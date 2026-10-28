<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Requests;

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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->has('weight_option_value_id')) {
                $itemId = $this->route('id') ?? $this->route('item');
                if (! $itemId) {
                    return;
                }

                $cartItem = \App\Plugins\CartEngine\src\Models\CartItem::find((int) $itemId);
                if (! $cartItem) {
                    return;
                }

                $weightValId = $this->filled('weight_option_value_id')
                    ? (int) $this->input('weight_option_value_id')
                    : null;

                try {
                    app(\App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface::class)
                        ->validateUpdateWeightOption((int) $cartItem->product_id, $weightValId);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            }
        });
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
