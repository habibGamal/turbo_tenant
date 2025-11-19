<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CartItemExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_item_id',
        'extra_option_item_id',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function extraOptionItem(): BelongsTo
    {
        return $this->belongsTo(ExtraOptionItem::class);
    }
}
