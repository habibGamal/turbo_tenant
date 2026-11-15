<?php

declare(strict_types=1);

use App\Plugins\HeroBanners\src\Models\HeroSlider;
use Database\Seeders\E2eSeeder;

it('hydrates the home document with the current session and public storefront collections', function (): void {
    $this->seed(E2eSeeder::class);
    $response = $this->get('/');
    $response->assertOk()->assertSee('window.__TENANT_BOOTSTRAP__', false)
        ->assertSee('E2E Featured', false)->assertSee('catalog-products', false)
        ->assertSee('combo-packages', false)->assertDontSee('loading_container', false);
});

it('serializes active banners through the plugin bootstrap contribution', function (): void {
    HeroSlider::factory()->create(['title' => 'Visible banner', 'is_active' => true]);
    HeroSlider::factory()->create(['title' => 'Hidden banner', 'is_active' => false]);
    $response = $this->getJson('/api/tenant/bootstrap')->assertOk();
    $queries = collect($response->json('query_data'));
    $banners = $queries->first(fn ($query) => $query['key'] === ['hero-banners', null]);
    expect($banners['data'])->toHaveCount(1)
        ->and($banners['data'][0]['title'])->toBe('Visible banner');
    expect($queries->contains(fn ($query) => $query['key'] === ['catalog-products', 'sections']))->toBeFalse();
});

it('does not hydrate contributions from inactive plugins', function (): void {
    App\Models\TenantPlugin::query()->where('tenant_id', (string) tenant()->id)
        ->where('plugin_id', 'hero-banners')->update(['status' => App\Core\Plugins\Enums\PluginStatus::DEACTIVATED]);
    app(App\Core\Plugins\Services\PluginManager::class)->resetTenant(tenant());
    $queries = collect($this->getJson('/api/tenant/bootstrap')->assertOk()->json('query_data'));
    expect($queries->contains(fn ($query) => $query['key'] === ['hero-banners', null]))->toBeFalse();
});

it('escapes session data embedded in the document script', function (): void {
    $user = auth()->user();
    $user->forceFill(['name' => '</script><script>window.injected=true</script>'])->save();
    $this->get('/')->assertOk()->assertDontSee('</script><script>window.injected=true</script>', false);
});

it('leaves the cart query to the cart API', function (): void {
    $this->get('/')->assertOk()->assertDontSee('\u0022cart\u0022', false);
});

it('delivers production storefront styles in the document', function (): void {
    $this->get('/')->assertOk()->assertSee('id="storefront-styles"', false);
});
