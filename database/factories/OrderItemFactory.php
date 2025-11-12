<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
final class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 100);
        $quantity = fake()->randomFloat(3, 1, 10);
        $total = $unitPrice * $quantity;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'variant_id' => fake()->boolean(50) ? ProductVariant::factory() : null,
            'product_name' => fake()->words(3, true),
            'variant_name' => fake()->boolean(50) ? fake()->randomElement(['Small', 'Medium', 'Large']) : null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
        ];
    }
}
