<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ExtraOptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_option_id',
        'name',
        'price',
        'is_default',
        'sort_order',
        'pos_mapping_type',
        'allow_quantity',
    ];

    public function extraOption(): BelongsTo
    {
        return $this->belongsTo(ExtraOption::class);
    }

    public function posMappings(): HasMany
    {
        return $this->hasMany(ProductPosMapping::class, 'extra_option_item_id');
    }

    protected function casts(): array
    {
        return [
            'price' => 'double',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'allow_quantity' => 'boolean',
        ];
    }
}
