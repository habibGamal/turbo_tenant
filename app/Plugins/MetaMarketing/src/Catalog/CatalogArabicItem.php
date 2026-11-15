<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

final readonly class CatalogArabicItem
{
    public function __construct(
        public string $id,
        public string $override,
        public string $title,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toFeedRow(): array
    {
        return [
            'id' => $this->id,
            'override' => $this->override,
            'title' => $this->title,
        ];
    }
}
