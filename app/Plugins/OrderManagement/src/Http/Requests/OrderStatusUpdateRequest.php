<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orderNumber' => ['required', 'string', 'exists:orders,order_number'],
            'status' => ['required', 'string', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orderNumber.required' => 'رقم الطلب مطلوب',
            'orderNumber.exists' => 'رقم الطلب غير موجود',
            'status.required' => 'حالة الطلب مطلوبة',
            'status.enum' => 'حالة الطلب يجب أن تكون واحدة من: pending, processing, out_for_delivery, completed, cancelled',
        ];
    }
}
