<?php

declare(strict_types=1);

namespace App\Services\Order\Pricing;

use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PlaceOrderData;
use App\Enums\PaymentMethod;
use App\Enums\SettingKey;
use App\Models\Address;
use App\Models\Area;
use App\Models\Coupon;
use App\Models\GuestUser;
use App\Services\CouponService;
use App\Services\Order\Contracts\OrderPricingCalculatorInterface;
use App\Services\SettingService;

final readonly class OrderPricingCalculator implements OrderPricingCalculatorInterface
{
    public function __construct(
        private CouponService $couponService,
        private SettingService $settingService
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function calculate(PlaceOrderData $data, array $cartItems, ?Coupon $coupon): OrderTotals
    {
        $subTotal = array_reduce(
            $cartItems,
            fn ($total, $item): float => $total + (float) ($item['subtotal'] ?? 0),
            0.0
        );

        $discount = 0.0;
        if ($coupon) {
            $discount = (float) $this->couponService->calculateDiscount($coupon, $cartItems, $subTotal);
        }

        $taxableAmount = max(0.0, $subTotal - $discount);
        $tax = $this->calculateTax($taxableAmount);
        $service = $this->calculateService($taxableAmount);

        if ($data->paymentMethod === PaymentMethod::COD) {
            $codFee = (float) $this->settingService->get(SettingKey::COD_FEE, 0);
            $service += $codFee;
        }

        $baseDeliveryFee = $this->calculateBaseDeliveryFee($data);

        $deliveryFee = (float) $this->couponService->calculateShippingFee(
            $coupon,
            $baseDeliveryFee,
            $subTotal,
            $discount
        );

        $total = $subTotal - $discount + $tax + $service + $deliveryFee;

        return new OrderTotals(
            subTotal: $subTotal,
            discount: $discount,
            tax: $tax,
            service: $service,
            deliveryFee: $deliveryFee,
            total: $total
        );
    }

    private function calculateTax(float $amount): float
    {
        $taxRate = (float) config('app.tax_rate', 0.0);

        return round($amount * $taxRate, 2);
    }

    private function calculateService(float $amount): float
    {
        $serviceRate = (float) config('app.service_rate', 0.0);

        return round($amount * $serviceRate, 2);
    }

    private function calculateBaseDeliveryFee(PlaceOrderData $data): float
    {
        if (in_array($data->type, ['web_takeaway', 'pos'], true)) {
            return 0.0;
        }

        if ($data->user instanceof GuestUser) {
            if (! $data->user->area_id) {
                return 0.0;
            }

            return (float) (Area::find($data->user->area_id)->shipping_cost ?? 0.0);
        }

        if (! $data->addressId) {
            return 0.0;
        }

        $address = Address::with('area')->find($data->addressId);

        return (float) ($address?->area?->shipping_cost ?? 0.0);
    }
}
