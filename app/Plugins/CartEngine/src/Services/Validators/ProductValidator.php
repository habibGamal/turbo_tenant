<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Validators;

use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Validation\ValidationException;

final class ProductValidator implements ProductValidatorInterface
{
    public function ensureProductIsActive(int $productId): void
    {
        $isActive = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $extras
     */
    public function validateOptions(int $productId, ?int $variantId, ?int $weightOptionValueId, array $extras = []): void
    {
        $product = Product::with([
            'variants',
            'extraOption.items',
            'weightOption.values',
        ])->find($productId);

        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        $errors = [];

        // 1. Variant validation
        $hasVariants = $product->variants->isNotEmpty();
        if ($hasVariants) {
            if ($variantId === null) {
                $errors['variant_id'] = ['Variant is required for this product.'];
            } else {
                $variant = $product->variants->firstWhere('id', $variantId);
                if (! $variant) {
                    $errors['variant_id'] = ['Selected variant does not belong to this product.'];
                } elseif (! $variant->is_available) {
                    $errors['variant_id'] = ['Selected variant is currently unavailable.'];
                }
            }
        } else {
            if ($variantId !== null) {
                $errors['variant_id'] = ['This product does not have variants.'];
            }
        }

        // 2. Weight option validation
        if ($product->sell_by_weight) {
            if ($weightOptionValueId === null) {
                $errors['weight_option_value_id'] = ['Weight option is required for this product.'];
            } else {
                $isValidWeight = $product->weight_options_id
                    && $product->weightOption?->values->contains('id', $weightOptionValueId);
                if (! $isValidWeight) {
                    $errors['weight_option_value_id'] = ['Selected weight option does not belong to this product.'];
                }
            }
        } else {
            if ($weightOptionValueId !== null) {
                $errors['weight_option_value_id'] = ['This product is not sold by weight.'];
            }
        }

        // 3. Extras validation
        if (! empty($extras)) {
            if ($product->extra_option_id === null || ! $product->extraOption) {
                $errors['extras'] = ['This product does not allow extras.'];
            } else {
                $extraOption = $product->extraOption;
                $extraIds = [];
                $normalizedExtras = [];

                foreach ($extras as $extra) {
                    if (is_array($extra)) {
                        $id = (int) ($extra['id'] ?? 0);
                        $qty = (int) ($extra['quantity'] ?? 1);
                    } else {
                        $id = (int) $extra;
                        $qty = 1;
                    }
                    $extraIds[] = $id;
                    $normalizedExtras[] = ['id' => $id, 'quantity' => $qty];
                }

                // Duplicate check (reject duplicates)
                if (count($extraIds) !== count(array_unique($extraIds))) {
                    $errors['extras'] = ['Duplicate extras are not allowed.'];
                } else {
                    $count = count($extraIds);

                    if (! $extraOption->allow_multiple && $count > 1) {
                        $errors['extras'] = ['Multiple extras are not allowed for this product.'];
                    } elseif ($extraOption->min_selections !== null && $extraOption->min_selections > 0 && $count < $extraOption->min_selections) {
                        $errors['extras'] = ["Minimum {$extraOption->min_selections} extra selection(s) required."];
                    } elseif ($extraOption->max_selections !== null && $extraOption->max_selections > 0 && $count > $extraOption->max_selections) {
                        $errors['extras'] = ["Maximum {$extraOption->max_selections} extra selection(s) allowed."];
                    } else {
                        foreach ($normalizedExtras as $item) {
                            $extraItem = $extraOption->items->firstWhere('id', $item['id']);
                            if (! $extraItem) {
                                $errors['extras'] = ['One or more selected extras do not belong to this product.'];
                                break;
                            }
                            if ($item['quantity'] > 1 && ! $extraItem->allow_quantity) {
                                $errors['extras'] = ["Quantity greater than 1 is not allowed for extra '{$extraItem->name}'."];
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            // Check if product requires minimum extras selections
            if ($product->extra_option_id !== null && $product->extraOption) {
                $extraOption = $product->extraOption;
                if ($extraOption->min_selections !== null && $extraOption->min_selections > 0) {
                    $errors['extras'] = ["Minimum {$extraOption->min_selections} extra selection(s) required."];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function validateUpdateWeightOption(int $productId, ?int $weightOptionValueId): void
    {
        $product = Product::with('weightOption.values')->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        if (! $product->sell_by_weight) {
            if ($weightOptionValueId !== null) {
                throw ValidationException::withMessages([
                    'weight_option_value_id' => ['This product is not sold by weight.'],
                ]);
            }
        } else {
            if ($weightOptionValueId === null) {
                throw ValidationException::withMessages([
                    'weight_option_value_id' => ['Weight option is required for this product.'],
                ]);
            }

            $isValidWeight = $product->weight_options_id
                && $product->weightOption?->values->contains('id', $weightOptionValueId);
            if (! $isValidWeight) {
                throw ValidationException::withMessages([
                    'weight_option_value_id' => ['Selected weight option does not belong to this product.'],
                ]);
            }
        }
    }
}
