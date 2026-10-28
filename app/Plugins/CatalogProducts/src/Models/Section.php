<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_ar',
        'location',
        'is_active',
        'sort_order',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'section_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    protected static function newFactory(): \Database\Factories\SectionFactory
    {
        return \Database\Factories\SectionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
