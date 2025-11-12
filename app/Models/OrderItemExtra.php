<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemExtra extends Model
{
    protected $fillable = [
        'order_item_id',
        'extra_name',
        'extra_price',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'extra_price' => 'double',
        ];
    }
}
