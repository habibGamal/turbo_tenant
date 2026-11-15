<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use App\Core\Contracts\Customer;

interface GuestCustomer extends Customer
{
    /** @return mixed */
    public function getKey();
}
