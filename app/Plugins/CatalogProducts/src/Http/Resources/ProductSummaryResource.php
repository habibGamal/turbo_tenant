<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Plugins\CatalogProducts\src\Models\Product
 *
 * @property \App\Plugins\CatalogProducts\src\Models\Product $resource
 */
final class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasVariants = ($this->variants_count ?? 0) > 0;
        $hasExtras = ! empty($this->extra_option_id);
        $image = $this->image;
        if (is_string($image) && $image !== '' && ! str_starts_with($image, 'http') && ! str_starts_with($image, '/') && ! str_starts_with($image, 'data:')) {
            $image = '/'.$image;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'image' => $image,
            'base_price' => $this->base_price,
            'price_after_discount' => $this->price_after_discount,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'sell_by_weight' => $this->sell_by_weight,
            'has_variants' => $hasVariants,
            'has_extras' => $hasExtras,
            'has_options' => $hasVariants || $hasExtras || (bool) $this->sell_by_weight,
            'category' => $this->category ? new CategoryResource($this->category) : null,
            'rating' => null,
        ];
    }
}
