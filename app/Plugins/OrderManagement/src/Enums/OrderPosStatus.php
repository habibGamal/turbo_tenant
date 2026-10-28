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
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_READY => 'غير جاهز',
            self::PENDING => 'قيد الانتظار',
            self::READY => 'جاهز للإرسال',
            self::SENDING => 'جارِ الإرسال',
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
            self::SENDING => 'warning',
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
            self::SENDING => 'heroicon-o-arrow-path',
            self::SENT => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }
}
