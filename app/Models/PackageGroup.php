<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PackageGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'name',
        'name_ar',
        'selection_type',
        'max_selections',
        'min_selections',
        'sort_order',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order');
    }

    public function isConditional(): bool
    {
        return in_array($this->selection_type, ['choose_one', 'choose_multiple']);
    }

    public function requiresSelection(): bool
    {
        return $this->isConditional();
    }

    protected function casts(): array
    {
        return [
            'max_selections' => 'integer',
            'min_selections' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
