<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExtraOptionItem extends Model
{
    protected $fillable = [
        'extra_option_id',
        'name',
        'price',
        'is_default',
        'sort_order',
    ];

    public function extraOption(): BelongsTo
    {
        return $this->belongsTo(ExtraOption::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'double',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
