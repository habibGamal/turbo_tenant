<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Rules;

use App\Plugins\OrderManagement\src\Contracts\PlaceOrderRulesContributor;
use Illuminate\Http\Request;

final class BillingDataRulesContributor implements PlaceOrderRulesContributor
{
    public function pluginId(): string
    {
        return 'payment-processing';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Request $request): array
    {
        return [
            'billing_data' => 'nullable|array',
            'billing_data.first_name' => 'nullable|string|max:255',
            'billing_data.last_name' => 'nullable|string|max:255',
            'billing_data.email' => 'nullable|email|max:255',
            'billing_data.phone_number' => 'nullable|string|max:20',
            'billing_data.apartment' => 'nullable|string|max:255',
            'billing_data.floor' => 'nullable|string|max:255',
            'billing_data.street' => 'nullable|string|max:255',
            'billing_data.building' => 'nullable|string|max:255',
            'billing_data.city' => 'nullable|string|max:255',
            'billing_data.country' => 'nullable|string|max:2',
            'billing_data.postal_code' => 'nullable|string|max:20',
        ];
    }
}
