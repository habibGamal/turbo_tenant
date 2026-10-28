<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use App\Services\Auth\ExpoTokenRegistrarService;
use App\Services\Auth\GoogleAccountService;
use App\Services\Auth\GoogleIdTokenVerifierService;
use App\Services\Auth\GoogleOAuthConfigService;
use App\Support\Auth\GoogleIdentity;
use App\Support\Auth\GoogleLoginFailureReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class GoogleNativeAuthController extends Controller
{
    public function __construct(
        private readonly GoogleIdTokenVerifierService $tokenVerifier,
        private readonly GoogleOAuthConfigService $googleOAuthConfig,
        private readonly GoogleAccountService $accountService,
        private readonly ExpoTokenRegistrarService $expoTokenRegistrar
    ) {}

    /**
     * Handle native/mobile Google ID token authentication.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'expo_token' => ['nullable', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->googleOAuthConfig->apply();

        $payload = $this->tokenVerifier->verifyIdToken($request->string('id_token')->toString());
        if ($payload === null) {
            return response()->json([
                'message' => 'Invalid or unverified Google token.',
            ], 401);
        }

        $claim = $payload['email_verified'] ?? null;
        $identity = new GoogleIdentity(
            googleId: (string) ($payload['sub'] ?? $payload['id'] ?? ''),
            email: (string) $payload['email'],
            name: isset($payload['name']) && is_string($payload['name']) ? $payload['name'] : null,
            avatar: isset($payload['picture']) && is_string($payload['picture']) ? $payload['picture'] : null,
            emailVerified: ($claim === true || $claim === 'true'),
        );

        $result = $this->accountService->resolveUser($identity);

        if ($result->isFailure()) {
            $reason = $result->failureReason();
            $status = $reason === GoogleLoginFailureReason::LinkRequired ? 409 : 401;

            return response()->json([
                'message' => $result->errorMessage(),
                ...($reason === GoogleLoginFailureReason::LinkRequired ? ['code' => 'link_required'] : []),
            ], $status);
        }

        $user = $result->user();
        if ($user === null) {
            return response()->json([
                'message' => 'Unable to authenticate user.',
            ], 401);
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $this->expoTokenRegistrar->registerFor($user, $request->input('expo_token'));

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully.',
            'user' => CustomerResource::make($user)->resolve(),
        ]);
    }
}
