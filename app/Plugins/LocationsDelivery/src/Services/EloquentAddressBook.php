<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Services;

use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;

final class EloquentAddressBook implements AddressBook
{
    /**
     * Snapshot stored on orders: the address row's attributes + area_name + governorate_name. Null when missing.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotAddress(int $addressId): ?array
    {
        if ($addressId <= 0) {
            return null;
        }

        $address = Address::with(['area.governorate'])->find($addressId);
        if (! $address) {
            return null;
        }

        $snapshot = $address->toArray();
        $area = $address->area;
        if ($area instanceof Area) {
            $snapshot['area_name'] = $area->name;
            $governorate = $area->governorate;
            if ($governorate instanceof Governorate) {
                $snapshot['governorate_name'] = $governorate->name;
            }
        }

        return $snapshot;
    }

    /**
     * @return array{area_name?: string, governorate_name?: string}
     */
    public function areaLabels(int $areaId): array
    {
        if ($areaId <= 0) {
            return [];
        }

        $area = Area::with('governorate')->find($areaId);
        if (! $area instanceof Area) {
            return [];
        }

        $labels = [];
        if (! empty($area->name)) {
            $labels['area_name'] = (string) $area->name;
        }
        $governorate = $area->governorate;
        if ($governorate instanceof Governorate && ! empty($governorate->name)) {
            $labels['governorate_name'] = (string) $governorate->name;
        }

        return $labels;
    }
}
