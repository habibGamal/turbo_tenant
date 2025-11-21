<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['nullable', 'numeric', 'min:0.001', 'max:9999.999'],
            'weight_multiplier' => ['nullable', 'integer', 'min:1', 'max:99'],
            'weight_option_value_id' => ['nullable', 'integer', 'exists:weight_option_values,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Quantity must be at least 0.001.',
            'quantity.max' => 'Quantity cannot exceed 9999.999.',
            'weight_multiplier.min' => 'Weight multiplier must be at least 1.',
            'weight_multiplier.max' => 'Weight multiplier cannot exceed 99.',
            'weight_option_value_id.exists' => 'Selected weight option does not exist.',
        ];
    }
}
