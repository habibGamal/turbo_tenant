<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Support\MetaContentId;
use Illuminate\Support\Facades\Storage;

final class CatalogItemBuilder
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    /**
     * @return list<CatalogItem>
     */
    public function buildForProduct(Product $product): array
    {
        $imageLink = $this->resolveImageUrl($product->image);
        if ($imageLink === null) {
            return [];
        }

        $brand = $this->config->catalogBrand;
        $productType = $product->category->name ?? '';
        $customLabel0 = $product->sell_by_weight ? 'by_weight' : '';
        $link = url('/products/'.$product->id);

        $variants = $product->variants;

        if ($variants->isNotEmpty()) {
            $items = [];
            foreach ($variants as $variant) {
                $rawTitle = mb_trim($product->name).' - '.mb_trim($variant->name);
                $title = mb_substr($rawTitle, 0, 200);
                $description = $this->sanitizeDescription($product->description, $title);

                $inStock = $product->is_active && (bool) $variant->is_available;
                $availability = $inStock ? 'in stock' : 'out of stock';

                // Pricing rule (CartPriceCalculator parity):
                // Variant price is used as-is with no sale_price (product discount is NOT applied to variants).
                // If variant price is null, fall back to product-level pricing rule.
                if ($variant->price !== null) {
                    $price = $this->formatMoney((float) $variant->price);
                    $salePrice = null;
                } else {
                    [$price, $salePrice] = $this->calculateProductPricing($product);
                }

                $items[] = new CatalogItem(
                    id: MetaContentId::for($product->id, $variant->id),
                    itemGroupId: MetaContentId::group($product->id),
                    title: $title,
                    description: $description,
                    availability: $availability,
                    condition: 'new',
                    price: $price,
                    salePrice: $salePrice,
                    link: $link,
                    imageLink: $imageLink,
                    brand: $brand,
                    productType: $productType,
                    customLabel0: $customLabel0,
                );
            }

            return $items;
        }

        $title = mb_substr(mb_trim($product->name), 0, 200);
        $description = $this->sanitizeDescription($product->description, $title);
        $availability = $product->is_active ? 'in stock' : 'out of stock';
        [$price, $salePrice] = $this->calculateProductPricing($product);

        return [
            new CatalogItem(
                id: MetaContentId::for($product->id),
                itemGroupId: null,
                title: $title,
                description: $description,
                availability: $availability,
                condition: 'new',
                price: $price,
                salePrice: $salePrice,
                link: $link,
                imageLink: $imageLink,
                brand: $brand,
                productType: $productType,
                customLabel0: $customLabel0,
            ),
        ];
    }

    /**
     * @return list<CatalogArabicItem>
     */
    public function buildArabicForProduct(Product $product): array
    {
        if ($this->resolveImageUrl($product->image) === null) {
            return [];
        }

        $nameAr = $product->name_ar !== null ? mb_trim($product->name_ar) : '';
        if ($nameAr === '') {
            return [];
        }

        $variants = $product->variants;

        if ($variants->isNotEmpty()) {
            $items = [];
            foreach ($variants as $variant) {
                $variantNameAr = $variant->name_ar !== null ? mb_trim($variant->name_ar) : '';
                $variantPart = $variantNameAr !== '' ? $variantNameAr : mb_trim($variant->name);
                $title = mb_substr($nameAr.' - '.$variantPart, 0, 200);

                $items[] = new CatalogArabicItem(
                    id: MetaContentId::for($product->id, $variant->id),
                    override: 'ar_AR',
                    title: $title,
                );
            }

            return $items;
        }

        return [
            new CatalogArabicItem(
                id: MetaContentId::for($product->id),
                override: 'ar_AR',
                title: mb_substr($nameAr, 0, 200),
            ),
        ];
    }

    public function resolveImageUrl(?string $image): ?string
    {
        if ($image === null || mb_trim($image) === '') {
            return null;
        }

        $trimmed = mb_trim($image);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            return url($trimmed);
        }

        $url = Storage::url($trimmed);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Mirror of CartPriceCalculator effective pricing logic.
     *
     * @see \App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator::getEffectivePrice
     *
     * @return array{0: string, 1: ?string}
     */
    public function calculateProductPricing(Product $product): array
    {
        $basePrice = (float) $product->base_price;
        $discountPrice = $product->price_after_discount !== null ? (float) $product->price_after_discount : null;

        if ($discountPrice !== null && $discountPrice < $basePrice) {
            return [
                $this->formatMoney($basePrice),
                $this->formatMoney($discountPrice),
            ];
        }

        if ($discountPrice !== null && $discountPrice >= $basePrice) {
            return [
                $this->formatMoney($discountPrice),
                null,
            ];
        }

        return [
            $this->formatMoney($basePrice),
            null,
        ];
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '').' '.$this->config->currency;
    }

    private function sanitizeDescription(?string $description, string $fallbackTitle): string
    {
        if ($description === null) {
            return $fallbackTitle;
        }

        $stripped = strip_tags($description);
        $collapsed = mb_trim((string) preg_replace('/\s+/', ' ', $stripped));

        if ($collapsed === '') {
            return $fallbackTitle;
        }

        return mb_substr($collapsed, 0, 9999);
    }
}
