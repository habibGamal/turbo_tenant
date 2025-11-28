<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SettingKey;
use App\Services\SettingService;
use Illuminate\Support\Facades\Config;
use Stancl\Tenancy\Events\TenancyInitialized;

final class ConfigureTenantMail
{
    public function handle(TenancyInitialized $event): void
    {
        $settingService = app(SettingService::class);

        $mailer = $settingService->get(SettingKey::MAIL_MAILER);
        $host = $settingService->get(SettingKey::MAIL_HOST);
        $port = $settingService->get(SettingKey::MAIL_PORT);
        $username = $settingService->get(SettingKey::MAIL_USERNAME);
        $password = $settingService->get(SettingKey::MAIL_PASSWORD);
        $encryption = $settingService->get(SettingKey::MAIL_ENCRYPTION);
        $fromAddress = $settingService->get(SettingKey::MAIL_FROM_ADDRESS);
        $fromName = $settingService->get(SettingKey::MAIL_FROM_NAME);

        if ($mailer) {
            Config::set('mail.default', $mailer);
        }

        if ($host) {
            Config::set('mail.mailers.smtp.host', $host);
        }

        if ($port) {
            Config::set('mail.mailers.smtp.port', $port);
        }

        if ($username) {
            Config::set('mail.mailers.smtp.username', $username);
        }

        if ($password) {
            Config::set('mail.mailers.smtp.password', $password);
        }

        if ($encryption) {
            Config::set('mail.mailers.smtp.encryption', $encryption);
        }

        if ($fromAddress) {
            Config::set('mail.from.address', $fromAddress);
        }

        if ($fromName) {
            Config::set('mail.from.name', $fromName);
        }
    }
}
