<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Resources;

use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int $extra_option_item_id
 * @property string $extra_name
 * @property string|null $extra_name_ar
 * @property float $extra_price
 * @property int $quantity
 * @property OrderItemExtra $resource
 */
final class OrderItemExtraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'extra_option_item_id' => $this->extra_option_item_id,
            'extra_name' => $this->extra_name,
            'extra_name_ar' => $this->extra_name_ar,
            'extra_price' => $this->extra_price,
            'quantity' => $this->quantity,
        ];
    }
}
