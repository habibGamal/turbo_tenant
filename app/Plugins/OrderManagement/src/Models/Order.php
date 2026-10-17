<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\OrderFactory::new();
    }

    protected static function booted(): void
    {
        static::created(function (self $order) {
            \App\Plugins\OrderManagement\src\Events\OrderCreated::dispatch($order);
        });

        static::updated(function (self $order) {
            if ($order->wasChanged('status')) {
                $previous = $order->getOriginal('status');
                $previousStatus = $previous instanceof OrderStatus ? $previous : ($previous ? OrderStatus::tryFrom((string) $previous) : null);
                \App\Plugins\OrderManagement\src\Events\OrderStatusUpdated::dispatch($order, $previousStatus);
            }
        });
    }

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
        'guest_user_id',
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

    /**
     * Check if this is a guest order
     */
    public function isGuestOrder(): bool
    {
        return $this->guest_user_id !== null;
    }

    /**
     * Get the customer name (from User or dynamically resolved GuestUser)
     */
    public function getCustomerName(): string
    {
        return $this->user?->name ?? $this->guestUser?->name ?? 'Unknown';
    }

    /**
     * Get the customer phone (from User or dynamically resolved GuestUser)
     */
    public function getCustomerPhone(): ?string
    {
        return $this->user?->phone ?? $this->guestUser?->phone;
    }

    /**
     * Get the customer email (from User or dynamically resolved GuestUser)
     */
    public function getCustomerEmail(): ?string
    {
        return $this->user?->email ?? $this->guestUser?->email;
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'pos_status' => class_exists(OrderPosStatus::class) ? OrderPosStatus::class : 'string',
            'payment_data' => 'array',
            'sub_total' => 'double',
            'tax' => 'double',
            'service' => 'double',
            'delivery_fee' => 'double',
            'discount' => 'double',
            'total' => 'double',
        ];
    }
}
