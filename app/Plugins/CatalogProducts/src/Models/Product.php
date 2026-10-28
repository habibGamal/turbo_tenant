<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'image',
        'base_price',
        'price_after_discount',
        'extra_option_id',
        'min_selections',
        'max_selections',
        'allow_multiple',
        'category_id',
        'is_active',
        'sell_by_weight',
        'weight_options_id',
    ];

    protected $appends = [
        'price',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function extraOption(): BelongsTo
    {
        return $this->belongsTo(ExtraOption::class);
    }

    public function weightOption(): BelongsTo
    {
        return $this->belongsTo(WeightOption::class, 'weight_options_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    public function getPriceAttribute()
    {
        return $this->price_after_discount ?? $this->base_price;
    }

    protected static function newFactory(): \Database\Factories\ProductFactory
    {
        return \Database\Factories\ProductFactory::new();
    }

    protected function casts(): array
    {
        return [
            'base_price' => 'float',
            'price_after_discount' => 'float',
            'is_active' => 'boolean',
            'sell_by_weight' => 'boolean',
            'min_selections' => 'integer',
            'max_selections' => 'integer',
            'allow_multiple' => 'boolean',
        ];
    }
}
