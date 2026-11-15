<?php

declare(strict_types=1);

use App\Core\Plugins\Contracts\FormComponentContributor;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\CreatePackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\EditPackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\ListPackages;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'base_price' => 100.00,
        'price_after_discount' => null,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);
});

it('has image upload field on create page and image column on table', function () {
    livewire(CreatePackage::class)
        ->assertFormFieldExists('image');

    livewire(ListPackages::class)
        ->assertTableColumnExists('image');
});

it('creates a valid package with nested groups and items and persists them', function () {
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Mega Feast',
            'name_ar' => 'وجبة التوفير الكبرى',
            'price' => 150.00,
        ])
        ->set('data.groups', [
            [
                'name' => 'Main Sandwiches',
                'name_ar' => 'السندوتشات الرئيسية',
                'selection_type' => 'all',
                'sort_order' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'sort_order' => 0,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = Package::where('name', 'Mega Feast')->first();
    expect($package)->not->toBeNull()
        ->and($package->groups)->toHaveCount(1)
        ->and($package->groups->first()->items)->toHaveCount(1)
        ->and($package->groups->first()->items->first()->product_id)->toBe($this->product->id)
        ->and($package->groups->first()->items->first()->quantity)->toBe(2);
});

it('shows errors and persists nothing for an invalid package', function () {
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Invalid Package Choice',
            'price' => 100.00,
        ])
        ->set('data.groups', [
            [
                'name' => 'Sides',
                'selection_type' => 'choose_multiple',
                'min_selections' => 1,
                'max_selections' => 5, // only 1 item in group, max cannot be 5
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['groups.0.max_selections']);

    expect(Package::where('name', 'Invalid Package Choice')->exists())->toBeFalse();
});

it('computes discount_percentage on save when original_price is given or estimated', function () {
    // 1. With estimated original price: product base_price 100 * 2 = 200. Package price = 150.
    // original_price left blank => estimated 200, discount_percentage = round((200-150)/200 * 100) = 25%
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Auto Discount Package',
            'price' => 150.00,
            'original_price' => null,
        ])
        ->set('data.groups', [
            [
                'name' => 'Items',
                'selection_type' => 'all',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pkg = Package::where('name', 'Auto Discount Package')->first();
    expect($pkg)->not->toBeNull()
        ->and((float) $pkg->original_price)->toBe(200.00)
        ->and($pkg->discount_percentage)->toBe(25);

    // 2. With manually specified original_price: 250, package price 175 => 30% discount
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Manual Discount Package',
            'price' => 175.00,
            'original_price' => 250.00,
        ])
        ->set('data.groups', [
            [
                'name' => 'Items',
                'selection_type' => 'all',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $manualPkg = Package::where('name', 'Manual Discount Package')->first();
    expect($manualPkg)->not->toBeNull()
        ->and((float) $manualPkg->original_price)->toBe(250.00)
        ->and($manualPkg->discount_percentage)->toBe(30);
});

it('deep-copies package with is_active false via duplicate action', function () {
    $package = Package::factory()->create([
        'name' => 'Original Package',
        'name_ar' => 'الباقة الأصلية',
        'is_active' => true,
        'price' => 120.00,
    ]);

    $group = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Group 1',
        'selection_type' => 'all',
        'sort_order' => 1,
    ]);

    PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'sort_order' => 1,
    ]);

    livewire(ListPackages::class)
        ->callTableAction('duplicate', $package);

    $copy = Package::where('name', 'Original Package (نسخة)')->first();
    expect($copy)->not->toBeNull()
        ->and($copy->name_ar)->toBe('الباقة الأصلية (نسخة)')
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->price)->toBe(120.00)
        ->and($copy->groups)->toHaveCount(1)
        ->and($copy->groups->first()->items)->toHaveCount(1)
        ->and($copy->groups->first()->items->first()->product_id)->toBe($this->product->id)
        ->and($copy->groups->first()->items->first()->quantity)->toBe(2);
});

it('activates and deactivates packages via bulk actions', function () {
    $pkg1 = Package::factory()->create(['name' => 'P1', 'is_active' => false]);
    $pkg2 = Package::factory()->create(['name' => 'P2', 'is_active' => false]);

    livewire(ListPackages::class)
        ->callTableBulkAction('activate', [$pkg1, $pkg2]);

    expect($pkg1->fresh()->is_active)->toBeTrue()
        ->and($pkg2->fresh()->is_active)->toBeTrue();

    livewire(ListPackages::class)
        ->callTableBulkAction('deactivate', [$pkg1, $pkg2]);

    expect($pkg1->fresh()->is_active)->toBeFalse()
        ->and($pkg2->fresh()->is_active)->toBeFalse();
});

it('updates an existing package with validation and recalculation on edit page', function () {
    $package = Package::factory()->create([
        'name' => 'Editable Package',
        'price' => 100.00,
        'original_price' => 150.00,
        'discount_percentage' => 33,
        'gradient' => 'from-orange-500/20 via-red-500/10 to-pink-500/20',
        'icon' => 'gift',
    ]);

    $group = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Group 1',
        'selection_type' => 'all',
        'sort_order' => 0,
    ]);

    PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'sort_order' => 0,
    ]);

    livewire(EditPackage::class, ['record' => $package->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Editable Package',
            'price' => 80.00,
            'original_price' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $package->fresh();
    expect($fresh->name)->toBe('Updated Editable Package')
        ->and((float) $fresh->price)->toBe(80.00)
        ->and((float) $fresh->original_price)->toBe(100.00)
        ->and($fresh->discount_percentage)->toBe(20);
});

it('stores the Arabic name in name when only name_ar is provided', function () {
    livewire(CreatePackage::class)
        ->fillForm([
            'name' => null,
            'name_ar' => 'وجبة عائلية',
            'price' => 100,
        ])
        ->set('data.groups', [[
            'name' => 'Main',
            'selection_type' => 'all',
            'sort_order' => 0,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'sort_order' => 0]],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Package::query()->where('name_ar', 'وجبة عائلية')->value('name'))->toBe('وجبة عائلية');
});

it('creates and saves new selection options fields on package, group and item', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Large',
        'price' => 120.00,
        'is_available' => true,
    ]);

    livewire(CreatePackage::class)
        ->fillForm([
            'name' => 'Configured Package',
            'name_ar' => 'باقة مخصصة',
            'price' => 150.00,
            'allow_coupons' => false,
        ])
        ->set('data.groups', [
            [
                'name' => 'Custom Group',
                'name_ar' => 'مجموعة مخصصة',
                'description' => 'Group English description',
                'description_ar' => 'وصف المجموعة بالعربي',
                'selection_type' => 'choose_multiple',
                'allow_repeats' => true,
                'min_selections' => 1,
                'max_selections' => 3,
                'sort_order' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'variant_mode' => 'customer_choice',
                        'quantity' => 1,
                        'price_adjustment' => 0,
                        'sort_order' => 0,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = Package::where('name', 'Configured Package')->first();
    expect($package)->not->toBeNull()
        ->and($package->allow_coupons)->toBeFalse()
        ->and($package->groups)->toHaveCount(1);

    $group = $package->groups->first();
    expect($group->allow_repeats)->toBeTrue()
        ->and($group->description)->toBe('Group English description')
        ->and($group->description_ar)->toBe('وصف المجموعة بالعربي')
        ->and($group->items)->toHaveCount(1);

    $item = $group->items->first();
    expect($item->variant_mode)->toBe('customer_choice')
        ->and($item->variant_id)->toBeNull();
});

final class TestFakePackageFormContributor implements FormComponentContributor
{
    public static bool $savedOnCreate = false;

    public static bool $savedOnEdit = false;

    public function pluginId(): string
    {
        return 'combo-packages';
    }

    public function components(): array
    {
        return [
            Filament\Forms\Components\TextInput::make('contributed_field')
                ->label('حقل تكاملات تجريبي')
                ->dehydrated(false)
                ->saveRelationshipsUsing(function ($record, $state) {
                    if ($record->wasRecentlyCreated) {
                        self::$savedOnCreate = true;
                    } else {
                        self::$savedOnEdit = true;
                    }
                }),
        ];
    }
}

it('renders contributed components in تكاملات tab and runs saveRelationshipsUsing on create and edit', function (): void {
    app()->singleton(TestFakePackageFormContributor::class);
    app()->tag([TestFakePackageFormContributor::class], 'admin.form.combo-packages.package');

    TestFakePackageFormContributor::$savedOnCreate = false;
    TestFakePackageFormContributor::$savedOnEdit = false;

    livewire(CreatePackage::class)
        ->assertFormFieldExists('contributed_field')
        ->fillForm([
            'name' => 'Contributed Package',
            'name_ar' => 'باقة تكاملات',
            'price' => 120.00,
            'contributed_field' => 'init-value',
        ])
        ->set('data.groups', [
            [
                'name' => 'Main Sandwiches',
                'selection_type' => 'all',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TestFakePackageFormContributor::$savedOnCreate)->toBeTrue();

    $package = Package::where('name', 'Contributed Package')->first();
    expect($package)->not->toBeNull();

    livewire(EditPackage::class, ['record' => $package->getKey()])
        ->assertFormFieldExists('contributed_field')
        ->fillForm([
            'contributed_field' => 'updated-value',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(TestFakePackageFormContributor::$savedOnEdit)->toBeTrue();
});
