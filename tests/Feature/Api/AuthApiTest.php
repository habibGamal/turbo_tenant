<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.passwords.users.throttle' => 0]);
        Notification::fake();
    }

    public function test_user_can_login_via_api(): void
    {
        Auth::logout();

        $user = User::factory()->create([
            'email' => 'api_customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/tenant/auth/login', [
            'email' => 'api_customer@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.email', 'api_customer@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tenant/auth/logout');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertGuest();
    }

    public function test_authenticated_user_profile_can_be_retrieved(): void
    {
        $user = User::factory()->create([
            'email' => 'profile_test@example.com',
        ]);

        $response = $this->actingAs($user)->getJson('/api/tenant/auth/user');

        $response->assertOk()
            ->assertJsonPath('user.email', 'profile_test@example.com');
    }

    public function test_user_can_request_password_reset_link_via_api(): void
    {
        Auth::logout();

        User::factory()->create([
            'email' => 'reset_link_user@example.com',
        ]);

        $response = $this->postJson('/api/tenant/auth/forgot-password', [
            'email' => 'reset_link_user@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_password_reset_link_request_fails_with_invalid_email(): void
    {
        Auth::logout();

        $response = $this->postJson('/api/tenant/auth/forgot-password', [
            'email' => 'nonexistent_customer@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_via_api(): void
    {
        Auth::logout();

        $user = User::factory()->create([
            'email' => 'reset_action_user@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/tenant/auth/reset-password', [
            'token' => $token,
            'email' => 'reset_action_user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        Auth::logout();

        User::factory()->create([
            'email' => 'reset_invalid_token@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->postJson('/api/tenant/auth/reset-password', [
            'token' => 'invalid-token-value',
            'email' => 'reset_invalid_token@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_update_profile_via_api(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original_profile@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->patchJson('/api/tenant/auth/profile', [
            'name' => 'Updated Name',
            'email' => 'updated_profile@example.com',
            'phone' => '01012345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', 'updated_profile@example.com');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated_profile@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_update_preserves_verified_at_if_email_unchanged(): void
    {
        $verifiedAt = now()->subDay();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'keep_email@example.com',
            'email_verified_at' => $verifiedAt,
        ]);

        $response = $this->actingAs($user)->patchJson('/api/tenant/auth/profile', [
            'name' => 'Brand New Name',
            'email' => 'keep_email@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.name', 'Brand New Name');

        $user->refresh();
        $this->assertSame('Brand New Name', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_profile_update_fails_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing_other@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'current_user@example.com',
        ]);

        $response = $this->actingAs($user)->patchJson('/api/tenant/auth/profile', [
            'name' => 'New Name',
            'email' => 'existing_other@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_guest_cannot_update_profile_via_api(): void
    {
        Auth::logout();

        $response = $this->patchJson('/api/tenant/auth/profile', [
            'name' => 'Guest Name',
            'email' => 'guest@example.com',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_password_via_api(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('currentpassword123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/tenant/auth/password', [
            'current_password' => 'currentpassword123',
            'password' => 'newsecretpass456',
            'password_confirmation' => 'newsecretpass456',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Hash::check('newsecretpass456', $user->fresh()->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('currentpassword123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/tenant/auth/password', [
            'current_password' => 'wrongpassword123',
            'password' => 'newsecretpass456',
            'password_confirmation' => 'newsecretpass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_guest_cannot_update_password_via_api(): void
    {
        Auth::logout();

        $response = $this->putJson('/api/tenant/auth/password', [
            'current_password' => 'somepassword',
            'password' => 'newsecretpass456',
            'password_confirmation' => 'newsecretpass456',
        ]);

        $response->assertUnauthorized();
    }

    protected function initializeTenancy(): void
    {
        parent::initializeTenancy();
        User::query()->delete();
    }
}
