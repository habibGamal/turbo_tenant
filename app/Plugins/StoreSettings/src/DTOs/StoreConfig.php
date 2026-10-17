<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\DTOs;

final readonly class StoreConfig
{
    public function __construct(
        public string $siteName = 'Restaurant',
        public ?string $siteNameAr = null,
        public string $currency = 'EGP',
        public float $taxRate = 0.0,
        public float $codFee = 0.0,
        public float $minOrderAmount = 0.0,
        public bool $maintenanceMode = false,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public ?string $contactAddress = null
    ) {}
}
