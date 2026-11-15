<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Models;

use App\Plugins\OrderManagement\src\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string|null $fbp
 * @property string|null $fbc
 * @property string|null $client_ip
 * @property string|null $user_agent
 * @property string|null $event_source_url
 * @property string|null $purchase_event_id
 * @property CarbonInterface|null $purchase_sent_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order|null $order
 */
final class MetaOrderTracking extends Model
{
    protected $table = 'meta_order_tracking';

    protected $fillable = [
        'order_id',
        'fbp',
        'fbc',
        'client_ip',
        'user_agent',
        'event_source_url',
        'purchase_event_id',
        'purchase_sent_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'purchase_sent_at' => 'datetime',
        ];
    }
}
