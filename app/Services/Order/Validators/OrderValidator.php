<?php

declare(strict_types=1);

namespace App\Services\Order\Validators;

use App\DTOs\Order\PlaceOrderData;
use App\Enums\SettingKey;
use App\Exceptions\OrderValidationException;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\Order\Contracts\OrderValidatorInterface;
use App\Services\SettingService;

final readonly class OrderValidator implements OrderValidatorInterface
{
    public function __construct(
        private SettingService $settingService,
        private CouponService $couponService
    ) {}

    /**
     * @param  array<string, mixed>  $cart
     *
     * @throws OrderValidationException
     */
    public function validate(PlaceOrderData $data, array $cart): ?Coupon
    {
        $items = $cart['items'] ?? [];

        if (empty($items)) {
            throw OrderValidationException::withMessage('Cart is empty');
        }

        $inactiveNames = $this->getInactiveProductNames($items);
        if (! empty($inactiveNames)) {
            throw OrderValidationException::withMessage('Some products are unavailable: '.implode(', ', $inactiveNames));
        }

        if (! $this->isStoreOpen()) {
            $acceptAfterHours = filter_var(
                $this->settingService->get(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            if (! $acceptAfterHours) {
                throw OrderValidationException::withMessage('We are currently closed. Please try again during our work hours.');
            }
        }

        return $this->validateCoupon($data, $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function getInactiveProductNames(array $items): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', false)
            ->pluck('name')
            ->all();
    }

    private function isStoreOpen(): bool
    {
        $workTimesJson = $this->settingService->get(SettingKey::WORK_TIMES, '[]');
        $workTimes = json_decode($workTimesJson, true) ?? [];

        if (empty($workTimes)) {
            return true;
        }

        $now = now();
        $currentDay = $now->format('l');
        $currentTime = $now->format('H:i');

        foreach ($workTimes as $daySchedule) {
            if (($daySchedule['day'] ?? '') === $currentDay) {
                if (! empty($daySchedule['closed'])) {
                    return false;
                }

                $from = $daySchedule['from'] ?? '00:00';
                $to = $daySchedule['to'] ?? '23:59';

                return $currentTime >= $from && $currentTime <= $to;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws OrderValidationException
     */
    private function validateCoupon(PlaceOrderData $data, array $items): ?Coupon
    {
        if (! $data->couponId) {
            return null;
        }

        $coupon = Coupon::find($data->couponId);
        if (! $coupon) {
            throw OrderValidationException::withMessage('Invalid coupon');
        }

        $address = $data->addressId ? Address::with('area.governorate')->find($data->addressId) : null;
        $areaId = $address?->area_id;
        $governorateId = $address?->area?->governorate_id;

        $subTotal = array_reduce($items, fn ($total, $item): float => $total + (float) ($item['subtotal'] ?? 0), 0.0);

        $validation = $this->couponService->validateCoupon(
            $coupon,
            $data->user,
            $items,
            $subTotal,
            $data->addressId,
            $areaId,
            $governorateId
        );

        if (! $validation['valid']) {
            throw OrderValidationException::withMessage((string) $validation['message']);
        }

        return $coupon;
    }
}
