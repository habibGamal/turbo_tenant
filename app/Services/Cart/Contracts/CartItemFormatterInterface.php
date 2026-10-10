<?php

declare(strict_types=1);

namespace App\Services\Cart\Contracts;

use App\Models\Cart;
use App\Models\CartItem;

interface CartItemFormatterInterface
{
    public function formatItem(CartItem $item): array;

    public function formatCart(?Cart $cart): array;
}
