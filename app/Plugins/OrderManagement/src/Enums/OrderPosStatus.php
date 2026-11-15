<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderPosStatus: string implements HasColor, HasIcon, HasLabel
{
    case NOT_READY = 'not_ready';
    case PENDING = 'pending';
    case READY = 'ready';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case RETRY_WAIT = 'retry_wait';
    case NEEDS_VERIFICATION = 'needs_verification';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_READY => 'غير جاهز',
            self::PENDING => 'قيد الانتظار',
            self::READY => 'جاهز للإرسال',
            self::QUEUED => 'في قائمة الإرسال',
            self::SENDING => 'جارِ الإرسال',
            self::RETRY_WAIT => 'بانتظار إعادة المحاولة',
            self::NEEDS_VERIFICATION => 'تحتاج مراجعة مع الفرع',
            self::SENT => 'تم الإرسال',
            self::FAILED => 'فشل الإرسال',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NOT_READY => 'gray',
            self::PENDING => 'gray',
            self::READY => 'info',
            self::QUEUED => 'info',
            self::SENDING => 'warning',
            self::RETRY_WAIT => 'warning',
            self::NEEDS_VERIFICATION => 'danger',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NOT_READY => 'heroicon-o-minus-circle',
            self::PENDING => 'heroicon-o-clock',
            self::READY => 'heroicon-o-arrow-up-tray',
            self::QUEUED => 'heroicon-o-clock',
            self::SENDING => 'heroicon-o-arrow-path',
            self::RETRY_WAIT => 'heroicon-o-clock',
            self::NEEDS_VERIFICATION => 'heroicon-o-exclamation-triangle',
            self::SENT => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }
}
