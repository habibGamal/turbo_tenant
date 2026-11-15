<?php

declare(strict_types=1);

namespace Tests\Feature\OrderManagement;

use App\Models\User;
use App\Plugins\OrderManagement\src\Models\Order;
use Tests\TestCase;

final class OrderHistoryFiltersTest extends TestCase
{
    public function test_filters_are_applied_to_the_customers_full_history_before_pagination(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id, 'order_number' => 'FILTER-MATCH-123', 'status' => 'completed', 'payment_status' => 'completed']);
        Order::factory()->create(['user_id' => $user->id, 'order_number' => 'FILTER-MATCH-456', 'status' => 'pending', 'payment_status' => 'pending']);
        Order::factory()->create(['user_id' => $other->id, 'order_number' => 'FILTER-MATCH-789', 'status' => 'completed', 'payment_status' => 'completed']);

        $this->actingAs($user)->getJson('/api/orders?search=MATCH&status=completed&payment_status=completed&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', 'FILTER-MATCH-123');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/orders?status=unknown')
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }
}
