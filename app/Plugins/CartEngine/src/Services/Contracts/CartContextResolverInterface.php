<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Models\User;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\CartEngine\src\Models\Cart;

interface CartContextResolverInterface
{
    public function getGuestCartIdentifier(): string;

    public function resolveCart(User|GuestUser|null $user): ?Cart;

    public function resolveOrCreateCart(User|GuestUser|null $user): Cart;

    public function clearGuestSession(): void;
}
