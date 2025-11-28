<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
final class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subTotal = fake()->randomFloat(2, 20, 500);
        $tax = $subTotal * 0.1;
        $service = $subTotal * 0.05;
        $deliveryFee = fake()->randomFloat(2, 0, 10);
        $discount = fake()->boolean(30) ? fake()->randomFloat(2, 5, 50) : 0;
        $total = $subTotal + $tax + $service + $deliveryFee - $discount;

        return [
            'order_number' => fake()->unique()->numerify('ORD-########'),
            'shift_id' => fake()->numberBetween(1, 100),
            'status' => fake()->randomElement(['pending', 'processing', 'out_for_delivery', 'completed', 'cancelled']),
            'type' => fake()->randomElement(['web_delivery', 'web_takeaway', 'pos']),
            'sub_total' => $subTotal,
            'tax' => $tax,
            'service' => $service,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'total' => $total,
            'note' => fake()->boolean(30) ? fake()->sentence() : null,
            'user_id' => User::factory(),
            'coupon_id' => fake()->boolean(20) ? Coupon::factory() : null,
            'branch_id' => Branch::factory(),
            'address_id' => fake()->boolean(70) ? Address::factory() : null,
        ];
    }
}
