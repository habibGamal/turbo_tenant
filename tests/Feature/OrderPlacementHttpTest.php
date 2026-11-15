<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Services\GuestTrackingTokenService;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\OrderManagement\src\Contracts\PaymentIntention;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class OrderPlacementHttpTest extends TestCase
{
    use RefreshDatabase;

    private Branch $activeBranch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeBranch = Branch::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create([
            'base_price' => 50.00,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------
    // PlaceOrderRequest validation tests
    // -------------------------------------------------------------

    public function test_place_order_request_rejects_type_pos(): void
    {
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'pos',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_place_order_request_rejects_inactive_branch(): void
    {
        $this->populateCart();
        $inactiveBranch = Branch::factory()->create(['is_active' => false]);

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $inactiveBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_place_order_request_rejects_non_existent_branch(): void
    {
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => 999999,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_place_order_request_rejects_invalid_payment_method_enum(): void
    {
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'bitcoin_crypto',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_place_order_request_rejects_web_delivery_without_address_for_authenticated_user(): void
    {
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address_id']);
    }

    public function test_place_order_request_rejects_address_not_owned_by_authenticated_user(): void
    {
        $this->populateCart();
        $otherUser = User::factory()->create();
        $otherAddress = Address::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'address_id' => $otherAddress->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address_id']);
    }

    public function test_place_order_request_rejects_web_delivery_without_area_id_for_guest(): void
    {
        Auth::logout();
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest User',
                'phone' => '01012345678',
                'email' => 'guest@example.com',
                'street' => 'Main St',
                'building' => '12',
                'city' => 'Cairo',
                // area_id is missing
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_data.area_id']);
    }

    public function test_place_order_request_rejects_web_delivery_without_area_for_guest(): void
    {
        Auth::logout();
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest User',
                'phone' => '01012345678',
                'email' => 'guest@example.com',
                'street' => 'Main Street',
                // area_id is missing
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_data.area_id']);
    }

    // -------------------------------------------------------------
    // OrderPlacementResponder tests
    // -------------------------------------------------------------

    public function test_responder_cod_success_has_no_checkout_url(): void
    {
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'requires_payment' => false,
                'checkout_url' => null,
            ],
        ]);
        $this->assertArrayNotHasKey('redirect_url', $response->json('data'));
        $this->assertArrayNotHasKey('redirect_type', $response->json('data'));
    }

    public function test_responder_online_payment_success_has_checkout_url(): void
    {
        $this->populateCart();

        $mockGateway = Mockery::mock(PaymentInitiator::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn(PaymentIntention::created('https://accept.paymobsolutions.com/checkout/12345'));
        $this->app->instance(PaymentInitiator::class, $mockGateway);

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'card',
            'type' => 'web_takeaway',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'requires_payment' => true,
                'checkout_url' => 'https://accept.paymobsolutions.com/checkout/12345',
            ],
        ]);
    }

    public function test_responder_online_payment_missing_checkout_url_returns_502(): void
    {
        $this->populateCart();

        $mockGateway = Mockery::mock(PaymentInitiator::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn(PaymentIntention::created(null)); // Success but no checkout URL
        $this->app->instance(PaymentInitiator::class, $mockGateway);

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'card',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'status' => 'error',
                'message' => 'Payment checkout URL not available',
            ]);
    }

    // -------------------------------------------------------------
    // Guest tracking token and no phone in tracking URL
    // -------------------------------------------------------------

    public function test_guest_placement_response_includes_tracking_token_and_no_phone(): void
    {
        Auth::logout();
        $this->populateCart();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->activeBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
            'guest_data' => [
                'name' => 'Guest User',
                'phone' => '01012345678',
                'email' => 'guest@example.com',
            ],
        ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertArrayHasKey('tracking_token', $data);
        $this->assertNotEmpty($data['tracking_token']);
        $this->assertArrayNotHasKey('redirect_url', $data);
        $this->assertArrayNotHasKey('phone', $data);
    }

    public function test_valid_tracking_token_finds_order(): void
    {
        $guest = GuestUser::factory()->create(['phone' => '01012345678', 'phone_country_code' => '+20']);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'branch_id' => $this->activeBranch->id,
        ]);

        $tokenService = app(GuestTrackingTokenService::class);
        $token = $tokenService->generate($order);

        $response = $this->postJson(route('api.guest-checkout.track'), [
            'order_number' => $order->order_number,
            'token' => $token,
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);
    }

    public function test_wrong_tracking_token_returns_404(): void
    {
        $guest = GuestUser::factory()->create(['phone' => '01011112222', 'phone_country_code' => '+20']);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'branch_id' => $this->activeBranch->id,
        ]);

        $response = $this->postJson(route('api.guest-checkout.track'), [
            'order_number' => $order->order_number,
            'token' => 'invalid-token-signature',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_phone_in_body_still_works_for_guest_tracking(): void
    {
        $guest = GuestUser::factory()->create(['phone' => '01099998888', 'phone_country_code' => '+20']);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'branch_id' => $this->activeBranch->id,
        ]);

        $response = $this->postJson(route('api.guest-checkout.track'), [
            'order_number' => $order->order_number,
            'phone' => '01099998888',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                ],
            ]);
    }

    public function test_wrong_phone_returns_404(): void
    {
        $guest = GuestUser::factory()->create(['phone' => '01099998888', 'phone_country_code' => '+20']);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'branch_id' => $this->activeBranch->id,
        ]);

        $response = $this->postJson(route('api.guest-checkout.track'), [
            'order_number' => $order->order_number,
            'phone' => '01000000000',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    // -------------------------------------------------------------
    // OrderResource & Security tests
    // -------------------------------------------------------------

    public function test_order_resource_hides_internal_columns(): void
    {
        $order = Order::factory()->create([
            'user_id' => Auth::id(),
            'branch_id' => $this->activeBranch->id,
            'payment_data' => ['secret_key' => '12345'],
            'pos_failure_reason' => 'Sync error terminal 4',
            'shift_id' => 99,
            'merchant_order_id' => 'MERCH-123',
            'paymob_order_id' => 'PAYMOB-456',
            'transaction_id' => 'TXN-789',
        ]);

        $response = $this->getJson(route('api.orders.show', ['orderId' => $order->id]));
        $response->assertSuccessful();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('payment_data', $data);
        $this->assertArrayNotHasKey('pos_failure_reason', $data);
        $this->assertArrayNotHasKey('shift_id', $data);
        $this->assertArrayNotHasKey('merchant_order_id', $data);
        $this->assertArrayNotHasKey('paymob_order_id', $data);
        $this->assertArrayNotHasKey('transaction_id', $data);
    }

    public function test_cannot_view_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->create([
            'user_id' => $otherUser->id,
            'branch_id' => $this->activeBranch->id,
        ]);

        $response = $this->getJson(route('api.orders.show', ['orderId' => $otherOrder->id]));

        $response->assertStatus(404);
    }

    private function populateCart(): void
    {
        $this->postJson(route('api.cart.store'), [
            'product_id' => $this->product->id,
            'quantity' => '1',
        ])->assertSuccessful();
    }
}
