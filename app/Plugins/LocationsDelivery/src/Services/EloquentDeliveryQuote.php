<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Services;

use App\Plugins\LocationsDelivery\src\Contracts\DeliveryQuote;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;

final class EloquentDeliveryQuote implements DeliveryQuote
{
    public function feeForArea(int $areaId): float
    {
        if ($areaId <= 0) {
            return 0.0;
        }

        $area = Area::find($areaId);

        return $area instanceof Area ? (float) $area->shipping_cost : 0.0;
    }

    public function feeForAddress(int $addressId): float
    {
        if ($addressId <= 0) {
            return 0.0;
        }

        $address = Address::with('area')->find($addressId);
        $area = $address?->area;

        return $area instanceof Area ? (float) $area->shipping_cost : 0.0;
    }
}
