<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

final readonly class CatalogItem
{
    public function __construct(
        public string $id,
        public ?string $itemGroupId,
        public string $title,
        public string $description,
        public string $availability,
        public string $condition,
        public string $price,
        public ?string $salePrice,
        public string $link,
        public string $imageLink,
        public string $brand,
        public string $productType,
        public string $customLabel0,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toFeedRow(): array
    {
        return [
            'id' => $this->id,
            'item_group_id' => $this->itemGroupId ?? '',
            'title' => $this->title,
            'description' => $this->description,
            'availability' => $this->availability,
            'condition' => $this->condition,
            'price' => $this->price,
            'sale_price' => $this->salePrice ?? '',
            'link' => $this->link,
            'image_link' => $this->imageLink,
            'brand' => $this->brand,
            'product_type' => $this->productType,
            'custom_label_0' => $this->customLabel0,
        ];
    }
}
