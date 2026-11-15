<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Http\Requests;

use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use Illuminate\Foundation\Http\FormRequest;

final class ValidateCouponRequest extends FormRequest
{
    public function authorize(AddressBook $addressBook): bool
    {
        $addressId = $this->input('address_id');
        if ($addressId === null) {
            return true;
        }

        $user = $this->user();
        if (! $user) {
            return false;
        }

        $snapshot = $addressBook->snapshotAddress((int) $addressId);
        if (! $snapshot) {
            return false;
        }

        return (int) ($snapshot['user_id'] ?? 0) === (int) $user->getAuthIdentifier();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'address_id' => ['nullable', 'integer'],
            'guest_phone' => ['nullable', 'string'],
        ];
    }
}
