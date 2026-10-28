<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Resolvers;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Models\User;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use Illuminate\Support\Facades\Session;

final class CartContextResolver implements CartContextResolverInterface
{
    public function getGuestCartIdentifier(): string
    {
        if (! Session::has('guest_cart_id')) {
            Session::put('guest_cart_id', 'guest_'.uniqid());
        }

        return (string) Session::get('guest_cart_id');
    }

    public function resolveCart(User|GuestCustomer|null $user): ?Cart
    {
        if ($user instanceof User) {
            return Cart::where('user_id', $user->id)->first();
        }

        $sessionId = $this->getGuestCartIdentifier();

        return Cart::where('session_id', $sessionId)->first();
    }

    public function resolveOrCreateCart(User|GuestCustomer|null $user): Cart
    {
        if ($user instanceof User) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['last_activity_at' => now()]
            );
        }

        $sessionId = $this->getGuestCartIdentifier();

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['last_activity_at' => now()]
        );
    }

    public function clearGuestSession(): void
    {
        Session::forget('guest_cart_id');
    }
}
