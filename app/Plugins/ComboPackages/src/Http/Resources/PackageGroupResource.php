<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $package_id
 * @property string|null $name
 * @property string|null $name_ar
 * @property string $selection_type
 * @property int|null $max_selections
 * @property int|null $min_selections
 * @property int $sort_order
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Plugins\ComboPackages\src\Models\PackageItem> $items
 */
final class PackageGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $selectionType = (string) $this->selection_type;

        $minSelections = match ($selectionType) {
            'all' => null,
            'choose_one' => 1,
            'choose_multiple' => $this->min_selections !== null ? (int) $this->min_selections : 0,
            default => null,
        };

        $maxSelections = match ($selectionType) {
            'all' => null,
            'choose_one' => 1,
            'choose_multiple' => $this->max_selections !== null
                ? (int) $this->max_selections
                : ($this->relationLoaded('items') ? $this->items->count() : null),
            default => null,
        };

        return [
            'id' => (int) $this->id,
            'package_id' => (int) $this->package_id,
            'name' => $this->name !== null ? (string) $this->name : null,
            'name_ar' => $this->name_ar !== null ? (string) $this->name_ar : null,
            'description' => $this->description !== null ? (string) $this->description : null,
            'description_ar' => $this->description_ar !== null ? (string) $this->description_ar : null,
            'selection_type' => $selectionType,
            'allow_repeats' => (bool) ($this->allow_repeats ?? false),
            'min_selections' => $minSelections,
            'max_selections' => $maxSelections,
            'sort_order' => (int) $this->sort_order,
            'items' => PackageItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
