<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WeightOptionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'weight_option_id',
        'value',
        'label',
        'sort_order',
    ];

    public function weightOption(): BelongsTo
    {
        return $this->belongsTo(WeightOption::class);
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }
}
