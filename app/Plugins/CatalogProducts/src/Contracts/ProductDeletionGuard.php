<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use App\Plugins\CatalogProducts\src\Models\Product;

interface ProductDeletionGuard extends PluginContribution
{
    public function blockingReason(Product $product): ?string;
}
