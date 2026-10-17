<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\OrderManagement\src\Models\Order;

final class POSCustomerDataFormatter
{
    /**
     * Format customer data for the POS payload.
     *
     * @return array{name: string, phone: string, area: string, address: string}
     */
    public function format(Order $order): array
    {
        if ($order->user) {
            return $this->formatRegularCustomer($order->user, $order->address);
        }

        if ($order->guestUser) {
            return $this->formatGuestCustomer($order->guestUser);
        }

        return [
            'name' => 'N/A',
            'phone' => 'N/A',
            'area' => 'N/A',
            'address' => 'N/A',
        ];
    }

    /**
     * Format regular registered user data.
     *
     * @return array{name: string, phone: string, area: string, address: string}
     */
    private function formatRegularCustomer(User $user, ?Address $address): array
    {
        $phone = (! empty($address?->phone_number))
            ? $address->phone_number
            : ((! empty($user->phone)) ? $user->phone : 'N/A');

        return [
            'name' => $user->name,
            'phone' => $phone,
            'area' => $address?->area?->name ?? 'N/A',
            'address' => $address ? $this->formatAddress($address) : 'N/A',
        ];
    }

    /**
     * Format guest customer data.
     *
     * @return array{name: string, phone: string, area: string, address: string}
     */
    private function formatGuestCustomer(object $guestUser): array
    {
        $phone = $guestUser->full_phone ?: $guestUser->phone;

        return [
            'name' => $guestUser->name,
            'phone' => $phone ?: 'N/A',
            'area' => $guestUser->area?->name ?? 'N/A',
            'address' => $this->formatGuestAddress($guestUser),
        ];
    }

    /**
     * Format address parts for registered user address.
     */
    private function formatAddress(Address $address): string
    {
        if (! empty($address->full_address)) {
            return $address->full_address;
        }

        $parts = [];

        if ($address->street) {
            $parts[] = $address->street;
        }

        if ($address->building) {
            $parts[] = 'Building '.$address->building;
        }

        if ($address->floor) {
            $parts[] = 'Floor '.$address->floor;
        }

        if ($address->apartment) {
            $parts[] = 'Apartment '.$address->apartment;
        }

        if ($address->area) {
            $parts[] = $address->area->name;
        }

        if ($address->area?->governorate) {
            $parts[] = $address->area->governorate->name;
        }

        $formatted = implode(', ', $parts);

        return $formatted !== '' ? $formatted : 'N/A';
    }

    /**
     * Format address parts for guest user address.
     */
    private function formatGuestAddress(object $guestUser): string
    {
        $parts = [];

        if ($guestUser->street) {
            $parts[] = $guestUser->street;
        }

        if ($guestUser->building) {
            $parts[] = 'Building '.$guestUser->building;
        }

        if ($guestUser->floor) {
            $parts[] = 'Floor '.$guestUser->floor;
        }

        if ($guestUser->apartment) {
            $parts[] = 'Apartment '.$guestUser->apartment;
        }

        if ($guestUser->city) {
            $parts[] = $guestUser->city;
        }

        if ($guestUser->area) {
            $parts[] = $guestUser->area->name;
        }

        if ($guestUser->area?->governorate) {
            $parts[] = $guestUser->area->governorate->name;
        }

        $formatted = implode(', ', $parts);

        return $formatted !== '' ? $formatted : 'N/A';
    }
}
