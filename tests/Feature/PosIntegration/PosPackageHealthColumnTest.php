<?php

declare(strict_types=1);

use App\Core\Plugins\Contracts\TableColumnContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\PosIntegration\src\Filament\Contributors\PosPackageHealthColumnContributor;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
    PosPackageHealthColumnContributor::resetCache();
});

function createTestPackage(array $attributes = []): int
{
    return (int) DB::table('packages')->insertGetId(array_merge([
        'name' => 'Test Package '.fake()->word(),
        'name_ar' => 'باقة تجريبية',
        'description' => 'Test Description',
        'description_ar' => 'وصف تجريبي',
        'price' => 50.00,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function createTestPackageGroup(int $packageId, array $attributes = []): int
{
    return (int) DB::table('package_groups')->insertGetId(array_merge([
        'package_id' => $packageId,
        'name' => 'Group 1',
        'selection_type' => 'all',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function createTestPackageItem(int $groupId, int $productId, array $attributes = []): int
{
    return (int) DB::table('package_items')->insertGetId(array_merge([
        'package_group_id' => $groupId,
        'product_id' => $productId,
        'quantity' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function makeTestRecord(int $id): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->setAttribute('id', $id);

    return $model;
}

function mountColumnToTable(TextColumn $column): Table
{
    $livewire = new class extends Component implements HasTable
    {
        use InteractsWithTable;

        public function makeFilamentTranslatableContentDriver(): ?Filament\Support\Contracts\TranslatableContentDriver
        {
            return null;
        }

        public function getTableRecordKey(Model|array $record): string
        {
            return (string) ($record instanceof Model ? $record->getKey() : ($record['id'] ?? 0));
        }
    };

    $table = Table::make($livewire);
    $table->columns([$column]);

    return $table;
}

describe('PosPackageHealthColumnContributor', function () {
    it('implements TableColumnContributor and is tagged in service provider', function () {
        $contributor = app(PosPackageHealthColumnContributor::class);
        expect($contributor)->toBeInstanceOf(TableColumnContributor::class)
            ->and($contributor->pluginId())->toBe('pos-integration');

        $active = app(PluginContributions::class)->active('admin.table.combo-packages.packages');
        $classes = array_map(fn ($c) => get_class($c), $active);
        expect($classes)->toContain(PosPackageHealthColumnContributor::class);
    });

    it('returns one TextColumn pos_status labelled POS rendered as a badge with correct colors', function () {
        $contributor = app(PosPackageHealthColumnContributor::class);
        $columns = $contributor->columns();

        expect($columns)->toHaveCount(1);

        $column = $columns[0];
        expect($column)->toBeInstanceOf(TextColumn::class)
            ->and($column->getName())->toBe('pos_status')
            ->and($column->getLabel())->toBe('POS')
            ->and($column->isBadge())->toBeTrue();

        expect($column->getColor('مربوطة'))->toBe('success')
            ->and($column->getColor('مكونات مربوطة'))->toBe('info')
            ->and($column->getColor('غير مربوطة'))->toBe('danger');
    });

    it('returns مربوطة when package-level pos_line_mappings row exists', function () {
        $packageId = createTestPackage();
        $groupId = createTestPackageGroup($packageId);
        $product = Product::factory()->create(['name' => 'Some Burger']);
        createTestPackageItem($groupId, $product->id);

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => $packageId,
            'branch_id' => null,
            'pos_item_id' => 'POS-PKG-99',
        ]);

        $contributor = app(PosPackageHealthColumnContributor::class);
        $column = $contributor->columns()[0];
        mountColumnToTable($column);

        $record = makeTestRecord($packageId);
        $column->record($record);

        $state = $column->getState();
        expect($state)->toBe('مربوطة')
            ->and($column->getColor($state))->toBe('success')
            ->and($column->getTooltip($state))->toBeNull();
    });

    it('returns مكونات مربوطة when every component product has a product_pos_mappings row', function () {
        $packageId = createTestPackage();
        $groupId = createTestPackageGroup($packageId);

        $prod1 = Product::factory()->create(['name' => 'Fries']);
        $prod2 = Product::factory()->create(['name' => 'Drink']);
        createTestPackageItem($groupId, $prod1->id);
        createTestPackageItem($groupId, $prod2->id);

        ProductPosMapping::create([
            'product_id' => $prod1->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'POS-PROD-1',
        ]);
        ProductPosMapping::create([
            'product_id' => $prod2->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'POS-PROD-2',
        ]);

        $contributor = app(PosPackageHealthColumnContributor::class);
        $column = $contributor->columns()[0];
        mountColumnToTable($column);

        $record = makeTestRecord($packageId);
        $column->record($record);

        $state = $column->getState();
        expect($state)->toBe('مكونات مربوطة')
            ->and($column->getColor($state))->toBe('info')
            ->and($column->getTooltip($state))->toBeNull();
    });

    it('returns غير مربوطة with tooltip listing unmapped product names when a product is unmapped', function () {
        $packageId = createTestPackage();
        $groupId = createTestPackageGroup($packageId);

        $mappedProd = Product::factory()->create(['name' => 'Mapped Chicken']);
        $unmappedProd = Product::factory()->create(['name' => 'Unmapped Soda']);
        createTestPackageItem($groupId, $mappedProd->id);
        createTestPackageItem($groupId, $unmappedProd->id);

        ProductPosMapping::create([
            'product_id' => $mappedProd->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'POS-CHICKEN',
        ]);

        $contributor = app(PosPackageHealthColumnContributor::class);
        $column = $contributor->columns()[0];
        mountColumnToTable($column);

        $record = makeTestRecord($packageId);
        $column->record($record);

        $state = $column->getState();
        expect($state)->toBe('غير مربوطة')
            ->and($column->getColor($state))->toBe('danger');

        $tooltip = $column->getTooltip($state);
        expect($tooltip)->toBe('Unmapped Soda');
    });

    it('lists multiple unmapped product names in tooltip for multiple unmapped products', function () {
        $packageId = createTestPackage();
        $groupId = createTestPackageGroup($packageId);

        $prodA = Product::factory()->create(['name' => 'Product Alpha']);
        $prodB = Product::factory()->create(['name' => 'Product Beta']);
        createTestPackageItem($groupId, $prodA->id);
        createTestPackageItem($groupId, $prodB->id);

        $contributor = app(PosPackageHealthColumnContributor::class);
        $column = $contributor->columns()[0];
        mountColumnToTable($column);

        $record = makeTestRecord($packageId);
        $column->record($record);

        $state = $column->getState();
        expect($state)->toBe('غير مربوطة');

        $tooltip = $column->getTooltip($state);
        expect($tooltip)->toContain('Product Alpha')
            ->and($tooltip)->toContain('Product Beta');
    });

    it('computes status with a fixed query count for N packages', function () {
        $packageIds = [];
        for ($i = 0; $i < 6; $i++) {
            $pkgId = createTestPackage();
            $groupId = createTestPackageGroup($pkgId);
            $prod = Product::factory()->create();
            createTestPackageItem($groupId, $prod->id);

            if ($i % 2 === 0) {
                ProductPosMapping::create([
                    'product_id' => $prod->id,
                    'variant_id' => null,
                    'extra_option_item_id' => null,
                    'branch_id' => null,
                    'pos_item_id' => 'POS-BATCH-'.$i,
                ]);
            }
            $packageIds[] = $pkgId;
        }

        PosPackageHealthColumnContributor::resetCache();

        $contributor = app(PosPackageHealthColumnContributor::class);
        $column = $contributor->columns()[0];
        mountColumnToTable($column);

        DB::flushQueryLog();
        DB::enableQueryLog();

        // First package triggers the candidate batch load
        $firstRecord = makeTestRecord($packageIds[0]);
        $column->record($firstRecord);
        $state1 = $column->getState();
        $tooltip1 = $column->getTooltip($state1);

        $queriesAfterFirst = count(DB::getQueryLog());
        expect($queriesAfterFirst)->toBeGreaterThan(0);

        // Subsequent packages must NOT execute additional queries (O(1) total query count)
        for ($i = 1; $i < count($packageIds); $i++) {
            $record = makeTestRecord($packageIds[$i]);
            $column->record($record);
            $state = $column->getState();
            $tooltip = $column->getTooltip($state);
        }

        $queriesAfterAll = count(DB::getQueryLog());
        expect($queriesAfterAll)->toBe($queriesAfterFirst);
    });

    it('returns empty columns array when combo tables do not exist', function () {
        $mock = Mockery::mock();
        $contributor = new PosPackageHealthColumnContributor();

        Schema::shouldReceive('hasTable')
            ->with('packages')
            ->andReturn(false);

        expect($contributor->columns())->toBe([]);
    });

    it('renders inside a Filament table test harness', function () {
        $pkg1 = createTestPackage();
        $group1 = createTestPackageGroup($pkg1);
        $prod1 = Product::factory()->create(['name' => 'Table Harness Product']);
        createTestPackageItem($group1, $prod1->id);

        ProductPosMapping::create([
            'product_id' => $prod1->id,
            'pos_item_id' => 'HARNESS-POS-1',
        ]);

        $contributor = new PosPackageHealthColumnContributor();
        $column = $contributor->columns()[0];
        $table = mountColumnToTable($column);

        $record = makeTestRecord($pkg1);
        $column->record($record);

        expect($column->getState())->toBe('مكونات مربوطة')
            ->and($column->getColor('مكونات مربوطة'))->toBe('info');
    });
});
