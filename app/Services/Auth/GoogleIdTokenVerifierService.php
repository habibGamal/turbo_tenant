<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Throwable;

final class GoogleIdTokenVerifierService
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    private const ALLOWED_ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    /**
     * Verify a Google ID token via Google's tokeninfo endpoint.
     *
     * Validates:
     * - Network/endpoint response status 200
     * - Token audience (aud) matches an allowed client ID
     * - Token issuer (iss) is Google
     * - Expiration (exp) is in the future
     * - Email is verified (email_verified === true || 'true')
     * - Email is present
     *
     * @return array<string, mixed>|null Payload array on success, null on failure
     */
    public function verifyIdToken(string $idToken): ?array
    {
        if (mb_trim($idToken) === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get(self::TOKENINFO_URL, [
                'id_token' => $idToken,
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        if (! $this->hasValidAudience($payload['aud'] ?? null)) {
            return null;
        }

        if (! $this->hasValidIssuer($payload['iss'] ?? null)) {
            return null;
        }

        if (! $this->isNotExpired($payload['exp'] ?? null)) {
            return null;
        }

        if (! $this->isEmailVerified($payload['email_verified'] ?? null)) {
            return null;
        }

        if (empty($payload['email']) || ! is_string($payload['email'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Verify a Google access token via Google's tokeninfo endpoint.
     *
     * Validates:
     * - Network/endpoint response status 200
     * - Token audience (aud or azp) matches an allowed client ID
     * - Expiration (exp or expires_in) is in the future
     * - Email is verified (missing claim counts as not verified)
     *
     * @return array<string, mixed>|null Payload array on success, null on failure
     */
    public function verifyAccessToken(string $accessToken): ?array
    {
        if (mb_trim($accessToken) === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get(self::TOKENINFO_URL, [
                'access_token' => $accessToken,
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        $aud = $payload['aud'] ?? null;
        $azp = $payload['azp'] ?? null;
        if (! $this->hasValidAudience($aud) && ! $this->hasValidAudience($azp)) {
            return null;
        }

        if (! $this->isNotExpired($payload['exp'] ?? null, $payload['expires_in'] ?? null)) {
            return null;
        }

        if (! $this->isEmailVerified($payload['email_verified'] ?? null)) {
            return null;
        }

        return $payload;
    }

    /**
     * Retrieve the list of allowed Google client IDs.
     *
     * @return list<string>
     */
    public function getAllowedClientIds(): array
    {
        $allowed = config('services.google.allowed_client_ids', []);
        $items = is_array($allowed) ? $allowed : [];

        $primary = config('services.google.client_id');
        if (is_string($primary) && $primary !== '' && ! in_array($primary, $items, true)) {
            $items[] = $primary;
        }

        return array_values($items);
    }

    private function hasValidAudience(mixed $audience): bool
    {
        if (! is_string($audience) || $audience === '') {
            return false;
        }

        return in_array($audience, $this->getAllowedClientIds(), true);
    }

    private function hasValidIssuer(mixed $issuer): bool
    {
        if (! is_string($issuer)) {
            return false;
        }

        return in_array($issuer, self::ALLOWED_ISSUERS, true);
    }

    private function isNotExpired(mixed $exp, mixed $expiresIn = null): bool
    {
        if ($exp !== null) {
            return is_numeric($exp) && (int) $exp > time();
        }

        if ($expiresIn !== null) {
            return is_numeric($expiresIn) && (int) $expiresIn > 0;
        }

        return false;
    }

    private function isEmailVerified(mixed $verified): bool
    {
        return $verified === true || $verified === 'true';
    }
}
