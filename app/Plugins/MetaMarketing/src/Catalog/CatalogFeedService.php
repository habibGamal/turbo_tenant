<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use App\Plugins\CatalogProducts\src\Models\Product;

/**
 * Builds the feed on every request. Meta fetches it at most hourly, and generation is a few chunked
 * queries, so the feed is never stale. Do not add Laravel Cache here: tenancy's CacheTenancyBootstrapper
 * needs a taggable store and the app runs on the database store.
 */
final class CatalogFeedService
{
    public function __construct(
        private readonly CatalogItemBuilder $itemBuilder,
        private readonly CatalogCsvWriter $csvWriter,
        private readonly CatalogXmlWriter $xmlWriter,
    ) {}

    public function generatePrimaryFeed(string $format): string
    {
        $items = [];
        $this->eachProduct(function (Product $product) use (&$items): void {
            array_push($items, ...$this->itemBuilder->buildForProduct($product));
        });

        return $format === 'xml'
            ? $this->xmlWriter->write($items)
            : $this->csvWriter->write($items);
    }

    public function generateArabicFeed(): string
    {
        $items = [];
        $this->eachProduct(function (Product $product) use (&$items): void {
            array_push($items, ...$this->itemBuilder->buildArabicForProduct($product));
        });

        return $this->csvWriter->writeArabic($items);
    }

    /**
     * @param  callable(Product): void  $callback
     */
    private function eachProduct(callable $callback): void
    {
        Product::query()
            ->with(['variants', 'category'])
            ->chunkById(200, function ($products) use ($callback): void {
                foreach ($products as $product) {
                    $callback($product);
                }
            });
    }
}
