<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Core\Contracts\Customer;
use App\Models\User;
use App\Plugins\LocationsDelivery\src\Contracts\DeliveryQuote;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing\CalculateDeliveryFeePipe;
use Tests\TestCase;

final class CalculateDeliveryFeePipeTest extends TestCase
{
    public function test_registered_user_delivery_fee_calculated_via_delivery_quote(): void
    {
        $quoteMock = $this->createMock(DeliveryQuote::class);
        $quoteMock->expects($this->once())
            ->method('feeForAddress')
            ->with(42)
            ->willReturn(18.50);

        $pipe = new CalculateDeliveryFeePipe($quoteMock);

        $user = new User();
        $user->id = 1;

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::COD,
            addressId: 42,
            type: 'web_delivery'
        );

        $context = new PricingCalculationContext($data, []);

        $nextCalled = false;
        $pipe->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;

            return $ctx;
        });

        $this->assertTrue($nextCalled);
        $adjustments = $context->getAdjustments();
        $this->assertArrayHasKey('shipping:delivery', $adjustments);
        $this->assertSame('shipping:delivery', $adjustments['shipping:delivery']->identifier);
        $this->assertSame(18.50, $adjustments['shipping:delivery']->amount);
        $this->assertSame(PricingAdjustment::TYPE_SHIPPING, $adjustments['shipping:delivery']->type);
    }

    public function test_guest_user_delivery_fee_calculated_via_delivery_quote(): void
    {
        $quoteMock = $this->createMock(DeliveryQuote::class);
        $quoteMock->expects($this->once())
            ->method('feeForArea')
            ->with(7)
            ->willReturn(22.00);

        $pipe = new CalculateDeliveryFeePipe($quoteMock);

        $guest = new class implements Customer
        {
            public function customerId(): int|string
            {
                return 99;
            }

            public function customerName(): ?string
            {
                return 'Guest User';
            }

            public function customerPhone(): ?string
            {
                return '01012345678';
            }

            public function customerEmail(): ?string
            {
                return null;
            }

            public function isGuest(): bool
            {
                return true;
            }

            public function deliveryAreaId(): ?int
            {
                return 7;
            }

            public function customerAddress(): array
            {
                return ['area_id' => 7];
            }
        };

        $data = new PlaceOrderData(
            user: $guest,
            branchId: 1,
            paymentMethod: PaymentMethod::COD,
            type: 'web_delivery'
        );

        $context = new PricingCalculationContext($data, []);

        $nextCalled = false;
        $pipe->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;

            return $ctx;
        });

        $this->assertTrue($nextCalled);
        $adjustments = $context->getAdjustments();
        $this->assertArrayHasKey('shipping:delivery', $adjustments);
        $this->assertSame('shipping:delivery', $adjustments['shipping:delivery']->identifier);
        $this->assertSame(22.00, $adjustments['shipping:delivery']->amount);
    }

    public function test_zero_delivery_fee_does_not_add_adjustment(): void
    {
        $quoteMock = $this->createMock(DeliveryQuote::class);
        $quoteMock->expects($this->once())
            ->method('feeForAddress')
            ->with(10)
            ->willReturn(0.0);

        $pipe = new CalculateDeliveryFeePipe($quoteMock);

        $user = new User();
        $user->id = 1;

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::COD,
            addressId: 10,
            type: 'web_delivery'
        );

        $context = new PricingCalculationContext($data, []);

        $pipe->handle($context, fn ($ctx) => $ctx);

        $this->assertEmpty($context->getAdjustments());
    }

    public function test_takeaway_and_pos_skip_delivery_fee(): void
    {
        $quoteMock = $this->createMock(DeliveryQuote::class);
        $quoteMock->expects($this->never())->method('feeForArea');
        $quoteMock->expects($this->never())->method('feeForAddress');

        $pipe = new CalculateDeliveryFeePipe($quoteMock);

        $user = new User();
        $user->id = 1;

        foreach (['web_takeaway', 'pos'] as $type) {
            $data = new PlaceOrderData(
                user: $user,
                branchId: 1,
                paymentMethod: PaymentMethod::COD,
                addressId: 10,
                type: $type
            );

            $context = new PricingCalculationContext($data, []);
            $pipe->handle($context, fn ($ctx) => $ctx);

            $this->assertEmpty($context->getAdjustments());
        }
    }
}
