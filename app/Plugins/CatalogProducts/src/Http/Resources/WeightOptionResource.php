<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Plugins\CatalogProducts\src\Models\WeightOption
 *
 * @property \App\Plugins\CatalogProducts\src\Models\WeightOption $resource
 */
final class WeightOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'values' => $this->whenLoaded('values', function (): array {
                /** @var iterable<object> $values */
                $values = $this->values;
                $mapped = [];
                foreach ($values as $val) {
                    $mapped[] = [
                        'id' => $val->id,
                        'value' => (float) $val->value,
                        'label' => $val->label,
                        'sort_order' => (int) $val->sort_order,
                    ];
                }

                return $mapped;
            }),
        ];
    }
}
