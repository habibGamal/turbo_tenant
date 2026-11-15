<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Models;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PosLineMapping extends Model
{
    use HasFactory;

    protected $table = 'pos_line_mappings';

    protected $fillable = [
        'line_type',
        'line_reference_id',
        'branch_id',
        'pos_item_id',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'line_reference_id' => 'integer',
            'branch_id' => 'integer',
        ];
    }
}
