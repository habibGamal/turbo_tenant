<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use BackedEnum;

/**
 * Contract for tenant-aware settings repository.
 *
 * Do NOT implement or bind it here (the StoreSettings plugin implements it in a later phase).
 */
interface SettingsRepository
{
    public function get(BackedEnum|string $key, mixed $default = null): mixed;

    public function set(BackedEnum|string $key, mixed $value): void;

    public function has(BackedEnum|string $key): bool;
}
