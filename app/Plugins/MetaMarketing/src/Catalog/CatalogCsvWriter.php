<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use RuntimeException;

final class CatalogCsvWriter
{
    /**
     * @var list<string>
     */
    public const array HEADERS = [
        'id',
        'item_group_id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'sale_price',
        'link',
        'image_link',
        'brand',
        'product_type',
        'custom_label_0',
    ];

    /**
     * @var list<string>
     */
    public const array ARABIC_HEADERS = [
        'id',
        'override',
        'title',
    ];

    /**
     * @param  iterable<CatalogItem|array<string, string>>  $items
     */
    public function write(iterable $items): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Failed to open memory stream for CSV export.');
        }

        fputcsv($stream, self::HEADERS, ',', '"', '');

        foreach ($items as $item) {
            $row = $item instanceof CatalogItem ? $item->toFeedRow() : $item;

            fputcsv($stream, [
                $row['id'] ?? '',
                $row['item_group_id'] ?? '',
                $row['title'] ?? '',
                $row['description'] ?? '',
                $row['availability'] ?? '',
                $row['condition'] ?? '',
                $row['price'] ?? '',
                $row['sale_price'] ?? '',
                $row['link'] ?? '',
                $row['image_link'] ?? '',
                $row['brand'] ?? '',
                $row['product_type'] ?? '',
                $row['custom_label_0'] ?? '',
            ], ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param  iterable<CatalogArabicItem|array<string, string>>  $items
     */
    public function writeArabic(iterable $items): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Failed to open memory stream for Arabic CSV export.');
        }

        fputcsv($stream, self::ARABIC_HEADERS, ',', '"', '');

        foreach ($items as $item) {
            $row = $item instanceof CatalogArabicItem ? $item->toFeedRow() : $item;

            fputcsv($stream, [
                $row['id'] ?? '',
                $row['override'] ?? 'ar_AR',
                $row['title'] ?? '',
            ], ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }
}
