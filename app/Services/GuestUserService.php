<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GuestUser;
use App\Models\User;

final class GuestUserService
{
    /**
     * Find or create a guest user by phone/email
     */
    public function findOrCreate(array $guestData): GuestUser
    {
        $phone = $guestData['phone'];
        $phoneCountryCode = $guestData['phone_country_code'] ?? '+20';

        // Try to find existing guest by phone
        $guestUser = GuestUser::where('phone', $phone)
            ->where('phone_country_code', $phoneCountryCode)
            ->first();

        if ($guestUser) {
            // Update info if provided
            $guestUser->update(array_filter([
                'name' => $guestData['name'] ?? $guestUser->name,
                'email' => $guestData['email'] ?? $guestUser->email,
                'street' => $guestData['street'] ?? $guestUser->street,
                'building' => $guestData['building'] ?? $guestUser->building,
                'floor' => $guestData['floor'] ?? $guestUser->floor,
                'apartment' => $guestData['apartment'] ?? $guestUser->apartment,
                'city' => $guestData['city'] ?? $guestUser->city,
                'area_id' => $guestData['area_id'] ?? $guestUser->area_id,
            ]));

            return $guestUser;
        }

        // Create new guest user
        return GuestUser::create($guestData);
    }

    /**
     * Convert guest user to registered user
     * Transfers all guest orders to the user account
     */
    public function convertToUser(GuestUser $guestUser, User $user): void
    {
        // Transfer all guest orders to the user
        $guestUser->orders()->update([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        // Optionally delete guest record (commented for data retention)
        // $guestUser->delete();
    }
}
