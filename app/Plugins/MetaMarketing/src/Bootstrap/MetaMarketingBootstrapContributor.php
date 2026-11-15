<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Bootstrap;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use Illuminate\Contracts\Auth\Authenticatable;

final class MetaMarketingBootstrapContributor implements BootstrapContributor
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    public function pluginId(): string
    {
        return 'meta-marketing';
    }

    public function contribute(?Authenticatable $user): array
    {
        return [
            'settings' => [
                'meta_pixel_id' => $this->config->pixelId,
                'meta_debug_mode' => $this->config->debugMode,
                // Storefront mirrors funnel events to POST /api/meta/events only when true.
                'meta_server_events_enabled' => $this->config->canSendServerEvents(),
            ],
        ];
    }
}
