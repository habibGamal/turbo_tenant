<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\DTOs;

final readonly class GuestCheckoutConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
