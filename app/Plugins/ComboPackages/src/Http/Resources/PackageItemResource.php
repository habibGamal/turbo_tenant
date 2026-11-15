<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $package_group_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property float|int|string $price_adjustment
 * @property bool $is_default
 * @property int $sort_order
 * @property bool $allow_extras
 * @property \App\Plugins\CatalogProducts\src\Models\Product|null $product
 * @property \App\Plugins\CatalogProducts\src\Models\ProductVariant|null $variant
 */
final class PackageItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variantMode = (string) ($this->variant_mode ?? 'fixed');
        $variantOptions = [];

        if ($variantMode === 'customer_choice' && $this->product) {
            $product = $this->product;
            $basePrice = ($product->price_after_discount !== null && (float) $product->price_after_discount > 0)
                ? (float) $product->price_after_discount
                : (float) $product->base_price;

            $variants = $product->relationLoaded('variants')
                ? $product->variants
                : $product->variants()->where('is_available', true)->orderBy('sort_order')->get();

            $variantOptions = $variants
                ->filter(fn ($v): bool => (bool) $v->is_available)
                ->map(fn ($v): array => [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                    'name_ar' => $v->name_ar !== null ? (string) $v->name_ar : null,
                    'price_delta' => max(0.0, round((float) $v->price - $basePrice, 2)),
                ])
                ->values()
                ->all();
        }

        $extraOption = null;
        if ((bool) ($this->allow_extras ?? false) && $this->product?->extraOption) {
            $extraOption = (new \App\Plugins\CatalogProducts\src\Http\Resources\ExtraOptionResource(
                $this->product->extraOption,
                $this->product
            ))->resolve($request);
        }

        return [
            'id' => (int) $this->id,
            'package_group_id' => (int) $this->package_group_id,
            'product_id' => (int) $this->product_id,
            'variant_id' => $this->variant_id !== null ? (int) $this->variant_id : null,
            'variant_mode' => $variantMode,
            'allow_extras' => (bool) ($this->allow_extras ?? false),
            'extra_option' => $extraOption,
            'variant_options' => $variantOptions,
            'quantity' => (int) $this->quantity,
            'price_adjustment' => (float) $this->price_adjustment,
            'is_default' => (bool) $this->is_default,
            'sort_order' => (int) $this->sort_order,
            'product' => $this->product ? [
                'id' => (int) $this->product->id,
                'name' => (string) $this->product->name,
                'name_ar' => $this->product->name_ar !== null ? (string) $this->product->name_ar : null,
                'image' => $this->product->image !== null ? (string) $this->product->image : null,
            ] : null,
            'variant' => $this->variant ? [
                'id' => (int) $this->variant->id,
                'name' => (string) $this->variant->name,
                'name_ar' => $this->variant->name_ar !== null ? (string) $this->variant->name_ar : null,
                'price' => (float) $this->variant->price,
            ] : null,
        ];
    }
}
