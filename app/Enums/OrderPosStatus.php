<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderPosStatus: string
{
    case NOT_READY = 'not_ready';
    case READY = 'ready';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
