<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;

final class POSCustomerDataFormatter
{
    /**
     * Format customer data for the POS payload using order snapshots only.
     *
     * @return array{name: string, phone: string, area: string, address: string}
     */
    public function format(Order $order): array
    {
        $deliveryAddress = is_array($order->delivery_address) ? $order->delivery_address : null;

        $name = (! empty($order->customer_name))
            ? (string) $order->customer_name
            : null;

        $phone = (! empty($order->customer_phone))
            ? (string) $order->customer_phone
            : (! empty($deliveryAddress['phone_number']) ? (string) $deliveryAddress['phone_number'] : null);

        $deliveryAddress = $deliveryAddress !== null ? $this->withArabicPlaceNames($deliveryAddress) : null;

        $area = (! empty($deliveryAddress['area_name']))
            ? (string) $deliveryAddress['area_name']
            : null;

        $formattedAddress = ! empty($deliveryAddress)
            ? $this->formatDeliveryAddress($deliveryAddress)
            : null;

        return [
            'name' => $name ?? 'N/A',
            'phone' => $phone ?? 'N/A',
            'area' => $area ?? 'N/A',
            'address' => $formattedAddress ?? 'N/A',
        ];
    }

    /**
     * Format address parts from the order's delivery_address snapshot.
     *
     * @param  array<string, mixed>  $address
     */
    private function formatDeliveryAddress(array $address): string
    {
        if (! empty($address['full_address'])) {
            return (string) $address['full_address'];
        }

        $parts = [];

        if (! empty($address['street'])) {
            $parts[] = (string) $address['street'];
        }

        if (! empty($address['building'])) {
            $parts[] = 'عمارة '.$address['building'];
        }

        if (! empty($address['floor'])) {
            $parts[] = 'الدور '.$address['floor'];
        }

        if (! empty($address['apartment'])) {
            $parts[] = 'شقة '.$address['apartment'];
        }

        if (! empty($address['city'])) {
            $parts[] = (string) $address['city'];
        }

        if (! empty($address['area_name'])) {
            $parts[] = (string) $address['area_name'];
        }

        if (! empty($address['governorate_name'])) {
            $parts[] = (string) $address['governorate_name'];
        }

        $formatted = implode('، ', $parts);

        return $formatted !== '' ? $formatted : 'N/A';
    }

    /**
     * The POS UI is Arabic: use the area and governorate Arabic names when the
     * catalog has them (the snapshot stores the default names only).
     *
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function withArabicPlaceNames(array $address): array
    {
        $areaId = (int) ($address['area_id'] ?? 0);
        if ($areaId <= 0) {
            return $address;
        }

        $area = Area::with('governorate')->find($areaId);
        if (! $area instanceof Area) {
            return $address;
        }

        if (filled($area->name_ar)) {
            $address['area_name'] = (string) $area->name_ar;
        }

        $governorate = $area->governorate;
        if ($governorate instanceof Governorate && filled($governorate->name_ar)) {
            $address['governorate_name'] = (string) $governorate->name_ar;
        }

        return $address;
    }
}
