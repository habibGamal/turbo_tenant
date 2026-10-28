<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Auth::logout();
    config([
        'services.google.client_id' => 'valid-client-id.apps.googleusercontent.com',
        'services.google.allowed_client_ids' => [
            'valid-client-id.apps.googleusercontent.com',
            'mobile-app-client-id.apps.googleusercontent.com',
        ],
    ]);
});

test('rejects id token with foreign aud and does not establish a session', function () {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'attacker-client-id.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => '1234567890',
            'email' => 'victim@example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
        ], 200),
    ]);

    $response = $this->postJson(route('api.tenant.auth.google.native'), [
        'id_token' => 'foreign-aud-token',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid or unverified Google token.']);

    $this->assertGuest();
});

test('rejects id token with unverified email', function () {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'valid-client-id.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => '1234567890',
            'email' => 'unverified@example.com',
            'email_verified' => 'false',
            'exp' => (string) (time() + 3600),
        ], 200),
    ]);

    $response = $this->postJson(route('api.tenant.auth.google.native'), [
        'id_token' => 'unverified-email-token',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid or unverified Google token.']);

    $this->assertGuest();
});

test('rejects expired id token', function () {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'valid-client-id.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => '1234567890',
            'email' => 'expired@example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() - 3600),
        ], 200),
    ]);

    $response = $this->postJson(route('api.tenant.auth.google.native'), [
        'id_token' => 'expired-token',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid or unverified Google token.']);

    $this->assertGuest();
});

test('refuses automatic linking for existing password user without google_id', function () {
    $existingUser = User::factory()->create([
        'email' => 'password_user@example.com',
        'password' => Hash::make('existing-password'),
        'google_id' => null,
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'valid-client-id.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => 'google-user-999',
            'email' => 'password_user@example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
        ], 200),
    ]);

    $response = $this->postJson(route('api.tenant.auth.google.native'), [
        'id_token' => 'valid-id-token',
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'code' => 'link_required',
        ]);

    $this->assertGuest();
    $existingUser->refresh();
    expect($existingUser->google_id)->toBeNull();
});

test('happy path logs in new user with authenticated session and customer resource', function () {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'mobile-app-client-id.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => 'google-user-new-123',
            'email' => 'newgoogleuser@example.com',
            'name' => 'New Google User',
            'picture' => 'https://example.com/photo.jpg',
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
        ], 200),
    ]);

    $response = $this->postJson(route('api.tenant.auth.google.native'), [
        'id_token' => 'valid-mobile-token',
        'expo_token' => 'ExponentPushToken[device-mobile-1]',
        'remember' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('user.email', 'newgoogleuser@example.com')
        ->assertJsonPath('user.name', 'New Google User');

    $user = User::query()->where('email', 'newgoogleuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-user-new-123');
    expect($user->avatar)->toBe('https://example.com/photo.jpg');
    expect((string) $user->expo_token)->toBe('ExponentPushToken[device-mobile-1]');
    expect($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('old GET native callback route refuses foreign audience access token and logs deprecation', function () {
    Log::spy();

    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'foreign-attacker-app.apps.googleusercontent.com',
            'azp' => 'foreign-attacker-app.apps.googleusercontent.com',
            'sub' => 'attacker-sub',
            'email' => 'victim_access@example.com',
            'email_verified' => 'true',
            'expires_in' => '3600',
        ], 200),
    ]);

    $response = $this->get(route('auth.google.native-callback', [
        'access_token' => 'foreign-access-token',
    ]));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message): bool {
            return str_contains($message, 'deprecated') && ! str_contains($message, 'foreign-access-token');
        });
});

test('guest cart is still merged on login through login event listener when cart-engine is active', function () {
    // 1. Create product
    $product = Product::factory()->create([
        'base_price' => 15.00,
        'is_active' => true,
    ]);

    // 2. Guest adds product to cart
    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '2',
    ])->assertSuccessful();

    $guestCart = Cart::whereNotNull('session_id')->first();
    expect($guestCart)->not->toBeNull();

    // 3. User registers/exists
    $user = User::factory()->create([
        'email' => 'cart_listener_user@example.com',
        'password' => Hash::make('password123'),
    ]);

    // 4. Perform web login (store method without CartService injection)
    $response = $this->post('/login', [
        'email' => 'cart_listener_user@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('home', absolute: false));
    $this->assertAuthenticatedAs($user);

    // 5. Verify the guest cart was merged into the user cart by the Login event listener
    $userCart = Cart::where('user_id', $user->id)->first();
    expect($userCart)->not->toBeNull();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $userCart->id,
        'product_id' => $product->id,
    ]);

    $this->assertDatabaseMissing('carts', [
        'id' => $guestCart->id,
    ]);
});
