<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\DTOs\Order\PlaceOrderData;
use App\Enums\PaymentMethod;
use App\Enums\SettingKey;
use App\Exceptions\OrderValidationException;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\Validators\OrderValidator;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderValidatorTest extends TestCase
{
    use RefreshDatabase;

    private OrderValidator $validator;

    private SettingService $settingService;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(OrderValidator::class);
        $this->settingService = app(SettingService::class);
        $this->branch = Branch::factory()->create();
    }

    public function test_throws_exception_when_cart_items_are_empty(): void
    {
        $this->expectException(OrderValidationException::class);
        $this->expectExceptionMessage('Cart is empty');

        $user = User::factory()->create();
        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $this->validator->validate($data, ['items' => []]);
    }

    public function test_throws_exception_when_cart_contains_inactive_product(): void
    {
        $this->expectException(OrderValidationException::class);
        $this->expectExceptionMessage('Some products are unavailable');

        $user = User::factory()->create();
        $inactiveProduct = Product::factory()->create([
            'name' => 'Unavailable Burger',
            'is_active' => false,
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $cart = [
            'items' => [
                [
                    'product_id' => $inactiveProduct->id,
                    'subtotal' => 50,
                ],
            ],
        ];

        $this->validator->validate($data, $cart);
    }

    public function test_throws_exception_when_store_closed_and_orders_not_accepted(): void
    {
        $this->expectException(OrderValidationException::class);
        $this->expectExceptionMessage('We are currently closed');

        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $currentDay = now()->format('l');
        $this->settingService->set(
            SettingKey::WORK_TIMES,
            json_encode([
                [
                    'day' => $currentDay,
                    'closed' => true,
                ],
            ])
        );
        $this->settingService->set(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'false');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $cart = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'subtotal' => 50,
                ],
            ],
        ];

        $this->validator->validate($data, $cart);
    }

    public function test_returns_coupon_when_valid(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $coupon = Coupon::factory()->create([
            'code' => 'VALID20',
            'type' => 'fixed',
            'value' => 20.00,
            'is_active' => true,
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: $coupon->id
        );

        $cart = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'subtotal' => 100,
                ],
            ],
        ];

        $validatedCoupon = $this->validator->validate($data, $cart);

        $this->assertNotNull($validatedCoupon);
        $this->assertSame($coupon->id, $validatedCoupon->id);
    }
}
