<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Resources;

use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CatalogProducts\src\Contracts\ProductDetailsContributor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Plugins\CatalogProducts\src\Models\Product
 *
 * @property \App\Plugins\CatalogProducts\src\Models\Product $resource
 */
final class ProductResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly PluginContributions $pluginContributions,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'image' => $this->image,
            'base_price' => $this->base_price,
            'price_after_discount' => $this->price_after_discount,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'sell_by_weight' => $this->sell_by_weight,
            'category' => $this->category ? new CategoryResource($this->category) : null,
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'extra_option' => $this->extraOption ? new ExtraOptionResource($this->extraOption, $this->resource) : null,
            'weight_option' => $this->weightOption ? new WeightOptionResource($this->weightOption) : null,
            'rating' => null,
            'reviews_count' => 0,
        ];

        foreach ($this->pluginContributions->active('catalog.product.details') as $contributor) {
            if ($contributor instanceof ProductDetailsContributor) {
                $data = array_merge($data, $contributor->contribute($this->resource));
            }
        }

        return $data;
    }
}
