<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Models;

use App\Plugins\OrderManagement\src\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $event_name
 * @property string $event_id
 * @property int|null $order_id
 * @property string $status
 * @property int $attempts
 * @property int|null $http_status
 * @property string|null $fbtrace_id
 * @property int|null $events_received
 * @property string|null $error
 * @property array<string, mixed> $payload
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order|null $order
 */
final class MetaCapiEvent extends Model
{
    use Prunable;

    protected $table = 'meta_capi_events';

    protected $fillable = [
        'event_name',
        'event_id',
        'order_id',
        'status',
        'attempts',
        'http_status',
        'fbtrace_id',
        'events_received',
        'error',
        'payload',
        'sent_at',
    ];

    /**
     * Get the prunable model query (rows older than 30 days).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return self::where('created_at', '<=', now()->subDays(30));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'attempts' => 'integer',
            'http_status' => 'integer',
            'events_received' => 'integer',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
