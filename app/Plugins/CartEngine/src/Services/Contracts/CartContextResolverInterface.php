<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Models\User;
use App\Plugins\CartEngine\src\Models\Cart;

interface CartContextResolverInterface
{
    public function getGuestCartIdentifier(): string;

    public function resolveCart(User|GuestCustomer|null $user): ?Cart;

    public function resolveOrCreateCart(User|GuestCustomer|null $user): Cart;

    public function clearGuestSession(): void;
}
