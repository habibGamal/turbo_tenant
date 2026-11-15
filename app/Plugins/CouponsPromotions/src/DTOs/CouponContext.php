<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\DTOs;

use App\Core\Contracts\Customer;
use App\Plugins\CartEngine\src\DTOs\CartLine;

final readonly class CouponContext
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public ?Customer $customer = null,
        public array $lines = [],
        public float $subtotal = 0.0,
        public ?int $areaId = null,
        public ?int $governorateId = null,
        public ?string $phone = null,
        public ?int $guestId = null,
    ) {}
}
