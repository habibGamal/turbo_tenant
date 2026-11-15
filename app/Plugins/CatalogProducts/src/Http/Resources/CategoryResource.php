<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Plugins\CatalogProducts\src\Models\Category
 */
final class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
        ];

        if (array_key_exists('description', $this->resource->getAttributes())) {
            $data['description'] = $this->description;
        }

        if (array_key_exists('image', $this->resource->getAttributes())) {
            $data['image'] = $this->image;
        }

        if (array_key_exists('sort_order', $this->resource->getAttributes())) {
            $data['sort_order'] = $this->sort_order;
        }

        return $data;
    }
}
