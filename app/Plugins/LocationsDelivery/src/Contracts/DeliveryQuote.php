<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Contracts;

interface DeliveryQuote
{
    /**
     * Area's shipping_cost, 0.0 when missing.
     */
    public function feeForArea(int $areaId): float;

    /**
     * The address's area fee, 0.0 when missing.
     */
    public function feeForAddress(int $addressId): float;
}
