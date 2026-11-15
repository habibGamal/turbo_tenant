<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use JsonSerializable;

final readonly class CatalogValidationReport implements JsonSerializable
{
    /**
     * @param  list<CatalogValidationIssue>  $issues
     */
    public function __construct(
        public int $totalProducts,
        public int $totalRows,
        public int $rowsSkipped,
        public array $issues,
    ) {}

    public function hasIssues(): bool
    {
        return ! empty($this->issues);
    }

    /**
     * @return list<CatalogValidationIssue>
     */
    public function issuesForField(string $field): array
    {
        return array_values(array_filter($this->issues, fn (CatalogValidationIssue $i) => $i->field === $field));
    }

    /**
     * @return array{total_products: int, total_rows: int, rows_skipped: int, issues: list<array{id: string, field: string, problem: string}>}
     */
    public function toArray(): array
    {
        return [
            'total_products' => $this->totalProducts,
            'total_rows' => $this->totalRows,
            'rows_skipped' => $this->rowsSkipped,
            'issues' => array_map(fn (CatalogValidationIssue $i) => $i->toArray(), $this->issues),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
