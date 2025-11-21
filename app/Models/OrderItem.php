<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'weight_option_value_id',
        'weight_multiplier',
        'product_name',
        'variant_name',
        'notes',
        'quantity',
        'unit_price',
        'total',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function weightOptionValue(): BelongsTo
    {
        return $this->belongsTo(WeightOptionValue::class, 'weight_option_value_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(OrderItemExtra::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'double',
            'total' => 'double',
            'weight_multiplier' => 'integer',
        ];
    }
}
