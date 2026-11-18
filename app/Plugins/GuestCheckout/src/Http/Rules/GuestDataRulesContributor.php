<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Rules;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Contracts\PlaceOrderRulesContributor;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GuestDataRulesContributor implements PlaceOrderRulesContributor
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function pluginId(): string
    {
        return 'guest-checkout';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Request $request): array
    {
        if ($request->user() !== null) {
            return [];
        }

        $isDelivery = $request->input('type') === OrderType::WEB_DELIVERY->value;
        $simplified = filter_var($this->settings->get('simplified_address_enabled', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'guest_data' => 'required|array',
            'guest_data.name' => 'required|string|max:255',
            'guest_data.phone' => 'required|string|max:20',
            'guest_data.phone_country_code' => 'nullable|string|max:5',
            'guest_data.email' => 'nullable|email|max:255',
            'guest_data.street' => ($isDelivery && $simplified) ? 'required|string|max:255' : 'nullable|string|max:255',
            'guest_data.building' => 'nullable|string|max:255',
            'guest_data.floor' => 'nullable|string|max:255',
            'guest_data.apartment' => 'nullable|string|max:255',
            'guest_data.city' => 'nullable|string|max:255',
            'guest_data.area_id' => [$isDelivery ? 'required' : 'nullable', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
        ];
    }
}
