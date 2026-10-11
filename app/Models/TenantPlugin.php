<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Plugins\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class TenantPlugin extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'plugin_id',
        'status',
        'settings',
        'activated_at',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PluginStatus::class,
            'settings' => 'array',
            'activated_at' => 'datetime',
            'installed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class, 'plugin_id');
    }

    public function isActive(): bool
    {
        return $this->status === PluginStatus::ACTIVE;
    }
}
