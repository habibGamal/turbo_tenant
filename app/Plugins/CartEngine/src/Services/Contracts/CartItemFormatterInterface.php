<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;

interface CartItemFormatterInterface
{
    public function formatItem(CartItem $item): array;

    public function formatCart(?Cart $cart): array;
}
