<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\OrderManagement\src\Models\Order;
use Database\Seeders\E2eSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds repeatable storefront fixtures for the live suite', function (): void {
    $this->seed(E2eSeeder::class);
    $this->seed(E2eSeeder::class);

    $product = Product::with(['variants', 'weightOption.values', 'extraOption.items', 'sections'])->findOrFail(5);
    expect($product->variants->pluck('price')->all())->toBe([600.0, 700.0])
        ->and($product->weightOption->values)->toHaveCount(4)
        ->and($product->extraOption->items)->toHaveCount(2)
        ->and($product->sections)->not->toBeEmpty()
        ->and(Product::whereIn('id', [5, 15, 16])->count())->toBe(3);
    $user = User::where('email', 'admin@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and(Order::where('user_id', $user->id)->where('order_number', 'E2E-0001')->count())->toBe(1);
});

it('serves the seeded catalog and customer order through the tenant APIs', function (): void {
    $this->seed(E2eSeeder::class);
    $this->getJson('/api/catalog-products/5')->assertOk()->assertJsonPath('data.name', 'E2E Simple Coffee');
    $this->getJson('/api/catalog-products/sections')->assertOk()->assertJsonFragment(['title' => 'E2E Featured']);
    $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())
        ->getJson('/api/orders')->assertOk()->assertJsonFragment(['order_number' => 'E2E-0001']);
});

it('refuses to overwrite an unrelated product with a required fixture ID', function (): void {
    Product::factory()->create(['id' => 5, 'name' => 'Existing product']);
    expect(fn () => $this->seed(E2eSeeder::class))->toThrow(RuntimeException::class, 'Product ID 5 is already in use');
    expect(Product::findOrFail(5)->name)->toBe('Existing product');
});

it('provides the fixed prices, empty category, page and paginated menu required by the browser suite', function (): void {
    $this->seed(E2eSeeder::class);
    expect(Product::findOrFail(15)->base_price)->toBe(166.0)
        ->and(Product::findOrFail(16)->base_price)->toBe(135.0)
        ->and(Product::where('is_active', true)->count())->toBeGreaterThan(50)
        ->and(App\Plugins\CatalogProducts\src\Models\Category::where('name', 'E2E Coffee Tools')->whereDoesntHave('products')->exists())->toBeTrue()
        ->and(App\Plugins\CmsPages\src\Models\Page::where('slug', 'about-us')->where('is_active', true)->exists())->toBeTrue()
        ->and(Product::findOrFail(5)->weightOption->values->pluck('value')->take(3)->all())->toBe(['0.125', '0.250', '0.500']);
});

it('provides a default delivery address for the seeded customer', function (): void {
    $this->seed(E2eSeeder::class);
    $user = User::where('email', 'admin@example.com')->firstOrFail();
    $this->actingAs($user)->getJson('/api/locations-delivery')
        ->assertOk()->assertJsonPath('data.addresses.0.street', 'E2E Test Street');
});

it('generates a stable integration token for the dedicated e2e fixture', function (): void {
    $this->seed(E2eSeeder::class);
    $tokens = app(App\Plugins\OrderManagement\src\Services\IntegrationTokenService::class);
    $first = $tokens->get();
    expect($first)->toBeString()->not->toBeEmpty();
    $this->seed(E2eSeeder::class);
    expect($tokens->get())->toBe($first);
});
