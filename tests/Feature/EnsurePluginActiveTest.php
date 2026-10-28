<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\HeroBanners\src\Models\HeroSlider;

it('returns 200 when plugin is active, 404 with feature not available when deactivated, and 200 once reactivated', function () {
    $tenant = static::$testTenant;
    $manager = app(PluginManager::class);

    HeroSlider::factory()->create(['title' => 'Banner 1', 'is_active' => true]);

    // 1. Initial active state returns 200
    $response = $this->getJson('/api/hero-banners');
    $response->assertStatus(200);

    // 2. Deactivate plugin -> returns 404
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'hero-banners')
        ->update(['status' => PluginStatus::DEACTIVATED]);
    $manager->resetTenant($tenant);

    $response = $this->getJson('/api/hero-banners');
    $response->assertStatus(404)
        ->assertExactJson([
            'status' => 'error',
            'message' => 'Feature not available',
        ]);

    // 3. Reactivate plugin -> returns 200
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'hero-banners')
        ->update(['status' => PluginStatus::ACTIVE]);
    $manager->resetTenant($tenant);

    $response = $this->getJson('/api/hero-banners');
    $response->assertStatus(200);
});

it('aborts product reviews api with 404 when product-reviews plugin is deactivated', function () {
    $tenant = static::$testTenant;
    $manager = app(PluginManager::class);

    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // Active returns 200
    $response = $this->getJson(route('reviews.index', $product));
    $response->assertStatus(200);

    // Deactivate -> returns 404
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'product-reviews')
        ->update(['status' => PluginStatus::DEACTIVATED]);
    $manager->resetTenant($tenant);

    $response = $this->getJson(route('reviews.index', $product));
    $response->assertStatus(404)
        ->assertExactJson([
            'status' => 'error',
            'message' => 'Feature not available',
        ]);

    // Reactivate -> returns 200
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'product-reviews')
        ->update(['status' => PluginStatus::ACTIVE]);
    $manager->resetTenant($tenant);

    $response = $this->getJson(route('reviews.index', $product));
    $response->assertStatus(200);
});

it('keeps ungated settlement routes accessible even when their plugin is deactivated', function () {
    $tenant = static::$testTenant;
    $manager = app(PluginManager::class);

    // Deactivate payment-processing directly in DB and reset
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'payment-processing')
        ->update(['status' => PluginStatus::DEACTIVATED]);
    $manager->resetTenant($tenant);

    // Gated payment route returns 404
    $this->getJson('/api/orders/999/payment-status')
        ->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Feature not available',
        ]);

    // Ungated webhook routes do NOT return 404 with "Feature not available"
    $responsePaymob = $this->postJson('/api/webhooks/paymob', []);
    expect($responsePaymob->status())->not->toBe(404);

    $responseKashier = $this->postJson('/api/webhooks/kashier', []);
    expect($responseKashier->status())->not->toBe(404);

    // Deactivate order-management directly in DB and reset
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'order-management')
        ->update(['status' => PluginStatus::DEACTIVATED]);
    $manager->resetTenant($tenant);

    // Gated order route returns 404
    $this->getJson('/api/checkout-data')
        ->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Feature not available',
        ]);

    // Ungated order-status route does NOT return 404 with "Feature not available"
    $responseOrderStatus = $this->postJson('/api/order-status', []);
    expect($responseOrderStatus->status())->not->toBe(404);
});
