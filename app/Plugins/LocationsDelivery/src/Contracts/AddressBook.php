<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Contracts;

interface AddressBook
{
    /**
     * Snapshot stored on orders: the address row's attributes + area_name + governorate_name. Null when missing.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotAddress(int $addressId): ?array;

    /**
     * @return array{area_name?: string, governorate_name?: string}
     */
    public function areaLabels(int $areaId): array;
}
