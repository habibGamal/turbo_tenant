<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Resources;

use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class PaymentStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentStatusValue = $this->payment_status instanceof PaymentStatus
            ? $this->payment_status->value
            : (string) ($this->payment_status ?? '');

        $isPaid = $this->payment_status === PaymentStatus::COMPLETED
            || $paymentStatusValue === 'completed'
            || $paymentStatusValue === 'paid';

        return [
            'order' => [
                'id' => $this->id,
                'order_number' => $this->order_number,
                'status' => $this->status instanceof BackedEnum ? $this->status->value : (string) $this->status,
                'payment_status' => $paymentStatusValue,
                'payment_method' => $this->payment_method instanceof BackedEnum ? $this->payment_method->value : (string) $this->payment_method,
                'total' => (float) $this->total,
                'currency' => 'EGP',
            ],
            'is_paid' => $isPaid,
        ];
    }
}
