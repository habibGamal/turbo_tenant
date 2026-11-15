<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Models;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use Carbon\CarbonInterface;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'price',
        'allow_coupons',
        'original_price',
        'discount_percentage',
        'badge',
        'badge_ar',
        'icon',
        'gradient',
        'is_active',
        'is_featured',
        'sort_order',
        'valid_from',
        'valid_until',
        'allowed_order_types',
        'max_per_order',
        'daily_limit',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(PackageGroup::class)->orderBy('sort_order');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(PackageItem::class, PackageGroup::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PackageAvailability::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'package_branch');
    }

    public function scopeSellable(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at);
            });
    }

    protected static function newFactory()
    {
        return PackageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'price' => 'double',
            'allow_coupons' => 'boolean',
            'original_price' => 'double',
            'discount_percentage' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'allowed_order_types' => 'array',
            'max_per_order' => 'integer',
            'daily_limit' => 'integer',
        ];
    }
}
