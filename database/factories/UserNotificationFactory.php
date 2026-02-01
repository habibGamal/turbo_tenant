<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserNotification>
 */
final class UserNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'data' => null,
            'read' => false,
            'read_at' => null,
            'type' => $this->faker->randomElement(['general', 'order', 'bulk', 'promo']),
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read' => true,
            'read_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the notification is an order notification.
     */
    public function order(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'order',
            'data' => [
                'order_id' => $this->faker->numberBetween(1, 1000),
                'order_number' => 'ORD-'.$this->faker->unique()->numerify('######'),
                'status' => $this->faker->randomElement(['pending', 'preparing', 'delivered']),
            ],
        ]);
    }

    /**
     * Indicate that the notification is a bulk notification.
     */
    public function bulk(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bulk',
        ]);
    }
}
