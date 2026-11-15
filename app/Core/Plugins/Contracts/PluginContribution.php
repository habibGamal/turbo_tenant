<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

interface PluginContribution
{
    public function pluginId(): string;
}
