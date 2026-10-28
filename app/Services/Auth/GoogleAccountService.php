<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Auth\GoogleIdentity;
use App\Support\Auth\GoogleLoginFailureReason;
use App\Support\Auth\GoogleLoginResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class GoogleAccountService
{
    /**
     * Resolve a user from a verified Google identity.
     *
     * Handles account linking, duplicate prevention, and user creation.
     */
    public function resolveUser(GoogleIdentity $identity): GoogleLoginResult
    {
        if (! $identity->emailVerified) {
            return GoogleLoginResult::failure(GoogleLoginFailureReason::EmailNotVerified);
        }

        $user = User::query()->where('email', $identity->email)->first();

        if ($user !== null) {
            if (! $user->google_id) {
                if ($user->password !== null && $user->password !== '') {
                    return GoogleLoginResult::failure(GoogleLoginFailureReason::LinkRequired);
                }

                $user->update([
                    'google_id' => $identity->googleId,
                    'avatar' => $identity->avatar ?? $user->avatar,
                ]);

                return GoogleLoginResult::success($user);
            }

            if ($user->google_id !== $identity->googleId) {
                return GoogleLoginResult::failure(GoogleLoginFailureReason::AccountMismatch);
            }

            if ($identity->avatar !== null && ! $user->avatar) {
                $user->update(['avatar' => $identity->avatar]);
            }

            return GoogleLoginResult::success($user);
        }

        $name = $identity->name ?: explode('@', $identity->email)[0];

        $user = User::query()->create([
            'name' => $name,
            'email' => $identity->email,
            'google_id' => $identity->googleId,
            'avatar' => $identity->avatar,
            'password' => Hash::make(Str::random(24)),
            'email_verified_at' => now(),
        ]);

        return GoogleLoginResult::success($user);
    }
}
