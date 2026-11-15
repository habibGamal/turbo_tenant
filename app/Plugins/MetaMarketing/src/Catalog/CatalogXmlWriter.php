<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use XMLWriter;

final class CatalogXmlWriter
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    /**
     * @param  iterable<CatalogItem|array<string, string>>  $items
     */
    public function write(iterable $items): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $writer->startElement('channel');
        $writer->writeElement('title', $this->config->catalogBrand);
        $writer->writeElement('link', url('/'));
        $writer->writeElement('description', $this->config->catalogBrand.' Product Catalog');

        foreach ($items as $item) {
            $row = $item instanceof CatalogItem ? $item->toFeedRow() : (array) $item;

            $writer->startElement('item');
            $writer->writeElement('g:id', (string) ($row['id'] ?? ''));

            if (! empty($row['item_group_id'])) {
                $writer->writeElement('g:item_group_id', (string) $row['item_group_id']);
            }

            $writer->writeElement('g:title', (string) ($row['title'] ?? ''));
            $writer->writeElement('g:description', (string) ($row['description'] ?? ''));
            $writer->writeElement('g:availability', (string) ($row['availability'] ?? ''));
            $writer->writeElement('g:condition', (string) ($row['condition'] ?? 'new'));
            $writer->writeElement('g:price', (string) ($row['price'] ?? ''));

            if (! empty($row['sale_price'])) {
                $writer->writeElement('g:sale_price', (string) $row['sale_price']);
            }

            $writer->writeElement('g:link', (string) ($row['link'] ?? ''));
            $writer->writeElement('g:image_link', (string) ($row['image_link'] ?? ''));
            $writer->writeElement('g:brand', (string) ($row['brand'] ?? ''));

            if (! empty($row['product_type'])) {
                $writer->writeElement('g:product_type', (string) $row['product_type']);
            }

            if (! empty($row['custom_label_0'])) {
                $writer->writeElement('g:custom_label_0', (string) $row['custom_label_0']);
            }

            $writer->endElement(); // item
        }

        $writer->endElement(); // channel
        $writer->endElement(); // rss
        $writer->endDocument();

        return $writer->outputMemory();
    }
}
