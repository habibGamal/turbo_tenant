<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Models;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property string $generation
 * @property OrderPosStatus $status
 * @property string $trigger
 * @property int|null $requested_by
 * @property string|null $requested_by_name
 * @property bool $history_complete
 * @property int $attempts_count
 * @property int $failures_count
 * @property int $run_attempts_count
 * @property int $long_retry_round
 * @property \Carbon\CarbonImmutable|null $queued_at
 * @property \Carbon\CarbonImmutable|null $last_attempt_at
 * @property \Carbon\CarbonImmutable|null $next_retry_at
 * @property \Carbon\CarbonImmutable|null $unreachable_since
 * @property \Carbon\CarbonImmutable|null $sent_at
 * @property string|null $last_error
 */
final class OrderPosDelivery extends Model
{
    protected $connection = 'tenant';

    protected $guarded = ['id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OrderPosDeliveryAttempt::class, 'delivery_id');
    }

    public function isOverdue(): bool
    {
        $expectedAt = match ($this->status) {
            OrderPosStatus::QUEUED => $this->queued_at,
            OrderPosStatus::SENDING => $this->last_attempt_at,
            OrderPosStatus::RETRY_WAIT => $this->next_retry_at,
            default => null,
        };

        return $expectedAt !== null && $expectedAt->lt(now()->subMinutes(5));
    }

    protected function casts(): array
    {
        return [
            'status' => OrderPosStatus::class,
            'history_complete' => 'boolean',
            'attempts_count' => 'integer',
            'failures_count' => 'integer',
            'run_attempts_count' => 'integer',
            'long_retry_round' => 'integer',
            'queued_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'next_retry_at' => 'immutable_datetime',
            'unreachable_since' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
