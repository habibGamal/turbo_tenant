<?php

declare(strict_types=1);

namespace App\Services\Cart\Contracts;

use App\Models\Cart;
use App\Models\GuestUser;
use App\Models\User;

interface CartContextResolverInterface
{
    public function getGuestCartIdentifier(): string;

    public function resolveCart(User|GuestUser|null $user): ?Cart;

    public function resolveOrCreateCart(User|GuestUser|null $user): Cart;

    public function clearGuestSession(): void;
}
