<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Resources;

use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 *
 * @property Order $resource
 */
final class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'status' => $this->resource->status instanceof BackedEnum ? $this->resource->status->value : (string) $this->resource->status,
            'payment_status' => $this->resource->payment_status instanceof BackedEnum ? $this->resource->payment_status->value : (string) $this->resource->payment_status,
            'payment_method' => $this->resource->payment_method instanceof BackedEnum ? $this->resource->payment_method->value : (string) $this->resource->payment_method,
            'type' => $this->resource->type instanceof BackedEnum ? $this->resource->type->value : (string) $this->resource->type,
            'sub_total' => $this->resource->sub_total,
            'tax' => $this->resource->tax,
            'service' => $this->resource->service,
            'delivery_fee' => $this->resource->delivery_fee,
            'discount' => $this->resource->discount,
            'total' => $this->resource->total,
            'created_at' => $this->resource->created_at?->toISOString() ?? (string) $this->resource->created_at,
            'updated_at' => $this->resource->updated_at?->toISOString() ?? (string) $this->resource->updated_at,
            'branch' => $this->whenLoaded('branch', function () {
                /** @var Branch|null $branch */
                $branch = $this->resource->relationLoaded('branch') ? $this->resource->branch : null;
                if ($branch === null) {
                    return null;
                }

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ];
            }),
            'address' => $this->whenLoaded('address', function () {
                /** @var Address|null $address */
                $address = $this->resource->relationLoaded('address') ? $this->resource->address : null;
                if ($address === null) {
                    return null;
                }

                /** @var Area|null $area */
                $area = $address->relationLoaded('area') ? $address->area : null;

                return [
                    'id' => $address->id,
                    'street' => $address->street,
                    'building' => $address->building,
                    'floor' => $address->floor,
                    'apartment' => $address->apartment,
                    'area' => $area !== null ? [
                        'id' => $area->id,
                        'name' => $area->name,
                    ] : null,
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
