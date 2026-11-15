<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PackageAvailability extends Model
{
    protected $table = 'package_availabilities';

    protected $fillable = [
        'package_id',
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
