<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Requests;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Http\Requests\Concerns\AuthorizesOrderPayment;
use Illuminate\Foundation\Http\FormRequest;

final class PaymentStatusRequest extends FormRequest
{
    use AuthorizesOrderPayment;

    public function authorize(): bool
    {
        $order = $this->order();

        if (! $order) {
            abort(404, 'Order not found');
        }

        if (! $this->canAccessOrder($order)) {
            abort(403, 'Unauthorized access to order payment status');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function order(): ?Order
    {
        $order = $this->route('orderId') ?? $this->route('order');

        if ($order instanceof Order) {
            return $order->loadMissing(['items.product', 'user', 'guestUser']);
        }

        return is_numeric($order)
            ? Order::with(['items.product', 'user', 'guestUser'])->find((int) $order)
            : null;
    }
}
