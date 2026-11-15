<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Filament\Checklist;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use Throwable;

final class LogoSetupChecklistItem implements SetupChecklistItem
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function key(): string
    {
        return 'site_logo';
    }

    public function label(): string
    {
        return 'شعار المتجر';
    }

    public function description(): string
    {
        return 'قم بإعداد شعار المتجر للظهور في واجهة المتجر والفواتير';
    }

    public function isComplete(): bool
    {
        try {
            $logo = $this->settings->get('site_logo');

            return ! empty($logo);
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return Settings::getUrl();
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 10;
    }
}
