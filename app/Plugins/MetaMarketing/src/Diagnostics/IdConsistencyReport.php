<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Diagnostics;

final readonly class IdConsistencyReport
{
    /**
     * @param  list<string>  $missingIds
     */
    public function __construct(
        public int $totalOrdersChecked,
        public int $totalContentIdsChecked,
        public int $matchedCount,
        public float $matchPercentage,
        public array $missingIds,
    ) {}

    public function isPerfectMatch(): bool
    {
        return empty($this->missingIds);
    }

    /**
     * @return array{total_orders: int, total_ids: int, matched: int, percentage: float, missing_ids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'total_orders' => $this->totalOrdersChecked,
            'total_ids' => $this->totalContentIdsChecked,
            'matched' => $this->matchedCount,
            'percentage' => $this->matchPercentage,
            'missing_ids' => $this->missingIds,
        ];
    }
}
