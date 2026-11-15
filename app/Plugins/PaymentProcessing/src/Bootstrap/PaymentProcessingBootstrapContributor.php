<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Bootstrap;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use Illuminate\Contracts\Auth\Authenticatable;

final class PaymentProcessingBootstrapContributor implements BootstrapContributor
{
    public function __construct(
        private readonly SettingsRepository $settingService,
    ) {}

    public function pluginId(): string
    {
        return 'payment-processing';
    }

    public function contribute(?Authenticatable $user): array
    {
        return [
            'settings' => [
                'cod_fee' => (float) $this->settingService->get(PaymentSettingKey::COD_FEE, 0),
                'online_payments_enabled' => (bool) $this->settingService->get(PaymentSettingKey::ONLINE_PAYMENTS_ENABLED, true),
            ],
        ];
    }
}
