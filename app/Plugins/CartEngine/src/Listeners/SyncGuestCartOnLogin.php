<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Listeners;

use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use Illuminate\Auth\Events\Login;
use Throwable;

final class SyncGuestCartOnLogin
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
    ) {}

    public function handle(Login $event): void
    {
        if (! ($event->user instanceof User)) {
            return;
        }

        try {
            $this->cartService->syncGuestCartToUser($event->user);
        } catch (Throwable $e) {
            logger()->error('Cart sync error on login: '.$e->getMessage());
        }
    }
}
