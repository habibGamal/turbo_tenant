<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Support\MetaContentId;

/**
 * Checks the catalog the feed would publish. Row checks run on the rows CatalogItemBuilder generates,
 * so the report never disagrees with the feed itself.
 */
final class CatalogFeedValidator
{
    public const string PROBLEM_MISSING_IMAGE = 'Product is missing an image';

    public const string PROBLEM_MISSING_BRAND = 'Catalog brand is missing';

    public const string PROBLEM_DUPLICATE_ID = 'Duplicate product ID';

    public const string PROBLEM_EMPTY_TITLE = 'Title is empty';

    public const string PROBLEM_LONG_TITLE = 'Title exceeds 200 characters';

    public const string PROBLEM_MISSING_PRICE = 'Price is missing or zero';

    public const string PROBLEM_SALE_NOT_BELOW_PRICE = 'Sale price is greater than or equal to price';

    public const string PROBLEM_DISCOUNT_ABOVE_BASE = 'Discount price is higher than the base price';

    public function __construct(
        private readonly MetaMarketingConfig $config,
        private readonly CatalogItemBuilder $itemBuilder,
    ) {}

    public function validate(): CatalogValidationReport
    {
        $totalProducts = Product::query()->count();
        $totalRows = 0;
        $rowsSkipped = 0;

        /** @var list<CatalogValidationIssue> $issues */
        $issues = [];
        /** @var array<string, int> $seenIds */
        $seenIds = [];

        if (mb_trim($this->config->catalogBrand) === '') {
            $issues[] = new CatalogValidationIssue('catalog', 'brand', self::PROBLEM_MISSING_BRAND);
        }

        Product::query()
            ->with(['variants', 'category'])
            ->chunkById(100, function ($products) use (&$totalRows, &$rowsSkipped, &$issues, &$seenIds): void {
                foreach ($products as $product) {
                    $variants = $product->variants;
                    $rowCount = $variants->isNotEmpty() ? $variants->count() : 1;

                    if ($this->itemBuilder->resolveImageUrl($product->image) === null) {
                        $rowsSkipped += $rowCount;
                        $issues[] = new CatalogValidationIssue(MetaContentId::for($product->id), 'image', self::PROBLEM_MISSING_IMAGE);

                        continue;
                    }

                    $totalRows += $rowCount;

                    // Source data the cart charges as-is but that is almost certainly a typo.
                    if ($product->price_after_discount !== null && (float) $product->price_after_discount > (float) $product->base_price) {
                        $issues[] = new CatalogValidationIssue(MetaContentId::for($product->id), 'price_after_discount', self::PROBLEM_DISCOUNT_ABOVE_BASE);
                    }

                    foreach ($this->itemBuilder->buildForProduct($product) as $item) {
                        array_push($issues, ...$this->rowIssues($item, $seenIds, $this->untruncatedTitle($product, $item)));
                    }
                }
            });

        return new CatalogValidationReport(
            totalProducts: $totalProducts,
            totalRows: $totalRows,
            rowsSkipped: $rowsSkipped,
            issues: $issues,
        );
    }

    /**
     * @return list<array<string, string>>
     */
    public function previewRows(int $limit = 20): array
    {
        if ($limit <= 0) {
            $limit = 20;
        }

        /** @var list<array<string, string>> $rows */
        $rows = [];

        Product::query()
            ->with(['variants', 'category'])
            ->chunkById(50, function ($products) use (&$rows, $limit): bool {
                foreach ($products as $product) {
                    foreach ($this->itemBuilder->buildForProduct($product) as $item) {
                        $rows[] = $item->toFeedRow();
                        if (count($rows) >= $limit) {
                            return false;
                        }
                    }
                }

                return true;
            });

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param  array<string, int>  $seenIds
     * @return list<CatalogValidationIssue>
     */
    private function rowIssues(CatalogItem $item, array &$seenIds, string $fullTitle): array
    {
        $issues = [];

        $seenIds[$item->id] = ($seenIds[$item->id] ?? 0) + 1;
        if ($seenIds[$item->id] === 2) {
            $issues[] = new CatalogValidationIssue($item->id, 'id', self::PROBLEM_DUPLICATE_ID);
        }

        if (mb_trim($fullTitle) === '' || str_starts_with(mb_trim($fullTitle), '-') || str_ends_with(mb_trim($fullTitle), '-')) {
            $issues[] = new CatalogValidationIssue($item->id, 'title', self::PROBLEM_EMPTY_TITLE);
        } elseif (mb_strlen($fullTitle) > 200) {
            $issues[] = new CatalogValidationIssue($item->id, 'title', self::PROBLEM_LONG_TITLE);
        }

        $price = $this->amount($item->price);
        if ($price <= 0) {
            $issues[] = new CatalogValidationIssue($item->id, 'price', self::PROBLEM_MISSING_PRICE);
        }

        if ($item->salePrice !== null && $this->amount($item->salePrice) >= $price) {
            $issues[] = new CatalogValidationIssue($item->id, 'sale_price', self::PROBLEM_SALE_NOT_BELOW_PRICE);
        }

        return $issues;
    }

    private function untruncatedTitle(Product $product, CatalogItem $item): string
    {
        if ($item->itemGroupId === null) {
            return mb_trim((string) $product->name);
        }

        $variant = $product->variants->first(fn ($variant): bool => MetaContentId::for($product->id, $variant->id) === $item->id);

        return mb_trim((string) $product->name).' - '.mb_trim((string) $variant?->name);
    }

    /**
     * "150.00 EGP" -> 150.0
     */
    private function amount(string $money): float
    {
        return (float) strtok($money, ' ');
    }
}
