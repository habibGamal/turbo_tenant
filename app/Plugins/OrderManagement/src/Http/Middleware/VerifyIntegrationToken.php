<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Middleware;

use App\Plugins\OrderManagement\src\Services\IntegrationTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyIntegrationToken
{
    public function __construct(
        private readonly IntegrationTokenService $tokenService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();
        if ($bearerToken === null || $bearerToken === '') {
            return $this->unauthorizedResponse('Integration token is missing.');
        }

        $configuredToken = $this->tokenService->get();
        if ($configuredToken === null) {
            return $this->unauthorizedResponse('Integration token is not configured.');
        }

        if (! hash_equals($configuredToken, $bearerToken)) {
            return $this->unauthorizedResponse('Invalid integration token.');
        }

        return $next($request);
    }

    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
