<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\PreparedCartLine;
use App\Plugins\CartEngine\src\Models\CartItem;

/**
 * Lets another plugin sell something other than a plain product as a cart line
 * (e.g. combo-packages sells "package" lines) without CartEngine depending on it.
 *
 * Handlers are collected through PluginContributions under the tag
 * 'cart-engine.line-types'; a line whose handler is not active is dropped from the cart.
 */
interface CartLineTypeHandler extends PluginContribution
{
    /** The cart_items.line_type value this handler owns, e.g. 'package'. */
    public function type(): string;

    /**
     * Validate a client add-to-cart payload and resolve it into what gets persisted.
     * Must rebuild every price and component from the database; never trust client prices.
     *
     * @param  array<string, mixed>  $payload  the request body minus type and notes; 'quantity' is the line quantity being added
     *
     * @throws \Illuminate\Validation\ValidationException with messages keyed by payload path
     */
    public function prepare(array $payload): PreparedCartLine;

    /**
     * Build the priced line from a persisted item (components eager-loaded).
     * Called on every cart read, so prices always reflect the current catalog.
     */
    public function buildLine(CartItem $item): CartLine;

    /**
     * Re-check that a persisted line can still be bought right now.
     * Returns null when it can, or a customer-facing reason when it cannot.
     */
    public function unavailableReason(CartItem $item): ?string;
}
