<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\GoogleAccountService;
use App\Support\Auth\GoogleIdentity;
use App\Support\Auth\GoogleLoginFailureReason;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->service = new GoogleAccountService;
});

test('it rejects login when email is not verified', function () {
    $identity = new GoogleIdentity(
        googleId: 'google-unverified-1',
        email: 'unverified_test@example.com',
        name: 'Unverified User',
        avatar: null,
        emailVerified: false,
    );

    $result = $this->service->resolveUser($identity);

    expect($result->isFailure())->toBeTrue();
    expect($result->failureReason())->toBe(GoogleLoginFailureReason::EmailNotVerified);
    expect($result->errorMessage())->toBe('Your Google email is not verified.');
    expect($result->user())->toBeNull();
});

test('it refuses automatic linking for existing user with password and no google_id', function () {
    $existing = User::factory()->create([
        'email' => 'has_password@example.com',
        'password' => Hash::make('secret-pass'),
        'google_id' => null,
    ]);

    $identity = new GoogleIdentity(
        googleId: 'google-new-id',
        email: 'has_password@example.com',
        name: 'Has Password',
        avatar: null,
        emailVerified: true,
    );

    $result = $this->service->resolveUser($identity);

    expect($result->isFailure())->toBeTrue();
    expect($result->failureReason())->toBe(GoogleLoginFailureReason::LinkRequired);
    expect($result->errorMessage())->toBe('An account with this email already exists. Please log in with your password.');

    $existing->refresh();
    expect($existing->google_id)->toBeNull();
});

test('it rejects login when user google_id does not match identity google_id', function () {
    User::factory()->create([
        'email' => 'mismatch@example.com',
        'google_id' => 'original-google-id',
    ]);

    $identity = new GoogleIdentity(
        googleId: 'different-google-id',
        email: 'mismatch@example.com',
        name: 'Mismatch User',
        avatar: null,
        emailVerified: true,
    );

    $result = $this->service->resolveUser($identity);

    expect($result->isFailure())->toBeTrue();
    expect($result->failureReason())->toBe(GoogleLoginFailureReason::AccountMismatch);
    expect($result->errorMessage())->toBe('Google account does not match this user.');
});

test('it successfully resolves an existing Google user with matching google_id', function () {
    $user = User::factory()->create([
        'email' => 'existing_oauth@example.com',
        'google_id' => 'matched-google-id',
        'avatar' => null,
    ]);

    $identity = new GoogleIdentity(
        googleId: 'matched-google-id',
        email: 'existing_oauth@example.com',
        name: 'Matched User',
        avatar: 'https://example.com/new-avatar.jpg',
        emailVerified: true,
    );

    $result = $this->service->resolveUser($identity);

    expect($result->isSuccess())->toBeTrue();
    expect($result->user())->not->toBeNull();
    expect($result->user()->id)->toBe($user->id);

    $user->refresh();
    expect($user->avatar)->toBe('https://example.com/new-avatar.jpg');
});

test('it creates a new user when email does not exist', function () {
    $email = 'brandnew_google_user@example.com';
    expect(User::query()->where('email', $email)->exists())->toBeFalse();

    $identity = new GoogleIdentity(
        googleId: 'brand-new-google-id',
        email: $email,
        name: 'Brand New User',
        avatar: 'https://example.com/pic.jpg',
        emailVerified: true,
    );

    $result = $this->service->resolveUser($identity);

    expect($result->isSuccess())->toBeTrue();
    $user = $result->user();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe($email);
    expect($user->name)->toBe('Brand New User');
    expect($user->google_id)->toBe('brand-new-google-id');
    expect($user->avatar)->toBe('https://example.com/pic.jpg');
    expect($user->email_verified_at)->not->toBeNull();
});
