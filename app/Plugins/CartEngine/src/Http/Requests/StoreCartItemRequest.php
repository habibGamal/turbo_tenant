<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Requests;

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
        $hasAssociativeExtras = false;
        if ($this->has('extras') && is_array($this->extras)) {
            foreach ($this->extras as $extra) {
                if (is_array($extra)) {
                    $hasAssociativeExtras = true;
                    break;
                }
            }
        }

        $rules = [
            'product_id' => ['required', 'integer', 'exists:products,id,is_active,1'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'weight_option_value_id' => ['nullable', 'integer', 'exists:weight_option_values,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999.999'],
            'weight_multiplier' => ['nullable', 'integer', 'min:1', 'max:99'],
            'extras' => ['nullable', 'array'],
        ];

        if ($hasAssociativeExtras) {
            $rules['extras.*.id'] = ['required', 'integer', 'exists:extra_option_items,id'];
            $rules['extras.*.quantity'] = ['nullable', 'integer', 'min:1', 'max:999'];
        } else {
            $rules['extras.*'] = ['integer', 'exists:extra_option_items,id'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(\App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface::class)->validateOptions(
                    (int) $this->input('product_id'),
                    $this->filled('variant_id') ? (int) $this->input('variant_id') : null,
                    $this->filled('weight_option_value_id') ? (int) $this->input('weight_option_value_id') : null,
                    (array) $this->input('extras', [])
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
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
            'extras.*.exists' => 'One or more selected extras do not exist.',
            'extras.*.quantity.min' => 'Extra quantity must be at least 1.',
            'extras.*.quantity.max' => 'Extra quantity cannot exceed 999.',
        ];
    }
}
