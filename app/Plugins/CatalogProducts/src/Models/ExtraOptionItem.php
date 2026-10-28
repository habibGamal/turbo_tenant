<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExtraOptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_option_id',
        'name',
        'name_ar',
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

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'allow_quantity' => 'boolean',
        ];
    }
}
