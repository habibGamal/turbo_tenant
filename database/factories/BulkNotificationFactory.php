<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BulkNotificationStatus;
use App\Models\BulkNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkNotification>
 */
final class BulkNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'data' => null,
            'status' => BulkNotificationStatus::DRAFT,
            'target_user_ids' => null,
            'scheduled_at' => null,
            'sent_at' => null,
            'total_recipients' => 0,
            'successful_sends' => 0,
            'failed_sends' => 0,
        ];
    }

    /**
     * Indicate that the notification is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkNotificationStatus::SCHEDULED,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    /**
     * Indicate that the notification is sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkNotificationStatus::SENT,
            'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'total_recipients' => $this->faker->numberBetween(10, 100),
            'successful_sends' => fn (array $attrs) => $attrs['total_recipients'],
        ]);
    }

    /**
     * Indicate that the notification failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkNotificationStatus::FAILED,
            'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'total_recipients' => $this->faker->numberBetween(10, 100),
            'successful_sends' => fn (array $attrs) => $this->faker->numberBetween(0, $attrs['total_recipients'] - 1),
            'failed_sends' => fn (array $attrs) => $attrs['total_recipients'] - $attrs['successful_sends'],
        ]);
    }

    /**
     * Indicate that the notification targets specific users.
     */
    public function withTargetUsers(array $userIds): static
    {
        return $this->state(fn (array $attributes) => [
            'target_user_ids' => $userIds,
        ]);
    }

    /**
     * Indicate that the notification has custom data.
     */
    public function withData(array $data): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => $data,
        ]);
    }
}
