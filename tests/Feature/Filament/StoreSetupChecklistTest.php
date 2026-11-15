<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Filament\Widgets\StoreSetupChecklist;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $this->actingAs($this->admin);
});

it('renders checklist items from active plugins and reflects completion data', function () {
    // 1. Initial state without setup data: incomplete
    $items = StoreSetupChecklist::getApplicableItems();
    $keys = array_column($items, 'key');

    expect($keys)->toContain('site_logo', 'active_branch', 'area', 'category', 'product', 'order');

    $keyedItems = collect($items)->keyBy('key');
    expect($keyedItems->get('site_logo')['is_completed'])->toBeFalse()
        ->and($keyedItems->get('active_branch')['is_completed'])->toBeFalse()
        ->and($keyedItems->get('area')['is_completed'])->toBeFalse()
        ->and($keyedItems->get('category')['is_completed'])->toBeFalse()
        ->and($keyedItems->get('product')['is_completed'])->toBeFalse()
        ->and($keyedItems->get('order')['is_completed'])->toBeFalse();

    // 2. Add required data and verify completion flips to true
    app(SettingsRepository::class)->set('site_logo', 'logo.png');
    Branch::factory()->create(['is_active' => true]);
    Area::factory()->create();
    $cat = Category::factory()->create();
    Product::factory()->create(['category_id' => $cat->id]);
    Order::factory()->create();

    $completedItems = collect(StoreSetupChecklist::getApplicableItems())->keyBy('key');
    expect($completedItems->get('site_logo')['is_completed'])->toBeTrue()
        ->and($completedItems->get('active_branch')['is_completed'])->toBeTrue()
        ->and($completedItems->get('area')['is_completed'])->toBeTrue()
        ->and($completedItems->get('category')['is_completed'])->toBeTrue()
        ->and($completedItems->get('product')['is_completed'])->toBeTrue()
        ->and($completedItems->get('order')['is_completed'])->toBeTrue();

    // 3. Verify widget rendering
    livewire(StoreSetupChecklist::class)
        ->assertOk()
        ->assertSee('شعار المتجر')
        ->assertSee('فرع نشط')
        ->assertSee('منطقة توصيل')
        ->assertSee('تصنيف منتجات')
        ->assertSee('إضافة منتج')
        ->assertSee('أول طلب');
});

it('filters out checklist items of inactive plugins', function () {
    $tenant = tenant();
    expect($tenant)->not->toBeNull();

    TenantPlugin::query()->where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'catalog-products')
        ->update(['status' => PluginStatus::DEACTIVATED]);

    app(PluginManager::class)->resetTenant($tenant);

    $items = StoreSetupChecklist::getApplicableItems();
    $keys = array_column($items, 'key');

    expect($keys)->not->toContain('category')
        ->and($keys)->not->toContain('product')
        ->and($keys)->toContain('site_logo', 'active_branch', 'area', 'order');
});
