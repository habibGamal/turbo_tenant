<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payment_events';

    protected $fillable = [
        'gateway',
        'transaction_id',
        'merchant_order_id',
        'event_type',
        'amount_cents',
        'result',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }
}
