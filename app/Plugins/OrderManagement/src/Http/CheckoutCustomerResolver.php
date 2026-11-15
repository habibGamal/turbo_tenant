<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http;

use App\Core\Contracts\Customer;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\OrderManagement\src\Contracts\GuestCustomerResolver;
use Illuminate\Http\Request;

final class CheckoutCustomerResolver
{
    public function __construct(
        private readonly PluginContributions $contributions,
    ) {}

    public function resolve(Request $request): ?Customer
    {
        $authUser = $request->user();
        if ($authUser instanceof Customer) {
            return $authUser;
        }

        /** @var list<GuestCustomerResolver> $resolvers */
        $resolvers = $this->contributions->active('order.customer.resolvers');
        foreach ($resolvers as $resolver) {
            $resolved = $resolver->resolve($request);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
