<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Resolvers;

use App\Models\Cart;
use App\Models\GuestUser;
use App\Models\User;
use App\Services\Cart\Resolvers\CartContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

final class CartContextResolverTest extends TestCase
{
    use RefreshDatabase;

    private CartContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CartContextResolver();
        Session::flush();
    }

    public function test_get_guest_cart_identifier_generates_and_stores_in_session(): void
    {
        $this->assertFalse(Session::has('guest_cart_id'));

        $id1 = $this->resolver->getGuestCartIdentifier();

        $this->assertTrue(Session::has('guest_cart_id'));
        $this->assertStringStartsWith('guest_', $id1);

        $id2 = $this->resolver->getGuestCartIdentifier();
        $this->assertSame($id1, $id2);
    }

    public function test_resolve_cart_for_user(): void
    {
        $user = User::factory()->create();
        $this->assertNull($this->resolver->resolveCart($user));

        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $resolved = $this->resolver->resolveCart($user);
        $this->assertNotNull($resolved);
        $this->assertSame($cart->id, $resolved->id);
    }

    public function test_resolve_cart_for_guest(): void
    {
        Session::put('guest_cart_id', 'guest_abc_123');

        $this->assertNull($this->resolver->resolveCart(null));
        $this->assertNull($this->resolver->resolveCart(new GuestUser()));

        $cart = Cart::create(['session_id' => 'guest_abc_123']);

        $resolved = $this->resolver->resolveCart(null);
        $this->assertNotNull($resolved);
        $this->assertSame($cart->id, $resolved->id);
    }

    public function test_resolve_or_create_cart_for_user(): void
    {
        $user = User::factory()->create();

        $cart1 = $this->resolver->resolveOrCreateCart($user);
        $this->assertSame($user->id, $cart1->user_id);

        $cart2 = $this->resolver->resolveOrCreateCart($user);
        $this->assertSame($cart1->id, $cart2->id);
    }

    public function test_resolve_or_create_cart_for_guest(): void
    {
        $cart1 = $this->resolver->resolveOrCreateCart(null);
        $this->assertNotNull($cart1->session_id);
        $this->assertNull($cart1->user_id);

        $cart2 = $this->resolver->resolveOrCreateCart(null);
        $this->assertSame($cart1->id, $cart2->id);
    }

    public function test_clear_guest_session(): void
    {
        Session::put('guest_cart_id', 'guest_to_clear');
        $this->assertTrue(Session::has('guest_cart_id'));

        $this->resolver->clearGuestSession();

        $this->assertFalse(Session::has('guest_cart_id'));
    }
}
