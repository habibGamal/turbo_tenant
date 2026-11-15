<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Requests;

use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

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
        if ($this->has('type') && $this->input('type') !== 'product') {
            return [
                'type' => ['required', 'string', 'max:32'],
                'quantity' => ['required', 'integer', 'min:1', 'max:99'],
                'notes' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'type' => ['nullable', 'string', 'in:product'],
            'product_id' => ['required', 'integer', 'exists:products,id,is_active,1'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'weight_option_value_id' => ['nullable', 'integer', 'exists:weight_option_values,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999.999'],
            'weight_multiplier' => ['nullable', 'integer', 'min:1', 'max:99'],
            'extras' => ['nullable', 'array'],
            'extras.*.id' => ['required', 'integer', 'exists:extra_option_items,id'],
            'extras.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(ProductValidatorInterface $productValidator): array
    {
        return [
            function (Validator $formValidator) use ($productValidator): void {
                if ($this->has('type') && $this->input('type') !== 'product') {
                    return;
                }

                if ($formValidator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $productValidator->validateOptions(
                        $this->filled('product_id') ? (int) $this->input('product_id') : null,
                        $this->filled('variant_id') ? (int) $this->input('variant_id') : null,
                        $this->filled('weight_option_value_id') ? (int) $this->input('weight_option_value_id') : null,
                        (array) $this->input('extras', [])
                    );
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $formValidator->errors()->add($field, $message);
                        }
                    }
                }
            },
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
            'extras.*.exists' => 'One or more selected extras do not exist.',
            'extras.*.quantity.min' => 'Extra quantity must be at least 1.',
            'extras.*.quantity.max' => 'Extra quantity cannot exceed 999.',
        ];
    }

    /**
     * The storefront may send extras as plain ids or as {id, quantity}. Everything after this
     * point works with list<array{id: int, quantity: int}>.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('type') && $this->input('type') !== 'product') {
            return;
        }

        if (! is_array($this->input('extras'))) {
            return;
        }

        $this->merge(['extras' => array_map(
            static fn (mixed $extra): array => is_array($extra)
                ? ['id' => self::toInt($extra['id'] ?? null), 'quantity' => self::toInt($extra['quantity'] ?? 1)]
                : ['id' => self::toInt($extra), 'quantity' => 1],
            array_values($this->input('extras')),
        )]);
    }

    /**
     * Casts numeric input to int and leaves anything else for the validation rules to reject.
     */
    private static function toInt(mixed $value): mixed
    {
        return is_numeric($value) ? (int) $value : $value;
    }
}
