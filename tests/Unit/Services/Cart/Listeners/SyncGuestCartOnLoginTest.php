<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Listeners;

use App\Models\User;
use App\Plugins\CartEngine\src\Listeners\SyncGuestCartOnLogin;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use Exception;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class SyncGuestCartOnLoginTest extends TestCase
{
    public function test_syncs_guest_cart_when_user_is_user_instance(): void
    {
        $user = new User();
        $guestCart = new Cart();
        $guestCart->setRelation('items', collect([new CartItem()]));
        $userCart = new Cart();

        $contextResolver = $this->createMock(CartContextResolverInterface::class);
        $contextResolver->expects($this->once())
            ->method('getGuestCartIdentifier')
            ->willReturn('session_123');
        $contextResolver->expects($this->once())
            ->method('resolveOrCreateCart')
            ->with($user)
            ->willReturn($userCart);
        $contextResolver->expects($this->once())
            ->method('clearGuestSession');

        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findCartWithRelations')
            ->with('session_id', 'session_123')
            ->willReturn($guestCart);
        $repository->expects($this->once())
            ->method('migrateCart')
            ->with($guestCart, $userCart);

        $cartService = $this->createCartService($repository, $contextResolver);
        $listener = new SyncGuestCartOnLogin($cartService);
        $event = new Login('web', $user, false);

        $listener->handle($event);
    }

    public function test_does_not_sync_when_user_is_not_user_instance(): void
    {
        $nonUser = $this->createMock(Authenticatable::class);
        $contextResolver = $this->createMock(CartContextResolverInterface::class);
        $contextResolver->expects($this->never())
            ->method('getGuestCartIdentifier');

        $cartService = $this->createCartService(contextResolver: $contextResolver);
        $listener = new SyncGuestCartOnLogin($cartService);
        $event = new Login('web', $nonUser, false);

        $listener->handle($event);
    }

    public function test_catches_and_logs_exception_from_cart_service(): void
    {
        $user = new User();

        $contextResolver = $this->createMock(CartContextResolverInterface::class);
        $contextResolver->expects($this->once())
            ->method('getGuestCartIdentifier')
            ->willThrowException(new Exception('Sync failure'));

        Log::shouldReceive('error')
            ->once()
            ->with('Cart sync error on login: Sync failure');

        $cartService = $this->createCartService(contextResolver: $contextResolver);
        $listener = new SyncGuestCartOnLogin($cartService);
        $event = new Login('web', $user, false);

        $listener->handle($event);
    }

    public function test_accepts_cart_service_interface_directly(): void
    {
        $user = new User();
        $mockService = $this->createMock(CartServiceInterface::class);
        $mockService->expects($this->once())
            ->method('syncGuestCartToUser')
            ->with($user);

        $listener = new SyncGuestCartOnLogin($mockService);
        $event = new Login('web', $user, false);

        $listener->handle($event);
    }

    private function createCartService(
        ?CartRepositoryInterface $repository = null,
        ?CartContextResolverInterface $contextResolver = null
    ): CartService {
        return new CartService(
            $repository ?? $this->createMock(CartRepositoryInterface::class),
            $contextResolver ?? $this->createMock(CartContextResolverInterface::class),
            $this->createMock(ProductValidatorInterface::class),
            $this->createMock(\App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface::class),
            app(\App\Plugins\CartEngine\src\Services\CartLineTypeRegistry::class)
        );
    }
}
