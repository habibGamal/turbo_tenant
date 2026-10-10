<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExtraOptionItem;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemExtra>
 */
final class OrderItemExtraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'extra_option_item_id' => ExtraOptionItem::factory(),
            'extra_name' => fake()->word(),
            'extra_name_ar' => fake()->word(),
            'extra_price' => fake()->randomFloat(2, 1, 20),
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}
