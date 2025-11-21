<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WeightOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(WeightOptionValue::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'weight_options_id');
    }

    protected function casts(): array
    {
        return [];
    }
}
