<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Models;

use App\Core\Plugins\Contracts\LineReferenceCatalog;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Enums\DiscountType;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

/**
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property DiscountType $type
 * @property float $value
 * @property \Carbon\Carbon|null $expiry_date
 * @property bool $is_active
 * @property int|null $max_usage
 * @property int $usage_count
 * @property float $total_consumed
 * @property array<string, mixed> $conditions
 */
final class Coupon extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'expiry_date',
        'is_active',
        'max_usage',
        'usage_count',
        'total_consumed',
        'conditions',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get default conditions structure
     */
    public function getDefaultConditions(): array
    {
        return [
            'min_order_total' => null,
            'max_order_total' => null,
            'applicable_to' => [
                'type' => 'all', // all, products, categories, line_types
                'line_type' => null,
                'ids' => [],
                'product_ids' => [],
                'category_ids' => [],
            ],
            'shipping' => [
                'free_shipping' => false,
                'free_shipping_threshold' => null,
                'applicable_governorates' => [],
                'applicable_areas' => [],
            ],
            'usage_restrictions' => [
                'first_order_only' => false,
                'user_specific' => false,
                'user_ids' => [],
            ],
            'valid_days' => null, // null means all days, or [0,1,2,3,4,5,6] for specific days
            'valid_hours' => [
                'start' => null,
                'end' => null,
            ],
        ];
    }

    /**
     * Get merged conditions with defaults
     */
    public function getConditionsAttribute($value): array
    {
        $conditions = $value ? json_decode($value, true) : [];

        return array_replace_recursive($this->getDefaultConditions(), $conditions);
    }

    public function getApplicableToLabelAttribute(): string
    {
        $applicableTo = ApplicableTo::fromConditions($this->conditions);

        if ($applicableTo === ApplicableTo::LINE_TYPES) {
            if (tenant() !== null) {
                try {
                    /** @var PluginContributions $contributions */
                    $contributions = app(PluginContributions::class);
                    /** @var list<LineReferenceCatalog> $providers */
                    $providers = $contributions->active('catalog.line-references');
                    $targetLineType = (string) ($this->conditions['applicable_to']['line_type'] ?? 'package');
                    foreach ($providers as $provider) {
                        if ($provider->lineType() === $targetLineType) {
                            return $provider->label();
                        }
                    }
                    if (! empty($providers)) {
                        return $providers[0]->label();
                    }
                } catch (Throwable) {
                }
            }

            return 'باقات التوفير';
        }

        return match ($applicableTo) {
            ApplicableTo::ALL => 'جميع المنتجات',
            ApplicableTo::PRODUCTS => 'منتجات محددة',
            ApplicableTo::CATEGORIES => 'فئات معينة',
            default => 'غير محدد',
        };
    }

    protected static function newFactory()
    {
        return \Database\Factories\CouponFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'double',
            'expiry_date' => 'datetime',
            'is_active' => 'boolean',
            'max_usage' => 'integer',
            'usage_count' => 'integer',
            'total_consumed' => 'double',
            'conditions' => 'array',
        ];
    }
}
