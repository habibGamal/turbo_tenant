<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;

interface PlaceOrderServiceInterface
{
    public function placeOrder(PlaceOrderData $data): OrderPlacementResult;
}
