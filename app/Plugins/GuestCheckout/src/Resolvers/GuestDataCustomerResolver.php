<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Resolvers;

use App\Core\Contracts\Customer;
use App\Plugins\GuestCheckout\src\Services\GuestUserService;
use App\Plugins\OrderManagement\src\Contracts\GuestCustomerResolver;
use Illuminate\Http\Request;

final class GuestDataCustomerResolver implements GuestCustomerResolver
{
    public function __construct(
        private readonly GuestUserService $guestUserService
    ) {}

    public function pluginId(): string
    {
        return 'guest-checkout';
    }

    public function resolve(Request $request): ?Customer
    {
        $guestData = $request->input('guest_data');

        if (! is_array($guestData) || empty($guestData)) {
            return null;
        }

        return $this->guestUserService->findOrCreate($guestData);
    }
}
