<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\DTOs;

final readonly class PaymentProcessingConfig
{
    /**
     * @param array<int> $paymobIntegrationIds
     */
    public function __construct(
        public string $activeGateway = 'paymob',
        public string $paymobBaseUrl = 'https://accept.paymob.com',
        public string $paymobSecretKey = '',
        public string $paymobPublicKey = '',
        public array $paymobIntegrationIds = [],
        public string $paymobHmacSecret = '',
        public string $kashierMerchantId = '',
        public string $kashierApiKey = '',
        public string $kashierSecretKey = '',
        public string $kashierMode = 'test',
        public string $kashierCurrency = 'EGP',
        public string $kashierAllowedMethods = 'card'
    ) {}
}
