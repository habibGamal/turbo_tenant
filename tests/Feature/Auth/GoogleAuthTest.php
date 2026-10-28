<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Models\User;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    Auth::logout();
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
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('home', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-123');
    expect($user->name)->toBe('Test User');
    expect($user->avatar)->toBe('https://example.com/avatar.jpg');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Auth::check())->toBeTrue();
});

test('refuses automatic linking for existing password user on google callback', function () {
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
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');

    $existingUser->refresh();
    expect($existingUser->google_id)->toBeNull();
    expect(Auth::check())->toBeFalse();
});

test('links and logs in existing user with matching google_id on callback', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing_oauth@example.com',
        'google_id' => 'google-456',
    ]);

    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-456');
        $mock->shouldReceive('getEmail')->andReturn('existing_oauth@example.com');
        $mock->shouldReceive('getName')->andReturn('Existing OAuth User');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar2.jpg');
    });
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('home', absolute: false));

    $existingUser->refresh();
    expect($existingUser->google_id)->toBe('google-456');
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($existingUser->id);
});

test('refuses callback when email_verified is missing (fail closed)', function () {
    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-unverified');
        $mock->shouldReceive('getEmail')->andReturn('missing_claim@example.com');
        $mock->shouldReceive('getName')->andReturn('Unverified User');
        $mock->shouldReceive('getAvatar')->andReturn(null);
    });
    // user property does not have email_verified
    $socialiteUser->user = [];

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Your Google email is not verified.');
    expect(Auth::check())->toBeFalse();
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

test('logs in user on successful oauth callback with default remember false', function () {
    $socialiteUser = $this->mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-789');
        $mock->shouldReceive('getEmail')->andReturn('remember@example.com');
        $mock->shouldReceive('getName')->andReturn('Remember User');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar3.jpg');
    });
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn($socialiteUser);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('home', absolute: false));
    expect(Auth::check())->toBeTrue();
    expect(Auth::viaRemember())->toBeFalse();
});
