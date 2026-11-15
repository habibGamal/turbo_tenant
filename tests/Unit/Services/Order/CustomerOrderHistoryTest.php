<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Models\User;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\OrderManagement\src\Contracts\CustomerOrderHistory;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\EloquentCustomerOrderHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private CustomerOrderHistory $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = new EloquentCustomerOrderHistory();
    }

    public function test_implements_customer_order_history_contract(): void
    {
        $this->assertInstanceOf(CustomerOrderHistory::class, $this->history);
    }

    public function test_counts_active_orders_for_user_id_excluding_cancelled(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PREPARING->value,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);

        $this->assertSame(2, $this->history->countActiveOrders($user->id, null));
    }

    public function test_counts_active_orders_for_phone_when_user_id_is_null(): void
    {
        $phone = '+201011112222';

        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $phone,
            'status' => OrderStatus::PENDING->value,
        ]);
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $phone,
            'status' => OrderStatus::DELIVERED->value,
        ]);
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $phone,
            'status' => OrderStatus::CANCELLED->value,
        ]);

        $this->assertSame(2, $this->history->countActiveOrders(null, $phone));
    }

    public function test_filters_by_coupon_id_when_provided(): void
    {
        $user = User::factory()->create();
        $couponA = Coupon::factory()->create();
        $couponB = Coupon::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'coupon_id' => $couponA->id,
            'status' => OrderStatus::PENDING->value,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'coupon_id' => $couponB->id,
            'status' => OrderStatus::PENDING->value,
        ]);

        $this->assertSame(1, $this->history->countActiveOrders($user->id, null, $couponA->id));
        $this->assertSame(1, $this->history->countActiveOrders($user->id, null, $couponB->id));
        $this->assertSame(0, $this->history->countActiveOrders($user->id, null, 99999));
    }

    public function test_returns_zero_when_both_user_id_and_phone_are_null(): void
    {
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => null,
            'status' => OrderStatus::PENDING->value,
        ]);

        $this->assertSame(0, $this->history->countActiveOrders(null, null));
        $this->assertSame(0, $this->history->countActiveOrders(null, ''));
    }

    public function test_user_id_takes_precedence_over_phone_when_both_given(): void
    {
        $user = User::factory()->create();
        $otherPhone = '+201099999999';

        Order::factory()->create([
            'user_id' => $user->id,
            'customer_phone' => '+201000000000',
            'status' => OrderStatus::PENDING->value,
        ]);
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $otherPhone,
            'status' => OrderStatus::PENDING->value,
        ]);

        $this->assertSame(1, $this->history->countActiveOrders($user->id, $otherPhone));
    }

    public function test_guest_orders_without_a_stored_phone_still_count_by_guest_user_id(): void
    {
        $guest = \App\Plugins\GuestCheckout\src\Models\GuestUser::factory()->create(['phone' => '01011112222']);

        // Legacy guest order saved before customer_phone was snapshotted.
        Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'customer_phone' => null,
            'status' => OrderStatus::PENDING->value,
        ]);
        // Another guest's order with the same phone (matched by phone).
        Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
            'customer_phone' => '01011112222',
            'status' => OrderStatus::PENDING->value,
        ]);
        Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'customer_phone' => null,
            'status' => OrderStatus::CANCELLED->value,
        ]);

        $this->assertSame(2, $this->history->countActiveOrders(null, '01011112222', null, $guest->id));
        $this->assertSame(1, $this->history->countActiveOrders(null, null, null, $guest->id));
        $this->assertSame(1, $this->history->countActiveOrders(null, '01011112222'));
    }
}
