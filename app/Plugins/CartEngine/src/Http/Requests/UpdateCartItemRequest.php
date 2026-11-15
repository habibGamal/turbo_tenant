<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Requests;

use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

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
     * @return array<int, callable(Validator): void>
     */
    public function after(ProductValidatorInterface $productValidator): array
    {
        return [
            function (Validator $formValidator) use ($productValidator): void {
                if ($formValidator->errors()->isNotEmpty()) {
                    return;
                }

                $itemId = $this->route('itemId') ?? $this->route('id') ?? $this->route('item');
                if (! $itemId) {
                    return;
                }

                $cartItem = CartItem::find((int) $itemId);
                if (! $cartItem) {
                    return;
                }

                if (! $cartItem->isProductLine()) {
                    if ($this->has('weight_option_value_id') || $this->has('weight_multiplier')) {
                        $formValidator->errors()->add('weight', 'Composite lines do not support weight options.');
                    }

                    if ($this->has('quantity')) {
                        $qty = (string) $this->input('quantity');
                        if (! filter_var($qty, FILTER_VALIDATE_INT) || (int) $qty < 1) {
                            $formValidator->errors()->add('quantity', 'Quantity for composite lines must be an integer of at least 1.');
                        }
                    }

                    return;
                }

                if ($this->has('weight_option_value_id')) {
                    $weightValId = $this->filled('weight_option_value_id')
                        ? (int) $this->input('weight_option_value_id')
                        : null;

                    try {
                        $productValidator->validateUpdateWeightOption((int) $cartItem->product_id, $weightValId);
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $field => $messages) {
                            foreach ($messages as $message) {
                                $formValidator->errors()->add($field, $message);
                            }
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
            'quantity.min' => 'Quantity must be at least 0.001.',
            'quantity.max' => 'Quantity cannot exceed 9999.999.',
            'weight_multiplier.min' => 'Weight multiplier must be at least 1.',
            'weight_multiplier.max' => 'Weight multiplier cannot exceed 99.',
            'weight_option_value_id.exists' => 'Selected weight option does not exist.',
        ];
    }
}
