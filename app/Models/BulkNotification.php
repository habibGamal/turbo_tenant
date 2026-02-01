<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkNotificationStatus;
use Database\Factories\BulkNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class BulkNotification extends Model
{
    /** @use HasFactory<BulkNotificationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'data',
        'status',
        'target_user_ids',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'successful_sends',
        'failed_sends',
    ];

    public function isScheduled(): bool
    {
        return $this->status === BulkNotificationStatus::SCHEDULED;
    }

    public function isDraft(): bool
    {
        return $this->status === BulkNotificationStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === BulkNotificationStatus::SENT;
    }

    public function isSending(): bool
    {
        return $this->status === BulkNotificationStatus::SENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === BulkNotificationStatus::FAILED;
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [
            BulkNotificationStatus::DRAFT,
            BulkNotificationStatus::SCHEDULED,
            BulkNotificationStatus::FAILED,
        ]);
    }

    public function markAsSending(): void
    {
        $this->update(['status' => BulkNotificationStatus::SENDING]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => BulkNotificationStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => BulkNotificationStatus::FAILED]);
    }

    public function incrementSuccessful(): void
    {
        $this->increment('successful_sends');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_sends');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'target_user_ids' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'status' => BulkNotificationStatus::class,
        ];
    }
}
