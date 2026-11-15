<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Policies;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;

final class AddressPolicy
{
    public function update(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->getKey();
    }

    public function delete(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->getKey();
    }
}
