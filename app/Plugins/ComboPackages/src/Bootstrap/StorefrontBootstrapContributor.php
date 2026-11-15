<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Bootstrap;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Plugins\ComboPackages\src\Services\StorefrontData;
use Illuminate\Contracts\Auth\Authenticatable;

final class StorefrontBootstrapContributor implements BootstrapContributor
{
    public function __construct(private readonly StorefrontData $data) {}

    public function pluginId(): string
    {
        return 'combo-packages';
    }

    public function contribute(?Authenticatable $user): array
    {
        // Only the home document needs these public collections at startup.
        if (! request()->is('/')) {
            return [];
        }

        return ['query_data' => [['key' => ['combo-packages'], 'data' => $this->data->index()]]];
    }
}
