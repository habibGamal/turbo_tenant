<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Support;

final class PosRetrySchedule
{
    /**
     * Cumulative 24-hour delayed retry intervals in seconds: 1h, 2h, 4h, 8h, 9h.
     *
     * @var list<int>
     */
    public const DELAYS = [3600, 7200, 14400, 28800, 32400];

    public const EXHAUSTED_REASON = 'تعذر الوصول إلى الفرع لمدة 24 ساعة. تأكد من تشغيل نقطة البيع ثم أعد الإرسال يدوياً.';

    public static function maxRounds(): int
    {
        return count(self::DELAYS);
    }

    public static function delayForNextRound(int $currentRound): ?int
    {
        return self::DELAYS[$currentRound] ?? null;
    }
}
