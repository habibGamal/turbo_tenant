<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Plugins\CartEngine\src\Models\CartItem;

interface CartPriceCalculatorInterface
{
    public function getEffectivePrice(CartItem $item): float;

    public function calculateExtrasTotal(CartItem $item): float;

    public function calculateFinalQuantity(CartItem $item): float;

    public function calculateSubtotal(CartItem $item): float;

    public function calculateTotal(array $items): float;
}
