<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Models;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GuestUser extends Model implements GuestCustomer
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_country_code',
        'street',
        'building',
        'floor',
        'apartment',
        'city',
        'area_id',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customerId(): int|string
    {
        return $this->getKey();
    }

    public function customerName(): ?string
    {
        $name = $this->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function customerPhone(): ?string
    {
        $phone = $this->getAttribute('phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public function customerEmail(): ?string
    {
        $email = $this->getAttribute('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function isGuest(): bool
    {
        return true;
    }

    public function deliveryAreaId(): ?int
    {
        $areaId = $this->getAttribute('area_id');

        return $areaId !== null ? (int) $areaId : null;
    }

    /**
     * phone_number is the international number (country code + phone), as payment gateways expect.
     *
     * @return array{street: ?string, building: ?string, floor: ?string, apartment: ?string, city: ?string, area_id: ?int, phone_number: ?string}
     */
    public function customerAddress(): array
    {
        $areaId = $this->getAttribute('area_id');

        return [
            'street' => $this->getAttribute('street'),
            'building' => $this->getAttribute('building'),
            'floor' => $this->getAttribute('floor'),
            'apartment' => $this->getAttribute('apartment'),
            'city' => $this->getAttribute('city'),
            'area_id' => $areaId !== null ? (int) $areaId : null,
            'phone_number' => $this->customerPhone() !== null ? $this->full_phone : null,
        ];
    }

    protected static function newFactory()
    {
        return \Database\Factories\GuestUserFactory::new();
    }

    /**
     * Get the full phone number with country code
     */
    protected function fullPhone(): Attribute
    {
        return Attribute::make(
            get: fn (): string => str_starts_with((string) $this->phone, '+')
                ? (string) $this->phone
                : ($this->phone_country_code ?? '+20').$this->phone,
        );
    }
}
