<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\OrderManagement\src\Models\OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = \App\Plugins\OrderManagement\src\Models\OrderItem::class;

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
            'variant_id' => null,
            'product_name' => fake()->words(3, true),
            'variant_name' => null,
            'notes' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
        ];
    }

    public function withVariant(?ProductVariant $variant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => $variant?->id ?? ProductVariant::factory(),
            'variant_name' => $variant?->name ?? 'Default Variant',
        ]);
    }

    public function withNotes(string $notes = 'Special instructions'): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }
}
