<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderPosDeliveryAttempt extends Model
{
    protected $connection = 'tenant';

    protected $guarded = ['id'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(OrderPosDelivery::class, 'delivery_id');
    }

    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            'sending' => 'جارِ الإرسال',
            'sent' => 'تم الإرسال',
            'deferred' => 'الفرع لا يقبل الطلبات حالياً',
            'failed' => 'فشلت المحاولة',
            'uncertain' => 'تحتاج مراجعة مع الفرع',
            'verified_sent' => 'تم تأكيد الاستلام يدوياً',
            'verified_not_sent' => 'تم تأكيد عدم الاستلام يدوياً',
            default => $this->outcome,
        };
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
