<?php

declare(strict_types=1);

namespace App\Models;

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
        'description',
        'image',
        'base_price',
        'price_after_discount',
        'extra_option_id',
        'category_id',
        'is_active',
        'sell_by_weight',
        'weight_options_id',
        'single_pos_ref',
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

    public function posMappings(): HasMany
    {
        return $this->hasMany(ProductPosMapping::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'base_price' => 'double',
            'price_after_discount' => 'double',
            'is_active' => 'boolean',
            'sell_by_weight' => 'boolean',
        ];
    }
}
