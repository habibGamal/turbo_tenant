<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\DTOs;

use App\Core\Contracts\Customer;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;

final readonly class PlaceOrderData
{
    /**
     * @param  array<string, mixed>  $billingData
     */
    public function __construct(
        public Customer $user,
        public int $branchId,
        public PaymentMethod $paymentMethod,
        public ?int $addressId = null,
        public ?int $couponId = null,
        public ?string $note = null,
        public string $type = 'web_delivery',
        public array $billingData = []
    ) {}

    public function isGuest(): bool
    {
        return $this->user->isGuest();
    }
}
