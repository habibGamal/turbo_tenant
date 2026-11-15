<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Resources;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CatalogProducts\src\Http\Resources\WeightOptionResource;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CartLine $resource
 */
final class CartItemResource extends JsonResource
{
    public function __construct(
        CartLine $resource,
        private readonly ?Product $product = null
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $line = $this->resource;
        $product = $this->product;

        $weightOptionData = null;
        // Only the weight picker needs the product's full list of weight values.
        if ($line->sellByWeight && $product?->weightOption) {
            $weightOptionData = (new WeightOptionResource($product->weightOption))->resolve($request);
        }

        $variant = null;
        if ($line->variantId !== null) {
            $variant = [
                'id' => $line->variantId,
                'name' => $line->variantName,
                'name_ar' => $line->variantNameAr,
            ];
        }

        $weightOptionValue = null;
        if ($line->weightOptionValueId !== null) {
            $weightOptionValue = [
                'id' => $line->weightOptionValueId,
                'value' => $line->weightValue,
                'label' => $line->weightLabel,
            ];
        }

        $extras = [];
        foreach ($line->extras as $extra) {
            $extras[] = [
                'id' => $extra->extraOptionItemId,
                'name' => $extra->name,
                'name_ar' => $extra->nameAr,
                'price' => $extra->unitPrice,
                'quantity' => $extra->quantity,
            ];
        }

        $components = [];
        foreach ($line->components as $component) {
            $compExtras = [];
            foreach ($component->extras as $extra) {
                $compExtras[] = [
                    'id' => $extra->extraOptionItemId,
                    'name' => $extra->name,
                    'name_ar' => $extra->nameAr,
                    'price' => $extra->unitPrice,
                    'quantity' => $extra->quantity,
                ];
            }

            $components[] = [
                'source_id' => $component->sourceId,
                'group_name' => $component->groupName,
                'group_name_ar' => $component->groupNameAr,
                'product_id' => $component->productId,
                'variant_id' => $component->variantId,
                'product_name' => $component->productName,
                'product_name_ar' => $component->productNameAr,
                'variant_name' => $component->variantName,
                'variant_name_ar' => $component->variantNameAr,
                'quantity' => $component->quantity,
                'unit_price_adjustment' => $component->unitPriceAdjustment,
                'extras' => $compExtras,
                'notes' => $component->notes,
            ];
        }

        $productData = $line->type !== 'product'
            ? [
                'id' => null,
                'name' => $line->productName,
                'name_ar' => $line->productNameAr,
                'image' => $line->productImage,
                'sell_by_weight' => false,
                'weight_option' => null,
            ]
            : [
                'id' => $line->productId,
                'name' => $line->productName,
                'name_ar' => $line->productNameAr,
                'image' => $line->productImage,
                'sell_by_weight' => $line->sellByWeight,
                'weight_option' => $weightOptionData,
            ];

        return [
            'id' => $line->itemId,
            'type' => $line->type,
            'reference_id' => $line->referenceId,
            'product_id' => $line->type !== 'product' ? null : $line->productId,
            'quantity' => $line->quantity,
            'weight_multiplier' => $line->weightMultiplier,
            'notes' => $line->notes,
            'product' => $productData,
            'variant' => $line->type !== 'product' ? null : $variant,
            'weight_option_value' => $line->type !== 'product' ? null : $weightOptionValue,
            'extras' => $line->type !== 'product' ? [] : $extras,
            'components' => $components,
            'price' => $line->unitPrice,
            'extras_total' => $line->type !== 'product' ? 0.0 : $line->extrasUnitTotal,
            'subtotal' => $line->subtotal,
        ];
    }
}
