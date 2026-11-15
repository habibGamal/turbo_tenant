<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $user_id
 * @property int $area_id
 * @property string $phone_number
 * @property string|null $street
 * @property string|null $building
 * @property string|null $floor
 * @property string|null $apartment
 * @property string|null $full_address
 * @property string|null $notes
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'area_id' => $this->area_id,
            'phone_number' => $this->phone_number,
            'street' => $this->street,
            'building' => $this->building,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'full_address' => $this->full_address,
            'notes' => $this->notes,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
            'area' => new AreaResource($this->whenLoaded('area')),
        ];
    }
}
