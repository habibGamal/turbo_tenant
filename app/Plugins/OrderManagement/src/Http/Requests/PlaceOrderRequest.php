<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Requests;

use App\Core\Contracts\Customer;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\OrderManagement\src\Contracts\PlaceOrderRulesContributor;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(PluginContributions $contributions): array
    {
        $isDelivery = $this->input('type') === OrderType::WEB_DELIVERY->value;
        $user = Auth::user();

        $addressRules = ['prohibited'];
        if ($user !== null) {
            $addressExists = Rule::exists('addresses', 'id')->where('user_id', $user->getAuthIdentifier());
            if ($isDelivery) {
                $addressExists->where(fn (Builder $query) => $query->whereExists(
                    fn (Builder $areas) => $areas->selectRaw('1')->from('areas')
                        ->whereColumn('areas.id', 'addresses.area_id')->where('areas.is_active', true)
                ));
            }
            $addressRules = [$isDelivery ? 'required' : 'nullable', 'integer', $addressExists];
        }

        $rules = [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            // The storefront can't place POS orders.
            'type' => ['required', 'string', Rule::in([OrderType::WEB_DELIVERY->value, OrderType::WEB_TAKEAWAY->value])],
            'address_id' => $addressRules,
            'coupon_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        /** @var list<PlaceOrderRulesContributor> $ruleContributors */
        $ruleContributors = $contributions->active('order.placement.rules');
        foreach ($ruleContributors as $contributor) {
            $rules = array_merge($rules, $contributor->rules($this));
        }

        return $rules;
    }

    public function toPlaceOrderData(Customer $customer): PlaceOrderData
    {
        return new PlaceOrderData(
            user: $customer,
            branchId: (int) $this->validated('branch_id'),
            paymentMethod: PaymentMethod::from((string) $this->validated('payment_method')),
            addressId: $this->filled('address_id') ? (int) $this->validated('address_id') : null,
            couponId: $this->filled('coupon_id') ? (int) $this->validated('coupon_id') : null,
            note: $this->validated('note'),
            type: (string) $this->validated('type'),
            billingData: (array) $this->validated('billing_data', []),
        );
    }
}
