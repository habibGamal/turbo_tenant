<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CartEngine\src\Models\CartItem>
 */
final class CartItemFactory extends Factory
{
    protected $model = \App\Plugins\CartEngine\src\Models\CartItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 10);

        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'variant_id' => fake()->boolean(50) ? ProductVariant::factory() : null,
            'quantity' => $quantity,
        ];
    }
}
