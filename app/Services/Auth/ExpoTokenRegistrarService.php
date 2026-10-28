<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use NotificationChannels\Expo\ExpoPushToken;

final class ExpoTokenRegistrarService
{
    /**
     * Validate and register an Expo push token for the user.
     * Saves only if the token passes the ExpoPushToken validation rule.
     */
    public function registerFor(User $user, ?string $token): void
    {
        if ($token === null || mb_trim($token) === '') {
            return;
        }

        $validator = Validator::make(
            ['expo_token' => $token],
            ['expo_token' => [ExpoPushToken::rule()]]
        );

        if ($validator->passes()) {
            $user->update(['expo_token' => $token]);
        }
    }
}
