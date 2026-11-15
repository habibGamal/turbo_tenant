<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class TimeWindow
{
    public function __construct(
        public string $start,
        public string $end,
    ) {}

    public static function fromStrings(string $start, string $end): self
    {
        return new self($start, $end);
    }

    public function isOvernight(): bool
    {
        return self::parseToSeconds($this->start) > self::parseToSeconds($this->end);
    }

    public function containsBeforeMidnight(CarbonInterface $at): bool
    {
        if (! $this->isOvernight()) {
            return false;
        }

        $startSeconds = self::parseToSeconds($this->start);
        $atSeconds = self::parseToSeconds($at->format('H:i:s'));

        return $atSeconds >= $startSeconds;
    }

    public function containsAfterMidnight(CarbonInterface $at): bool
    {
        if (! $this->isOvernight()) {
            return false;
        }

        $endSeconds = self::parseToSeconds($this->end);
        $atSeconds = self::parseToSeconds($at->format('H:i:s'));

        return $atSeconds <= $endSeconds;
    }

    public function contains(CarbonInterface $at): bool
    {
        $startSeconds = self::parseToSeconds($this->start);
        $endSeconds = self::parseToSeconds($this->end);
        $atSeconds = self::parseToSeconds($at->format('H:i:s'));

        if ($startSeconds <= $endSeconds) {
            return $atSeconds >= $startSeconds && $atSeconds <= $endSeconds;
        }

        return $atSeconds >= $startSeconds || $atSeconds <= $endSeconds;
    }

    private static function parseToSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', mb_trim($time)));
        $hours = $parts[0];
        $minutes = $parts[1] ?? 0;
        $seconds = $parts[2] ?? 0;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
