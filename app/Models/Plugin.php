<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Plugins\Enums\PluginCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class Plugin extends Model
{
    use CentralConnection;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'version',
        'category',
        'description',
        'is_core',
        'dependencies',
        'author',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'category' => PluginCategory::class,
            'dependencies' => 'array',
        ];
    }

    public function tenantPlugins(): HasMany
    {
        return $this->hasMany(TenantPlugin::class, 'plugin_id');
    }
}
