<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class GoogleAuthController extends Controller
{
    public function __construct(private readonly SettingService $settingService) {}

    public function redirect(): RedirectResponse
    {
        $this->configureSocialite();

        if (request()->has('expo_token')) {
            session(['expo_token' => request('expo_token')]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(CartService $cartService): RedirectResponse
    {
        $this->configureSocialite();

        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Update existing user with Google ID if not set
                if (! $user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                }
            } else {
                // Create new user
                $user = User::query()->create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)), // Random password for OAuth users
                    'email_verified_at' => now(),
                ]);
            }

            Auth::login($user, true);

            // Sync guest cart to authenticated user
            $cartService->syncGuestCartToUser($user);

            if (session()->has('expo_token')) {
                $token = session('expo_token');
                $validator = \Illuminate\Support\Facades\Validator::make(['expo_token' => $token], [
                    'expo_token' => \NotificationChannels\Expo\ExpoPushToken::rule(),
                ]);

                if ($validator->passes()) {
                    $user->update(['expo_token' => $token]);
                }
                session()->forget('expo_token');
            }

            return redirect()->intended(route('home', absolute: false));
        } catch (Throwable $e) {
            return redirect()->route('login')->with('error', 'Unable to login with Google. Please try again.');
        }
    }

    /**
     * Configure Socialite with tenant-specific Google OAuth credentials.
     */
    private function configureSocialite(): void
    {
        config([
            'services.google.client_id' => $this->settingService->get(SettingKey::GOOGLE_CLIENT_ID),
            'services.google.client_secret' => $this->settingService->get(SettingKey::GOOGLE_CLIENT_SECRET),
            'services.google.redirect' => $this->settingService->get(SettingKey::GOOGLE_REDIRECT_URL),
        ]);
    }
}
