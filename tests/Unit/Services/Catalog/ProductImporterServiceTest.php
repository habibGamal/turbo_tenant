<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Contracts\ImportedProduct;
use App\Plugins\CatalogProducts\src\Contracts\ProductImporter;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Services\ProductImporterService;

uses()->group('unit');

beforeEach(function () {
    $this->importer = new ProductImporterService();
});

describe('ProductImporterService', function () {
    it('implements ProductImporter interface', function () {
        expect($this->importer)->toBeInstanceOf(ProductImporter::class);
    });

    it('creates product and category when category does not exist', function () {
        expect(Category::count())->toBe(0);
        expect(Product::count())->toBe(0);

        $importedProduct = new ImportedProduct(
            name: 'Pizza Margherita',
            basePrice: 100.0,
            priceAfterDiscount: 90.0,
            categoryName: 'Pizza',
            categoryImage: 'pizza.jpg',
            image: '/images/custom-pizza.png',
        );

        $product = $this->importer->importProduct($importedProduct);

        expect($product)->toBeInstanceOf(Product::class)
            ->and($product->name)->toBe('Pizza Margherita')
            ->and((float) $product->base_price)->toBe(100.0)
            ->and((float) $product->price_after_discount)->toBe(90.0)
            ->and($product->image)->toBe('/images/custom-pizza.png')
            ->and($product->is_active)->toBeTrue()
            ->and($product->sell_by_weight)->toBeFalse()
            ->and($product->description)->toBeNull();

        $category = Category::query()->where('name', 'Pizza')->first();
        expect($category)->not->toBeNull()
            ->and($category->image)->toBe('pizza.jpg')
            ->and($category->is_active)->toBeTrue()
            ->and($product->category_id)->toBe($category->id);
    });

    it('reuses existing category by name', function () {
        $existingCategory = Category::query()->create([
            'name' => 'Pizza',
            'image' => 'existing.jpg',
            'is_active' => true,
        ]);

        $importedProduct = new ImportedProduct(
            name: 'Pepperoni Pizza',
            basePrice: 120.0,
            priceAfterDiscount: 110.0,
            categoryName: 'Pizza',
            categoryImage: 'new-image-ignored.jpg',
        );

        $product = $this->importer->importProduct($importedProduct);

        expect(Category::count())->toBe(1)
            ->and($product->category_id)->toBe($existingCategory->id);
    });

    it('falls back to default image and base price when optional fields are null', function () {
        $importedProduct = new ImportedProduct(
            name: 'Burger Classic',
            basePrice: 75.0,
            priceAfterDiscount: null,
            categoryName: 'Burgers',
        );

        $product = $this->importer->importProduct($importedProduct);

        expect($product->image)->toBe('/images/default-product.png')
            ->and((float) $product->price_after_discount)->toBe(75.0);
    });
});
