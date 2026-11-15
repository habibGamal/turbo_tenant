<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface BootstrapContributor extends PluginContribution
{
    /**
     * @return array<string, mixed>
     */
    public function contribute(?Authenticatable $user): array;
}
