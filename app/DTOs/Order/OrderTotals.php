<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class OrderTotals
{
    public function __construct(
        public float $subTotal,
        public float $discount,
        public float $tax,
        public float $service,
        public float $deliveryFee,
        public float $total
    ) {}
}
