<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

interface SetupChecklistItem extends PluginContribution
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function isComplete(): bool;

    public function url(): ?string;

    public function sortOrder(): int;
}
