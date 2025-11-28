<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OrderStatusUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
     * Get custom messages for validator errors.
     *
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
