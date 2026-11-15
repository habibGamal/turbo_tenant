<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use App\Plugins\CatalogProducts\src\Models\Product;

interface ProductDetailsContributor extends PluginContribution
{
    /**
     * @return array<string, mixed>
     */
    public function contribute(Product $product): array;
}
