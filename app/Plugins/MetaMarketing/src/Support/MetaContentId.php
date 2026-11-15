<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Support;

/**
 * The one place that builds Meta content IDs. Pixel events, Conversions API payloads and the
 * catalog feed must all use it, so event content_ids always equal the catalog's `id` column.
 *
 * Product without variants -> "{productId}"
 * Product variant          -> "{productId}-{variantId}" (item_group_id = "{productId}")
 *
 * Mirrored on the storefront by metaContentId() in resources/js/lib/meta-pixel.
 */
final class MetaContentId
{
    public static function for(int $productId, ?int $variantId = null): string
    {
        return $variantId === null ? (string) $productId : $productId.'-'.$variantId;
    }

    public static function group(int $productId): string
    {
        return (string) $productId;
    }
}
