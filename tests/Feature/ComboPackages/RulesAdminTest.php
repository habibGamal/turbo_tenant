<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Core\Plugins\Contracts\TableColumnContributor;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\CreatePackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\ListPackages;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageAvailability;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\ComboPackages\src\Services\PackageRulesValidator;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;

use function Pest\Livewire\livewire;

final class FakeTestTableColumnContributor implements TableColumnContributor
{
    public function pluginId(): string
    {
        return 'combo-packages';
    }

    public function columns(): array
    {
        return [
            TextColumn::make('fake_contributed_column')
                ->label('Fake Col')
                ->state(fn ($record) => 'fake_'.$record->getKey()),
        ];
    }
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'base_price' => 50.0,
        'is_active' => true,
    ]);
    $this->branch = Branch::factory()->create(['name' => 'Main Branch']);
    $this->validator = new PackageRulesValidator();
    $this->service = app(PackageAvailabilityService::class);
});

it('saves availability, branches, order types, and limits from the new admin tab', function (): void {
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Full Merchandising Combo',
            'name_ar' => 'باقة القواعد الشاملة',
            'price' => 120.00,
            'max_per_order' => 4,
            'daily_limit' => 15,
            'allowed_order_types' => ['web_delivery'],
            'branches' => [$this->branch->id],
            'availabilities' => [
                [
                    'day_of_week' => 1,
                    'starts_at' => '11:00:00',
                    'ends_at' => '15:00:00',
                ],
            ],
        ])
        ->set('data.groups', [
            [
                'name' => 'Main Group',
                'selection_type' => 'all',
                'sort_order' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                        'sort_order' => 0,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = Package::where('name', 'Full Merchandising Combo')->first();
    expect($package)->not->toBeNull()
        ->and($package->max_per_order)->toBe(4)
        ->and($package->daily_limit)->toBe(15)
        ->and($package->allowed_order_types)->toBe(['web_delivery'])
        ->and($package->branches->pluck('id')->all())->toContain($this->branch->id)
        ->and($package->availabilities)->toHaveCount(1)
        ->and((int) $package->availabilities->first()->day_of_week)->toBe(1);
});

it('validates rules: starts_at != ends_at, no overlaps on same day, limits >= 1', function (): void {
    $baseState = [
        'name' => 'Test Combo',
        'price' => 50.0,
        'groups' => [
            [
                'name' => 'Group 1',
                'selection_type' => 'all',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    // 1. starts_at == ends_at
    $stateSameTimes = array_merge($baseState, [
        'availabilities' => [
            ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '12:00'],
        ],
    ]);
    expect($this->validator->validate($stateSameTimes))->toHaveKey('availabilities.0.ends_at');

    // 2. Overlapping windows on same day
    $stateOverlap = array_merge($baseState, [
        'availabilities' => [
            ['day_of_week' => 1, 'starts_at' => '10:00', 'ends_at' => '14:00'],
            ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00'],
        ],
    ]);
    expect($this->validator->validate($stateOverlap))->toHaveKey('availabilities');

    // 3. max_per_order < 1
    $stateInvalidMax = array_merge($baseState, ['max_per_order' => 0]);
    expect($this->validator->validate($stateInvalidMax))->toHaveKey('max_per_order');

    // 4. daily_limit < 1
    $stateInvalidDaily = array_merge($baseState, ['daily_limit' => 0]);
    expect($this->validator->validate($stateInvalidDaily))->toHaveKey('daily_limit');
});

it('computes health badge precedence correctly across all 7 states', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00'); // Monday 13:00

    $inactiveProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 40.0,
        'is_active' => false,
    ]);

    // 1. Inactive -> "غير نشطة"
    $pkg1 = Package::factory()->create(['is_active' => false]);
    expect($this->service->determineHealthStatus($pkg1))->toBe('غير نشطة');

    // 2. Expired -> "منتهية"
    $pkg2 = Package::factory()->create([
        'is_active' => true,
        'valid_until' => now()->subDay(),
    ]);
    expect($this->service->determineHealthStatus($pkg2))->toBe('منتهية');

    // 3. Scheduled -> "مجدولة"
    $pkg3 = Package::factory()->create([
        'is_active' => true,
        'valid_from' => now()->addDay(),
        'valid_until' => null,
    ]);
    expect($this->service->determineHealthStatus($pkg3))->toBe('مجدولة');

    // 4. Unfulfillable (contains inactive product in an 'all' group) -> "غير مكتملة"
    $pkg4 = Package::factory()->create(['is_active' => true]);
    $g4 = PackageGroup::create(['package_id' => $pkg4->id, 'selection_type' => 'all']);
    PackageItem::create(['package_group_id' => $g4->id, 'product_id' => $inactiveProduct->id, 'quantity' => 1]);
    expect($this->service->determineHealthStatus($pkg4))->toBe('غير مكتملة');

    // Helper for packages with a valid product
    $createFulfillable = function (array $attrs = []): Package {
        $p = Package::factory()->create(array_merge(['is_active' => true], $attrs));
        $g = PackageGroup::create(['package_id' => $p->id, 'selection_type' => 'all']);
        PackageItem::create(['package_group_id' => $g->id, 'product_id' => $this->product->id, 'quantity' => 1]);

        return $p;
    };

    // 5. Sold out today -> "نفدت اليوم"
    $pkg5 = $createFulfillable(['daily_limit' => 2]);
    $order = Order::create([
        'order_number' => 'ORD-H5',
        'user_id' => auth()->id(),
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $pkg5->id,
        'product_name' => 'Pkg5',
        'quantity' => 2,
        'unit_price' => 50,
        'total' => 100,
    ]);
    expect($this->service->determineHealthStatus($pkg5))->toBe('نفدت اليوم');

    // 6. Outside hours -> "خارج أوقات التوفر"
    $pkg6 = $createFulfillable();
    PackageAvailability::create([
        'package_id' => $pkg6->id,
        'day_of_week' => 1,
        'starts_at' => '15:00:00', // Window is 15:00-18:00, but test now is 13:00
        'ends_at' => '18:00:00',
    ]);
    expect($this->service->determineHealthStatus($pkg6))->toBe('خارج أوقات التوفر');

    // 7. Available -> "متاحة"
    $pkg7 = $createFulfillable();
    PackageAvailability::create([
        'package_id' => $pkg7->id,
        'day_of_week' => 1,
        'starts_at' => '10:00:00',
        'ends_at' => '15:00:00',
    ]);
    expect($this->service->determineHealthStatus($pkg7))->toBe('متاحة');
});

it('filters packages table by health_status', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    $activePackage = Package::factory()->create(['name' => 'Active Available', 'is_active' => true]);
    $g1 = PackageGroup::create(['package_id' => $activePackage->id, 'selection_type' => 'all']);
    PackageItem::create(['package_group_id' => $g1->id, 'product_id' => $this->product->id, 'quantity' => 1]);

    $inactivePackage = Package::factory()->create(['name' => 'Inactive Package', 'is_active' => false]);

    livewire(ListPackages::class)
        ->filterTable('health_status', 'available')
        ->assertCanSeeTableRecords([$activePackage])
        ->assertCanNotSeeTableRecords([$inactivePackage]);

    livewire(ListPackages::class)
        ->filterTable('health_status', 'inactive')
        ->assertCanSeeTableRecords([$inactivePackage])
        ->assertCanNotSeeTableRecords([$activePackage]);
});

it('renders contributed columns from admin.table.combo-packages.packages', function (): void {
    app()->singleton(FakeTestTableColumnContributor::class);
    app()->tag([FakeTestTableColumnContributor::class], 'admin.table.combo-packages.packages');

    livewire(ListPackages::class)
        ->assertTableColumnExists('fake_contributed_column');
});
