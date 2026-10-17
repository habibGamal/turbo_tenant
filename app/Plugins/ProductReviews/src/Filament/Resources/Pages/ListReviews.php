<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament\Resources\Pages;

use App\Plugins\ProductReviews\src\Filament\Resources\ReviewResource;
use Filament\Resources\Pages\ListRecords;

final class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;
}
