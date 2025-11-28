<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderPosStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'merchant_order_id',
        'transaction_id',
        'paymob_order_id',
        'shift_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_data',
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
        'pos_status',
        'pos_failure_reason',
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
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'pos_status' => OrderPosStatus::class,
        ];
    }
}
