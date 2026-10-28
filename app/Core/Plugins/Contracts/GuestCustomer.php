<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

interface GuestCustomer
{
    public function getKey(): mixed;
}
