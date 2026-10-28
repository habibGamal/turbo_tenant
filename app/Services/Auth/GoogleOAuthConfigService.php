<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;

final class GoogleOAuthConfigService
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * Apply tenant-specific Google OAuth credentials to Laravel configuration.
     */
    public function apply(): void
    {
        config([
            'services.google.client_id' => $this->settingService->get(SettingKey::GOOGLE_CLIENT_ID) ?: config('services.google.client_id'),
            'services.google.client_secret' => $this->settingService->get(SettingKey::GOOGLE_CLIENT_SECRET) ?: config('services.google.client_secret'),
            'services.google.redirect' => $this->settingService->get(SettingKey::GOOGLE_REDIRECT_URL) ?: config('services.google.redirect'),
        ]);
    }
}
