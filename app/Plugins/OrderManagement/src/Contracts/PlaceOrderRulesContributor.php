<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use Illuminate\Http\Request;

/**
 * Contributes validation rules for order placement.
 * Tag: 'order.placement.rules'
 */
interface PlaceOrderRulesContributor extends PluginContribution
{
    /**
     * @return array<string, mixed>
     */
    public function rules(Request $request): array;
}
