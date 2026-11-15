<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Requests;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Http\Requests\Concerns\AuthorizesOrderPayment;
use Illuminate\Foundation\Http\FormRequest;

final class KashierConfigRequest extends FormRequest
{
    use AuthorizesOrderPayment;

    public function authorize(): bool
    {
        $order = $this->order();

        if (! $order) {
            abort(404, 'Order not found');
        }

        if (! $this->canAccessOrder($order)) {
            abort(403, 'Unauthorized');
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
        $order = $this->route('order') ?? $this->route('orderId');

        if ($order instanceof Order) {
            return $order;
        }

        return is_numeric($order) ? Order::find((int) $order) : null;
    }
}
