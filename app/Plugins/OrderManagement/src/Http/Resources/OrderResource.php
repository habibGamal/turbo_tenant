<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Resources;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 *
 * @property Order $resource
 */
final class OrderResource extends JsonResource
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
            'note' => $this->resource->note,
            'customer_name' => $this->resource->customer_name,
            'customer_phone' => $this->resource->customer_phone,
            'customer_email' => $this->resource->customer_email,
            'delivery_address' => $this->resource->delivery_address,
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
                    'phone' => $branch->phone ?? $branch->phone_number ?? $branch->contact_phone ?? null,
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
            'user' => $this->whenLoaded('user', function () {
                /** @var User|null $user */
                $user = $this->resource->relationLoaded('user') ? $this->resource->user : null;
                if ($user === null) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }),
            'guest_user' => $this->whenLoaded('guestUser', function () {
                $guestUser = $this->resource->relationLoaded('guestUser') ? $this->resource->getRelation('guestUser') : null;
                if (! $guestUser instanceof Model) {
                    return null;
                }

                $area = $guestUser->relationLoaded('area') ? $guestUser->getRelation('area') : null;

                return [
                    'id' => $guestUser->getAttribute('id'),
                    'name' => $guestUser->getAttribute('name'),
                    'phone' => $guestUser->getAttribute('phone'),
                    'phone_country_code' => $guestUser->getAttribute('phone_country_code'),
                    'full_phone' => $guestUser->getAttribute('full_phone') ?? ($guestUser->getAttribute('phone_country_code').$guestUser->getAttribute('phone')),
                    'street' => $guestUser->getAttribute('street'),
                    'building' => $guestUser->getAttribute('building'),
                    'floor' => $guestUser->getAttribute('floor'),
                    'apartment' => $guestUser->getAttribute('apartment'),
                    'city' => $guestUser->getAttribute('city'),
                    'area' => $area instanceof Model ? [
                        'id' => $area->getAttribute('id'),
                        'name' => $area->getAttribute('name'),
                        'name_ar' => $area->getAttribute('name_ar'),
                    ] : null,
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
