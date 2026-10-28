<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_events';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'source',
        'ip',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }
}
