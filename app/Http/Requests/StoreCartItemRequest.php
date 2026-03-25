<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id,is_active,1'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'weight_option_value_id' => ['nullable', 'integer', 'exists:weight_option_values,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999.999'],
            'weight_multiplier' => ['nullable', 'integer', 'min:1', 'max:99'],
            'extras' => ['nullable', 'array'],
            'extras.*.id' => ['required', 'integer', 'exists:extra_option_items,id'],
            'extras.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'Selected product is unavailable.',
            'variant_id.exists' => 'Selected variant does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 0.001.',
            'quantity.max' => 'Quantity cannot exceed 9999.999.',
            'weight_multiplier.min' => 'Weight multiplier must be at least 1.',
            'weight_multiplier.max' => 'Weight multiplier cannot exceed 99.',
            'extras.*.id.required' => 'Extra item ID is required.',
            'extras.*.id.exists' => 'One or more selected extras do not exist.',
            'extras.*.quantity.min' => 'Extra quantity must be at least 1.',
            'extras.*.quantity.max' => 'Extra quantity cannot exceed 999.',
        ];
    }
}
