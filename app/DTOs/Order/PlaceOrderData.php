<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\Enums\PaymentMethod;
use App\Models\GuestUser;
use App\Models\User;

final readonly class PlaceOrderData
{
    /**
     * @param  array<string, mixed>  $billingData
     */
    public function __construct(
        public User|GuestUser $user,
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
        return $this->user instanceof GuestUser;
    }
}
