<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SectionProduct extends Pivot
{
    protected $fillable = [
        'section_id',
        'product_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
