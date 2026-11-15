<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Resources;

use App\Plugins\CatalogProducts\src\DTOs\SelectionRules;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Plugins\CatalogProducts\src\Models\ExtraOption
 *
 * @property \App\Plugins\CatalogProducts\src\Models\ExtraOption $resource
 */
final class ExtraOptionResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly ?Product $product = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Rendered for a product, the product's overrides apply; on its own, the option's own values.
        $rules = $this->product?->effectiveSelectionRules()
            ?? new SelectionRules($this->min_selections, $this->max_selections, (bool) $this->allow_multiple);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'min_selections' => $rules->minSelections,
            'max_selections' => $rules->maxSelections,
            'allow_multiple' => $rules->allowMultiple,
            'items' => $this->whenLoaded('items', function (): array {
                /** @var iterable<object> $items */
                $items = $this->items;
                $mapped = [];
                foreach ($items as $item) {
                    $mapped[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'name_ar' => $item->name_ar,
                        'price' => (float) $item->price,
                        'is_default' => (bool) $item->is_default,
                        'sort_order' => (int) $item->sort_order,
                        'allow_quantity' => (bool) ($item->allow_quantity ?? false),
                    ];
                }

                return $mapped;
            }),
        ];
    }
}
