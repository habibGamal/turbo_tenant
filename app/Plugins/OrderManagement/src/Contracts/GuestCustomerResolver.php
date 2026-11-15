<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Core\Contracts\Customer;
use App\Core\Plugins\Contracts\PluginContribution;
use Illuminate\Http\Request;

/**
 * Resolves a guest customer from the incoming request.
 * Tag: 'order.customer.resolvers'
 */
interface GuestCustomerResolver extends PluginContribution
{
    public function resolve(Request $request): ?Customer;
}
