<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Http\Controllers;

use App\Plugins\MetaMarketing\src\Catalog\CatalogFeedService;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meta product catalog feed (CSV / XML) and the Arabic language override feed.
 */
final class CatalogFeedController
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
        private readonly CatalogFeedService $feedService,
    ) {}

    public function primary(string $token, string $format): Response
    {
        if (! $this->config->catalogFeedAvailable() || ! hash_equals((string) $this->config->catalogFeedToken, $token)) {
            abort(404);
        }

        if ($format === 'csv') {
            $content = $this->feedService->generatePrimaryFeed('csv');

            return response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        if ($format === 'xml') {
            $content = $this->feedService->generatePrimaryFeed('xml');

            return response($content, 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]);
        }

        abort(404);
    }

    public function arabicOverride(string $token): Response
    {
        if (! $this->config->catalogFeedAvailable() || ! hash_equals((string) $this->config->catalogFeedToken, $token)) {
            abort(404);
        }

        $content = $this->feedService->generateArabicFeed();

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
