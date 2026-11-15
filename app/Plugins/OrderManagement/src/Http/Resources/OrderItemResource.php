<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Resources;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int|null $weight_option_value_id
 * @property int $weight_multiplier
 * @property string $product_name
 * @property string|null $product_name_ar
 * @property string|null $variant_name
 * @property string|null $variant_name_ar
 * @property string|float $quantity
 * @property float $unit_price
 * @property float $total
 * @property OrderItem $resource
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $components = [];
        if (($this->line_type ?? 'product') !== 'product') {
            $componentModels = $this->resource->relationLoaded('components')
                ? $this->resource->components
                : $this->resource->components()->get();

            $components = $componentModels->map(fn ($component) => [
                'source_id' => $component->source_id,
                'group_name' => $component->group_name,
                'group_name_ar' => $component->group_name_ar,
                'product_id' => $component->product_id,
                'variant_id' => $component->variant_id,
                'product_name' => $component->product_name,
                'product_name_ar' => $component->product_name_ar,
                'variant_name' => $component->variant_name,
                'variant_name_ar' => $component->variant_name_ar,
                'quantity' => (float) $component->quantity,
                'unit_price_adjustment' => (float) $component->unit_price_adjustment,
                'extras' => array_values(array_map(fn (array $extra): array => [
                    'id' => $extra['extra_option_item_id'] ?? $extra['id'] ?? null,
                    'name' => $extra['name'] ?? null,
                    'name_ar' => $extra['name_ar'] ?? null,
                    'price' => (float) ($extra['price'] ?? 0),
                    'quantity' => (int) ($extra['quantity'] ?? 1),
                ], $component->extras ?? [])),
                'notes' => $component->notes,
            ])->values()->all();
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'type' => $this->line_type ?? 'product',
            'reference_id' => $this->line_reference_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'weight_option_value_id' => $this->weight_option_value_id,
            'weight_multiplier' => $this->weight_multiplier,
            'product_name' => $this->product_name,
            'product_name_ar' => $this->product_name_ar,
            'variant_name' => $this->variant_name,
            'variant_name_ar' => $this->variant_name_ar,
            'notes' => $this->notes,
            'quantity' => (string) $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'components' => $components,
            'extras' => OrderItemExtraResource::collection($this->whenLoaded('extras')),
            'product' => $this->whenLoaded('product', function () {
                /** @var Product|null $product */
                $product = $this->resource->relationLoaded('product') ? $this->resource->product : null;
                if ($product === null) {
                    return null;
                }

                $weightOption = $product->relationLoaded('weightOption') && $product->weightOption !== null
                    ? [
                        'id' => $product->weightOption->id,
                        'name' => $product->weightOption->name ?? null,
                        'unit' => $product->weightOption->unit,
                    ]
                    : null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'name_ar' => $product->name_ar,
                    'image' => $product->image,
                    'sell_by_weight' => (bool) $product->sell_by_weight,
                    'weight_option' => $weightOption,
                ];
            }),
            'weight_option_value' => $this->whenLoaded('weightOptionValue', function () {
                /** @var WeightOptionValue|null $weightOptionValue */
                $weightOptionValue = $this->resource->relationLoaded('weightOptionValue') ? $this->resource->weightOptionValue : null;
                if ($weightOptionValue === null) {
                    return null;
                }

                return [
                    'id' => $weightOptionValue->id,
                    'value' => (string) $weightOptionValue->value,
                    'label' => $weightOptionValue->label,
                ];
            }),
        ];
    }
}
