<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, CartService $cartService): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        logger()->info('LoginRequest',$request->all());    
        // Sync guest cart to authenticated user
        $cartService->syncGuestCartToUser($request->user());

        if ($request->filled('expo_token')) {
            // $validator = \Illuminate\Support\Facades\Validator::make($request->only('expo_token'), [
            //     'expo_token' => \NotificationChannels\Expo\ExpoPushToken::rule(),
            // ]);
            logger()->info($request->expo_token);
            // if ($validator->passes()) {
                $request->user()->update(['expo_token' => $request->expo_token]);
            // }
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
