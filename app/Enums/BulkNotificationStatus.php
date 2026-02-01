<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkNotificationStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::SCHEDULED => 'مجدولة',
            self::SENDING => 'جاري الإرسال',
            self::SENT => 'تم الإرسال',
            self::FAILED => 'فشل',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'info',
            self::SENDING => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }
}
