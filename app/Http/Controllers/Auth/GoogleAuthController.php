<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ExpoTokenRegistrarService;
use App\Services\Auth\GoogleAccountService;
use App\Services\Auth\GoogleIdTokenVerifierService;
use App\Services\Auth\GoogleOAuthConfigService;
use App\Support\Auth\GoogleIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleIdTokenVerifierService $tokenVerifier,
        private readonly GoogleOAuthConfigService $googleOAuthConfig,
        private readonly GoogleAccountService $accountService,
        private readonly ExpoTokenRegistrarService $expoTokenRegistrar
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $this->googleOAuthConfig->apply();

        if ($request->filled('expo_token')) {
            session(['expo_token' => $request->input('expo_token')]);
        }

        if ($request->has('remember')) {
            session(['remember' => $request->boolean('remember')]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->googleOAuthConfig->apply();

        try {
            $googleUser = Socialite::driver('google')->user();

            $emailVerified = false;
            if (is_object($googleUser) && property_exists($googleUser, 'user') && is_array($googleUser->user)) {
                $claim = $googleUser->user['email_verified'] ?? null;
                $emailVerified = ($claim === true || $claim === 'true');
            }

            $identity = new GoogleIdentity(
                googleId: (string) $googleUser->getId(),
                email: (string) $googleUser->getEmail(),
                name: (string) $googleUser->getName(),
                avatar: (string) $googleUser->getAvatar(),
                emailVerified: $emailVerified,
            );

            $result = $this->accountService->resolveUser($identity);
            if ($result->isFailure()) {
                return redirect()->route('login')->with('error', $result->errorMessage());
            }

            $user = $result->user();
            $remember = (bool) (session()->pull('remember') ?? $request->boolean('remember'));
            Auth::login($user, $remember);
            $request->session()->regenerate();

            $expoToken = session()->pull('expo_token');
            $this->expoTokenRegistrar->registerFor($user, is_string($expoToken) ? $expoToken : null);

            return redirect()->intended(route('home', absolute: false));
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Unable to login with Google. Please try again.');
        }
    }

    /**
     * Handle the callback from the native app with an Access Token (deprecated).
     */
    public function handleNativeCallback(Request $request): RedirectResponse
    {
        logger()->warning('Google native callback via GET access_token is deprecated and will be removed in a future release.');

        $accessToken = $request->input('access_token');
        if (! $accessToken) {
            return redirect()->route('login')->with('error', 'No Access token provided.');
        }

        try {
            $this->googleOAuthConfig->apply();

            $tokenInfo = $this->tokenVerifier->verifyAccessToken((string) $accessToken);
            if ($tokenInfo === null) {
                return redirect()->route('login')->with('error', 'Unable to login with Google. Please try again.');
            }

            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->userFromToken((string) $accessToken);

            $claim = $tokenInfo['email_verified'] ?? null;
            $emailVerified = ($claim === true || $claim === 'true');

            $identity = new GoogleIdentity(
                googleId: (string) $googleUser->getId(),
                email: (string) $googleUser->getEmail(),
                name: (string) $googleUser->getName(),
                avatar: (string) $googleUser->getAvatar(),
                emailVerified: $emailVerified,
            );

            $result = $this->accountService->resolveUser($identity);
            if ($result->isFailure()) {
                return redirect()->route('login')->with('error', $result->errorMessage());
            }

            $user = $result->user();
            $remember = $request->boolean('remember');
            Auth::login($user, $remember);
            $request->session()->regenerate();

            $this->expoTokenRegistrar->registerFor($user, $request->input('expo_token'));

            return redirect()->intended(route('home', absolute: false));
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Unable to login with Google. Please try again.');
        }
    }
}
