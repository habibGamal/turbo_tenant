<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WeightOption extends Model
{
    protected $fillable = [
        'name',
        'min_weight',
        'max_weight',
        'step',
        'unit',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'weight_options_id');
    }

    protected function casts(): array
    {
        return [
            'min_weight' => 'decimal:3',
            'max_weight' => 'decimal:3',
            'step' => 'decimal:3',
        ];
    }
}
