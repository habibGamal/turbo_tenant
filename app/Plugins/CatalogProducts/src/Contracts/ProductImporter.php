<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

use App\Plugins\CatalogProducts\src\Models\Product;

interface ProductImporter
{
    public function importProduct(ImportedProduct $product): Product;
}
