<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use App\Services\CartService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly SettingService $settingService,
        private readonly CartService $cartService,
    ) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if (tenant()) {
            return [
                ...parent::share($request),
                'auth' => [
                    'user' => $request->user(),
                ],
                'cartItemsCount' => $this->cartService->getCartCount($request->user()),
                'settings' => [
                    'site_name' => $this->settingService->get(SettingKey::SITE_NAME),
                    'site_name_ar' => $this->settingService->get(SettingKey::SITE_NAME_AR),
                    'site_description' => $this->settingService->get(SettingKey::SITE_DESCRIPTION),
                    'site_logo' => $this->settingService->get(SettingKey::SITE_LOGO) ? Storage::url($this->settingService->get(SettingKey::SITE_LOGO)) : null,
                    'image_placeholder' => $this->settingService->get(SettingKey::IMAGE_PLACEHOLDER),
                    'contact_email' => $this->settingService->get(SettingKey::CONTACT_EMAIL),
                    'contact_phone' => $this->settingService->get(SettingKey::CONTACT_PHONE),
                    'contact_address' => $this->settingService->get(SettingKey::CONTACT_ADDRESS),
                    'social_facebook' => $this->settingService->get(SettingKey::SOCIAL_FACEBOOK),
                    'social_instagram' => $this->settingService->get(SettingKey::SOCIAL_INSTAGRAM),
                    'social_twitter' => $this->settingService->get(SettingKey::SOCIAL_TWITTER),
                    'social_links' => json_decode($this->settingService->get(SettingKey::SOCIAL_LINKS, '[]'), true) ?? [],
                    'cod_fee' => (float) $this->settingService->get(SettingKey::COD_FEE, 0),
                    'online_payments_enabled' => $this->settingService->get(SettingKey::ONLINE_PAYMENTS_ENABLED, 'true') === 'true',
                    'facebook_app_id' => $this->settingService->get(SettingKey::FACEBOOK_APP_ID),
                    'product_show_cards' => json_decode($this->settingService->get(SettingKey::PRODUCT_SHOW_CARDS, '[]'), true),
                    'pages' => \App\Models\Page::where('is_active', true)->select('title', 'title_ar', 'slug')->get(),
                ],
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
        ];
    }
}
