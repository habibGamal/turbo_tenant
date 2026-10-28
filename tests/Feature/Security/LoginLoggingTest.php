<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

test('logging in through web login does not write password or expo token to logs', function () {
    Auth::logout();

    $password = 'SecretPassword123!';
    $expoToken = 'ExponentPushToken[test-expo-token-999]';

    $user = User::factory()->create([
        'email' => 'no_log_user@example.com',
        'password' => Hash::make($password),
    ]);

    Log::spy();

    $response = $this->post('/login', [
        'email' => 'no_log_user@example.com',
        'password' => $password,
        'expo_token' => $expoToken,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('home', absolute: false));

    Log::shouldNotHaveReceived('info', function (string $message, array $context = []) use ($password): bool {
        $dump = json_encode(array_merge([$message], $context)) ?: '';

        return str_contains($dump, $password);
    });

    Log::shouldNotHaveReceived('info', function (string $message, array $context = []) use ($expoToken): bool {
        $dump = json_encode(array_merge([$message], $context)) ?: '';

        return str_contains($dump, $expoToken);
    });
});
