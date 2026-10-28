<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Bootstrap;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;

final class StoreSettingsBootstrapContributor implements BootstrapContributor
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function contribute(?Authenticatable $user): array
    {
        return [
            'settings' => [
                'site_name' => $this->settingService->get(SettingKey::SITE_NAME, config('app.name', 'Turbo Tenant')),
                'site_name_ar' => $this->settingService->get(SettingKey::SITE_NAME_AR),
                'site_description' => $this->settingService->get(SettingKey::SITE_DESCRIPTION),
                'site_logo' => $this->settingService->get(SettingKey::SITE_LOGO) ? Storage::url($this->settingService->get(SettingKey::SITE_LOGO)) : null,
                'svg_logo' => $this->settingService->get(SettingKey::SVG_LOGO) ? Storage::url($this->settingService->get(SettingKey::SVG_LOGO)) : null,
                'site_favicon' => $this->settingService->get(SettingKey::SITE_FAVICON) ? Storage::url($this->settingService->get(SettingKey::SITE_FAVICON)) : null,
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
                'product_show_cards' => json_decode($this->settingService->get(SettingKey::PRODUCT_SHOW_CARDS, '[]'), true) ?? [],
                'currency' => $this->settingService->get('currency', 'EGP'),
            ],
        ];
    }
}
