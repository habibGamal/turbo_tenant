<?php

declare(strict_types=1);

namespace App\Services\Order\Contracts;

use App\DTOs\Order\OrderPlacementResult;
use App\DTOs\Order\PlaceOrderData;

interface PlaceOrderServiceInterface
{
    public function placeOrder(PlaceOrderData $data): OrderPlacementResult;
}
