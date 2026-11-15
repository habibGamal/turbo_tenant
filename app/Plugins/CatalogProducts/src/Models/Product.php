<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use App\Plugins\CatalogProducts\src\DTOs\SelectionRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property string|null $description
 * @property string|null $image
 * @property float $base_price
 * @property float|null $price_after_discount
 * @property float $price
 * @property int|null $extra_option_id
 * @property int|null $min_selections
 * @property int|null $max_selections
 * @property bool|null $allow_multiple
 * @property int|null $category_id
 * @property bool $is_active
 * @property bool $sell_by_weight
 * @property int|null $weight_options_id
 * @property int|null $variants_count
 * @property-read Category|null $category
 * @property-read ExtraOption|null $extraOption
 * @property-read WeightOption|null $weightOption
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductVariant> $variants
 */
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

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || mb_trim($term) === '') {
            return $query;
        }

        // "!" as the escape character: a backslash means different things in MySQL and SQLite string literals.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_trim($term));

        return $query->where(function (Builder $q) use ($escaped) {
            $pattern = "%{$escaped}%";
            $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', (array) $filters['category_ids']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $min = (float) $filters['min_price'];
            $query->whereRaw('COALESCE(price_after_discount, base_price) >= ?', [$min]);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $max = (float) $filters['max_price'];
            $query->whereRaw('COALESCE(price_after_discount, base_price) <= ?', [$max]);
        }

        return $query;
    }

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

    /**
     * Get the effective extra selection rules for this product.
     * Product-level values override the extra option's values.
     * When the product has no extra option it returns null.
     */
    public function effectiveSelectionRules(): ?SelectionRules
    {
        if ($this->extra_option_id === null || ! $this->extraOption) {
            return null;
        }

        $extraOption = $this->extraOption;

        return new SelectionRules(
            minSelections: $this->min_selections ?? $extraOption->min_selections,
            maxSelections: $this->max_selections ?? $extraOption->max_selections,
            allowMultiple: (bool) ($this->allow_multiple ?? $extraOption->allow_multiple),
        );
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
