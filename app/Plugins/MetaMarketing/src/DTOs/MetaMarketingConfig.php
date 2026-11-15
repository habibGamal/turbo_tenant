<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\DTOs;

final readonly class MetaMarketingConfig
{
    public function __construct(
        public ?string $pixelId,
        public bool $debugMode,
        public bool $capiEnabled,
        public ?string $accessToken,
        public ?string $testEventCode,
        public string $graphVersion,
        public bool $catalogEnabled,
        public ?string $catalogFeedToken,
        public string $catalogBrand,
        public string $currency,
    ) {}

    /**
     * Server-side events can be sent only when CAPI is switched on and fully configured.
     */
    public function canSendServerEvents(): bool
    {
        return $this->capiEnabled && filled($this->pixelId) && filled($this->accessToken);
    }

    public function catalogFeedAvailable(): bool
    {
        return $this->catalogEnabled && filled($this->catalogFeedToken);
    }
}
