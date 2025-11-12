<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Section extends Model
{
    protected $fillable = [
        'title',
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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
