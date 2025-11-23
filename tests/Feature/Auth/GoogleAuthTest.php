<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    $settingService = app(SettingService::class);
    $settingService->set(SettingKey::GOOGLE_CLIENT_ID, 'test-client-id');
    $settingService->set(SettingKey::GOOGLE_CLIENT_SECRET, 'test-client-secret');
    $settingService->set(SettingKey::GOOGLE_REDIRECT_URL, 'http://localhost:8000/auth/google/callback');
});

test('redirects to google oauth', function () {
    $response = $this->get(route('auth.google'));

    $response->assertRedirect();
});

test('creates new user on google callback', function () {
    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-123');
        $mock->shouldReceive('getEmail')->andReturn('test@example.com');
        $mock->shouldReceive('getName')->andReturn('Test User');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    });

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-123');
    expect($user->name)->toBe('Test User');
    expect($user->avatar)->toBe('https://example.com/avatar.jpg');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Auth::check())->toBeTrue();
});

test('links existing user on google callback', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-456');
        $mock->shouldReceive('getEmail')->andReturn('existing@example.com');
        $mock->shouldReceive('getName')->andReturn('Existing User');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar2.jpg');
    });

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $existingUser->refresh();
    expect($existingUser->google_id)->toBe('google-456');
    expect($existingUser->avatar)->toBe('https://example.com/avatar2.jpg');
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($existingUser->id);
});

test('handles oauth error gracefully', function () {
    Socialite::shouldReceive('driver->user')
        ->once()
        ->andThrow(new Exception('OAuth error'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Unable to login with Google. Please try again.');
    expect(Auth::check())->toBeFalse();
});

test('logs in user with remember me on successful oauth', function () {
    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-789');
        $mock->shouldReceive('getEmail')->andReturn('remember@example.com');
        $mock->shouldReceive('getName')->andReturn('Remember User');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar3.jpg');
    });

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(Auth::check())->toBeTrue();
    expect(Auth::viaRemember())->toBeFalse(); // First login, so not via remember
});
