<?php

declare(strict_types=1);

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'plugin.active' => App\Core\Plugins\Http\Middleware\EnsurePluginActive::class,
        ]);

        $middleware->web(append: [
            Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->encryptCookies(except: [
            '_fbp',
            '_fbc',
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
            'api/order-status',
        ]);

        $middleware->group('universal', [
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Storefront API errors use the same envelope as ApiResponse::error().
        $isApi = static fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiResponse::error($e->getMessage(), $e->status, $e->errors()) : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiResponse::error('Unauthenticated.', 401) : null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            // Don't leak model class names from "No query results for model [...]".
            $message = $e->getPrevious() instanceof ModelNotFoundException || $e->getMessage() === ''
                ? (SymfonyResponse::$statusTexts[$status] ?? 'Error')
                : $e->getMessage();

            return ApiResponse::error($message, $status)->withHeaders($e->getHeaders());
        });
    })->create();
