<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $order_number
 * @property string|null $merchant_order_id
 * @property string|null $transaction_id
 * @property string|null $paymob_order_id
 * @property int|null $shift_id
 * @property OrderStatus|string $status
 * @property PaymentStatus|string $payment_status
 * @property PaymentMethod|string $payment_method
 * @property array<string, mixed>|null $payment_data
 * @property OrderType|string $type
 * @property float $sub_total
 * @property float $tax
 * @property float $service
 * @property float $delivery_fee
 * @property float $discount
 * @property float $total
 * @property string|null $note
 * @property int|null $user_id
 * @property int|null $guest_user_id
 * @property int|null $coupon_id
 * @property int $branch_id
 * @property int|null $address_id
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property array<string, mixed>|null $delivery_address
 * @property string|null $coupon_code
 * @property OrderPosStatus|string|null $pos_status
 * @property string|null $pos_failure_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrderItem> $items
 */
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
        'guest_user_id',
        'coupon_id',
        'branch_id',
        'address_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'coupon_code',
        'pos_status',
        'pos_failure_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'payment_data',
        'transaction_id',
        'merchant_order_id',
        'paymob_order_id',
        'shift_id',
        'pos_failure_reason',
    ];

    /**
     * @return array<int, string>
     */
    public static function customerRelations(): array
    {
        return ['user'];
    }

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
     * Get the customer name (from snapshot or User)
     */
    public function getCustomerName(): string
    {
        $userName = $this->user?->customerName() ?? ($this->user?->getAttribute('name'));

        return $this->customer_name ?? (is_string($userName) ? $userName : 'Unknown');
    }

    /**
     * Get the customer phone (from snapshot or User)
     */
    public function getCustomerPhone(): ?string
    {
        $userPhone = $this->user?->customerPhone();

        return $this->customer_phone ?? (is_string($userPhone) ? $userPhone : null);
    }

    /**
     * Get the customer email (from snapshot or User)
     */
    public function getCustomerEmail(): ?string
    {
        $userEmail = $this->user?->customerEmail() ?? ($this->user?->getAttribute('email'));

        return $this->customer_email ?? (is_string($userEmail) ? $userEmail : null);
    }

    protected static function newFactory()
    {
        return \Database\Factories\OrderFactory::new();
    }

    protected static function booted(): void
    {
        self::updated(function (self $order) {
            if ($order->wasChanged('status')) {
                $previous = $order->getOriginal('status');
                $previousStatus = $previous instanceof OrderStatus ? $previous : ($previous ? OrderStatus::tryFrom((string) $previous) : null);
                \App\Plugins\OrderManagement\src\Events\OrderStatusUpdated::dispatch($order, $previousStatus);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'type' => OrderType::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'pos_status' => OrderPosStatus::class,
            'payment_data' => 'array',
            'delivery_address' => 'array',
            'sub_total' => 'float',
            'tax' => 'float',
            'service' => 'float',
            'delivery_fee' => 'float',
            'discount' => 'float',
            'total' => 'float',
        ];
    }
}
