<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CouponService
{
    /**
     * Validate if a coupon can be applied to an order
     */
    public function validateCoupon(
        Coupon $coupon,
        User $user,
        array $cartItems,
        float $subTotal,
        ?int $addressId = null,
        ?int $areaId = null,
        ?int $governorateId = null
    ): array {
        // Check if coupon is active
        if (!$coupon->is_active) {
            return [
                'valid' => false,
                'message' => 'This coupon is not active',
            ];
        }

        // Check if coupon has expired
        if ($coupon->expiry_date && Carbon::parse($coupon->expiry_date)->isPast()) {
            return [
                'valid' => false,
                'message' => 'This coupon has expired',
            ];
        }

        // Check max usage limit
        if ($coupon->max_usage !== null && $coupon->usage_count >= $coupon->max_usage) {
            return [
                'valid' => false,
                'message' => 'This coupon has reached its maximum usage limit',
            ];
        }

        $conditions = $coupon->conditions;

        // Validate minimum order total
        if (isset($conditions['min_order_total']) && $conditions['min_order_total'] !== null) {
            if ($subTotal < $conditions['min_order_total']) {
                return [
                    'valid' => false,
                    'message' => "Minimum order total of {$conditions['min_order_total']} required",
                ];
            }
        }

        // Validate maximum order total
        if (isset($conditions['max_order_total']) && $conditions['max_order_total'] !== null) {
            if ($subTotal > $conditions['max_order_total']) {
                return [
                    'valid' => false,
                    'message' => "Maximum order total of {$conditions['max_order_total']} exceeded",
                ];
            }
        }

        // Validate applicable products/categories
        if (!$this->validateApplicableItems($coupon, $cartItems)) {
            return [
                'valid' => false,
                'message' => 'This coupon is not applicable to items in your cart',
            ];
        }

        // Validate governorate/area restrictions
        if (!$this->validateShippingLocation($coupon, $governorateId, $areaId)) {
            return [
                'valid' => false,
                'message' => 'This coupon is not available for your delivery location',
            ];
        }

        // Validate user restrictions
        if (!$this->validateUserRestrictions($coupon, $user)) {
            return [
                'valid' => false,
                'message' => 'This coupon is not available for your account',
            ];
        }

        // Validate time restrictions (days and hours)
        if (!$this->validateTimeRestrictions($coupon)) {
            return [
                'valid' => false,
                'message' => 'This coupon is not valid at this time',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Coupon is valid',
        ];
    }

    /**
     * Calculate discount amount based on coupon and order details
     */
    public function calculateDiscount(
        Coupon $coupon,
        array $cartItems,
        float $subTotal
    ): float {
        $conditions = $coupon->conditions;
        $applicableAmount = $subTotal;

        // If coupon is for specific products/categories, calculate applicable amount only
        if (isset($conditions['applicable_to']['type']) && $conditions['applicable_to']['type'] !== 'all') {
            $applicableAmount = $this->calculateApplicableAmount($coupon, $cartItems);
        }

        // Calculate discount based on type
        if ($coupon->type === 'percentage') {
            $discount = ($applicableAmount * $coupon->value) / 100;
        } else {
            // Fixed amount
            $discount = min($coupon->value, $applicableAmount);
        }

        return round($discount, 2);
    }

    /**
     * Calculate shipping fee with coupon considerations
     */
    public function calculateShippingFee(
        ?Coupon $coupon,
        float $originalShippingFee,
        float $subTotal,
        float $discount
    ): float {
        if (!$coupon) {
            return $originalShippingFee;
        }

        $conditions = $coupon->conditions;

        // Check if free shipping is enabled
        if (isset($conditions['shipping']['free_shipping']) && $conditions['shipping']['free_shipping']) {
            // Check if there's a threshold
            if (isset($conditions['shipping']['free_shipping_threshold']) && $conditions['shipping']['free_shipping_threshold'] !== null) {
                $orderTotal = $subTotal - $discount;
                if ($orderTotal >= $conditions['shipping']['free_shipping_threshold']) {
                    return 0;
                }
            } else {
                // No threshold, free shipping applies
                return 0;
            }
        }

        return $originalShippingFee;
    }

    /**
     * Apply coupon to order (increment usage)
     */
    public function applyCoupon(Coupon $coupon, float $discountAmount): void
    {
        $coupon->increment('usage_count');
        $coupon->increment('total_consumed', $discountAmount);
    }

    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Validate if coupon applies to items in cart
     */
    private function validateApplicableItems(Coupon $coupon, array $cartItems): bool
    {
        $conditions = $coupon->conditions;
        $applicableType = $conditions['applicable_to']['type'] ?? 'all';

        if ($applicableType === 'all') {
            return true;
        }

        if ($applicableType === 'products') {
            $productIds = $conditions['applicable_to']['product_ids'] ?? [];
            if (empty($productIds)) {
                return true;
            }

            // Check if any cart item has a product in the allowed list
            foreach ($cartItems as $item) {
                if (in_array($item['product_id'], $productIds)) {
                    return true;
                }
            }
            return false;
        }

        if ($applicableType === 'categories') {
            $categoryIds = $conditions['applicable_to']['category_ids'] ?? [];
            if (empty($categoryIds)) {
                return true;
            }

            // Check if any cart item's product belongs to allowed categories
            foreach ($cartItems as $item) {
                if (isset($item['product']['category_id']) && in_array($item['product']['category_id'], $categoryIds)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Calculate the amount that the coupon applies to
     */
    private function calculateApplicableAmount(Coupon $coupon, array $cartItems): float
    {
        $conditions = $coupon->conditions;
        $applicableType = $conditions['applicable_to']['type'] ?? 'all';
        $amount = 0;

        if ($applicableType === 'products') {
            $productIds = $conditions['applicable_to']['product_ids'] ?? [];
            foreach ($cartItems as $item) {
                if (in_array($item['product_id'], $productIds)) {
                    $amount += $item['subtotal'] ?? 0;
                }
            }
        } elseif ($applicableType === 'categories') {
            $categoryIds = $conditions['applicable_to']['category_ids'] ?? [];
            foreach ($cartItems as $item) {
                if (isset($item['product']['category_id']) && in_array($item['product']['category_id'], $categoryIds)) {
                    $amount += $item['subtotal'] ?? 0;
                }
            }
        }

        return $amount;
    }

    /**
     * Validate shipping location restrictions
     */
    private function validateShippingLocation(Coupon $coupon, ?int $governorateId, ?int $areaId): bool
    {
        $conditions = $coupon->conditions;

        // Check governorate restrictions
        $applicableGovernorates = $conditions['shipping']['applicable_governorates'] ?? [];
        if (!empty($applicableGovernorates) && $governorateId !== null) {
            if (!in_array($governorateId, $applicableGovernorates)) {
                return false;
            }
        }

        // Check area restrictions
        $applicableAreas = $conditions['shipping']['applicable_areas'] ?? [];
        if (!empty($applicableAreas) && $areaId !== null) {
            if (!in_array($areaId, $applicableAreas)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate user restrictions
     */
    private function validateUserRestrictions(Coupon $coupon, User $user): bool
    {
        $conditions = $coupon->conditions;
        $restrictions = $conditions['usage_restrictions'] ?? [];

        // Check if coupon is for first order only
        if (isset($restrictions['first_order_only']) && $restrictions['first_order_only']) {
            $orderCount = Order::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'completed', 'delivered'])
                ->count();

            if ($orderCount > 0) {
                return false;
            }
        }

        // Check if coupon is for specific users
        if (isset($restrictions['user_specific']) && $restrictions['user_specific']) {
            $userIds = $restrictions['user_ids'] ?? [];
            if (!empty($userIds) && !in_array($user->id, $userIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate time restrictions (days and hours)
     */
    private function validateTimeRestrictions(Coupon $coupon): bool
    {
        $conditions = $coupon->conditions;
        $now = Carbon::now();

        // Check valid days (0 = Sunday, 6 = Saturday)
        $validDays = $conditions['valid_days'] ?? null;
        if ($validDays !== null && is_array($validDays) && !empty($validDays)) {
            if (!in_array($now->dayOfWeek, $validDays)) {
                return false;
            }
        }

        // Check valid hours
        $validHours = $conditions['valid_hours'] ?? [];
        $startTime = $validHours['start'] ?? null;
        $endTime = $validHours['end'] ?? null;

        if ($startTime !== null && $endTime !== null) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $startTime || $currentTime > $endTime) {
                return false;
            }
        }

        return true;
    }
}
