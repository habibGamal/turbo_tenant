<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Support\OrderManagementSettings;
use Illuminate\Support\Str;

final class IntegrationTokenService
{
    public function __construct(
        private readonly SettingsRepository $settingService
    ) {}

    public function generate(): string
    {
        $newToken = Str::random(48);
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $newToken);

        return $newToken;
    }

    public function get(): ?string
    {
        $token = $this->settingService->get(OrderManagementSettings::INTEGRATION_TOKEN_KEY);

        return (is_string($token) && $token !== '') ? $token : null;
    }

    public function masked(): ?string
    {
        $token = $this->get();
        if ($token === null) {
            return null;
        }

        return Str::mask($token, '*', 4, -4);
    }

    public function isValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $configuredToken = $this->get();
        if ($configuredToken === null) {
            return false;
        }

        return hash_equals($configuredToken, $token);
    }
}
