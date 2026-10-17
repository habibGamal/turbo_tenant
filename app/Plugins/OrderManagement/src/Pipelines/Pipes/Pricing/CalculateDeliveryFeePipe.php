<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use Closure;

final class CalculateDeliveryFeePipe implements PricingCalculationPipeInterface
{
    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        $type = (string) ($context->data->type ?? 'web_delivery');

        if (in_array($type, ['web_takeaway', 'pos'], true)) {
            return $next($context);
        }

        $deliveryFee = 0.0;
        $user = $context->data->user;

        // Guest user area
        if (! ($user instanceof User) && isset($user->area_id) && $user->area_id) {
            $area = Area::find($user->area_id);
            $deliveryFee = (float) ($area?->shipping_cost ?? 0.0);
        } elseif ($context->data->addressId) {
            $address = Address::with('area')->find($context->data->addressId);
            $deliveryFee = (float) ($address?->area?->shipping_cost ?? 0.0);
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
