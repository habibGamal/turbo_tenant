<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    protected $fillable = [
        'order_number',
        'shift_id',
        'status',
        'type',
        'sub_total',
        'tax',
        'service',
        'delivery_fee',
        'discount',
        'total',
        'note',
        'user_id',
        'coupon_id',
        'branch_id',
        'address_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'sub_total' => 'double',
            'tax' => 'double',
            'service' => 'double',
            'delivery_fee' => 'double',
            'discount' => 'double',
            'total' => 'double',
        ];
    }
}
