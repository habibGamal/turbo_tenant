<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'name_ar',
        'price',
        'is_available',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
