<?php

declare(strict_types=1);

namespace App\Core\Contracts;

interface Customer
{
    public function customerId(): int|string;

    public function customerName(): ?string;

    public function customerPhone(): ?string;

    public function customerEmail(): ?string;

    public function isGuest(): bool;

    public function deliveryAreaId(): ?int;

    /**
     * @return array{street?: ?string, building?: ?string, floor?: ?string, apartment?: ?string, city?: ?string, area_id?: ?int, phone_number?: ?string}
     */
    public function customerAddress(): array;
}
