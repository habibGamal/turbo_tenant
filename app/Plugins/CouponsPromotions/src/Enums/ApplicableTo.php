<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Enums;

use App\Plugins\CartEngine\src\DTOs\CartLine;

enum ApplicableTo: string
{
    case ALL = 'all';
    case PRODUCTS = 'products';
    case CATEGORIES = 'categories';
    case LINE_TYPES = 'line_types';

    /**
     * @param  array<string, mixed>  $conditions
     */
    public static function fromConditions(array $conditions): self
    {
        return self::tryFrom((string) ($conditions['applicable_to']['type'] ?? self::ALL->value)) ?? self::ALL;
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    public function matches(CartLine $line, array $conditions): bool
    {
        if (! $line->couponEligible) {
            return false;
        }

        return match ($this) {
            self::ALL => true,
            self::PRODUCTS => ($line->type ?? 'product') === 'product'
                && $line->productId !== null
                && in_array($line->productId, array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $this->ids($conditions)), true),
            self::CATEGORIES => ($line->type ?? 'product') === 'product'
                && $line->categoryId !== null
                && in_array($line->categoryId, array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $this->ids($conditions)), true),
            self::LINE_TYPES => $this->matchesLineType($line, $conditions),
        };
    }

    /**
     * Whether the coupon may be used with this cart. An empty id list means "no restriction".
     *
     * @param  list<CartLine>  $cartLines
     * @param  array<string, mixed>  $conditions
     */
    public function appliesTo(array $cartLines, array $conditions): bool
    {
        if ($this === self::ALL) {
            if ($cartLines === []) {
                return true;
            }

            foreach ($cartLines as $line) {
                if ($this->matches($line, $conditions)) {
                    return true;
                }
            }

            return false;
        }

        if ($this === self::LINE_TYPES) {
            foreach ($cartLines as $line) {
                if ($this->matches($line, $conditions)) {
                    return true;
                }
            }

            return false;
        }

        if ($this->ids($conditions) === []) {
            return true;
        }

        foreach ($cartLines as $line) {
            if ($this->matches($line, $conditions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The part of the subtotal the discount is computed on.
     *
     * @param  list<CartLine>  $cartLines
     * @param  array<string, mixed>  $conditions
     */
    public function sumApplicableSubtotal(array $cartLines, array $conditions, float $subTotal): float
    {
        if ($cartLines === []) {
            return $this === self::ALL ? $subTotal : 0.0;
        }

        $amount = 0.0;
        foreach ($cartLines as $line) {
            if ($this->matches($line, $conditions)) {
                $amount += $line->subtotal;
            }
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return array<int, mixed>
     */
    public function ids(array $conditions): array
    {
        return match ($this) {
            self::ALL => [],
            self::PRODUCTS => (array) ($conditions['applicable_to']['product_ids'] ?? []),
            self::CATEGORIES => (array) ($conditions['applicable_to']['category_ids'] ?? []),
            self::LINE_TYPES => (array) ($conditions['applicable_to']['ids'] ?? []),
        };
    }

    /**
     * Target line type for LINE_TYPES coupons (defaults to 'package').
     *
     * @param  array<string, mixed>  $conditions
     */
    public function lineType(array $conditions): string
    {
        return (string) ($conditions['applicable_to']['line_type'] ?? 'package');
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function matchesLineType(CartLine $line, array $conditions): bool
    {
        $targetLineType = $this->lineType($conditions);
        if ($line->type !== $targetLineType) {
            return false;
        }

        $ids = $this->ids($conditions);
        if ($ids === []) {
            return true;
        }

        return $line->referenceId !== null
            && in_array($line->referenceId, array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $ids), true);
    }
}
