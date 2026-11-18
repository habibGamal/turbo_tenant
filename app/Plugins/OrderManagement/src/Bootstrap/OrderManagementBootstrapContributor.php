<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Bootstrap;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use Illuminate\Contracts\Auth\Authenticatable;

final class OrderManagementBootstrapContributor implements BootstrapContributor
{
    public function __construct(
        private readonly SettingsRepository $settingService,
        private readonly OrderManagementConfig $config,
    ) {}

    public function pluginId(): string
    {
        return 'order-management';
    }

    public function contribute(?Authenticatable $user): array
    {
        return [
            'settings' => [
                'min_order_amount' => (float) $this->settingService->get(OrderSettingKey::MIN_ORDER_AMOUNT, 0),
                'free_shipping_threshold' => $this->config->getFreeShippingThreshold(),
                'simplified_address_enabled' => $this->config->isSimplifiedAddressEnabled(),
            ],
        ];
    }
}
