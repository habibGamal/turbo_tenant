<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property float|int|string $shipping_cost
 * @property int $governorate_id
 * @property int|null $branch_id
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'shipping_cost' => (float) $this->shipping_cost,
            'governorate_id' => $this->governorate_id,
            'branch_id' => $this->branch_id,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
        ];
    }
}
