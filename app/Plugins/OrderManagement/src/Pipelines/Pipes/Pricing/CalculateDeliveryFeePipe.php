<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing;

use App\Plugins\LocationsDelivery\src\Contracts\DeliveryQuote;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use Closure;

final readonly class CalculateDeliveryFeePipe implements PricingCalculationPipeInterface
{
    public function __construct(private DeliveryQuote $deliveryQuote) {}

    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        $type = (string) ($context->data->type ?? 'web_delivery');

        if (in_array($type, ['web_takeaway', 'pos'], true)) {
            return $next($context);
        }

        $deliveryFee = 0.0;
        $customer = $context->data->user;

        if ($customer->isGuest() && $customer->deliveryAreaId() !== null) {
            $deliveryFee = $this->deliveryQuote->feeForArea($customer->deliveryAreaId());
        } elseif ($context->data->addressId) {
            $deliveryFee = $this->deliveryQuote->feeForAddress((int) $context->data->addressId);
        }

        if ($deliveryFee > 0.0) {
            $context->addAdjustment(new PricingAdjustment(
                identifier: 'shipping:delivery',
                label: 'Delivery Fee',
                amount: $deliveryFee,
                type: PricingAdjustment::TYPE_SHIPPING
            ));
        }

        return $next($context);
    }
}
