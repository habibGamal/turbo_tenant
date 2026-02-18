<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ExtraOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'is_active',
        'min_selections',
        'max_selections',
        'allow_multiple',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ExtraOptionItem::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_selections' => 'integer',
            'max_selections' => 'integer',
            'allow_multiple' => 'boolean',
        ];
    }
}
