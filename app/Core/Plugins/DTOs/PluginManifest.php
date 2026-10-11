<?php

declare(strict_types=1);

namespace App\Core\Plugins\DTOs;

use App\Core\Plugins\Enums\PluginCategory;
use InvalidArgumentException;

final readonly class PluginManifest
{
    /**
     * @param  array<string>  $dependencies
     * @param  array<string>  $providers
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public PluginCategory $category,
        public string $basePath,
        public ?string $description = null,
        public ?string $author = null,
        public bool $isCore = false,
        public array $dependencies = [],
        public array $providers = [],
        public ?string $filamentPlugin = null,
        public ?string $tenantMigrationsPath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $basePath): self
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException("Plugin manifest in {$basePath} is missing required 'id'");
        }

        if (empty($data['name'])) {
            throw new InvalidArgumentException("Plugin manifest in {$basePath} is missing required 'name'");
        }

        $migrationsPath = $basePath . '/database/migrations/tenant';
        $hasMigrations = is_dir($migrationsPath);

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            version: (string) ($data['version'] ?? '1.0.0'),
            category: PluginCategory::tryFrom((string) ($data['category'] ?? 'addon')) ?? PluginCategory::ADDON,
            basePath: $basePath,
            description: isset($data['description']) ? (string) $data['description'] : null,
            author: isset($data['author']) ? (string) $data['author'] : null,
            isCore: (bool) ($data['is_core'] ?? false),
            dependencies: (array) ($data['dependencies'] ?? []),
            providers: (array) ($data['providers'] ?? []),
            filamentPlugin: isset($data['filament']) ? (string) $data['filament'] : null,
            tenantMigrationsPath: $hasMigrations ? $migrationsPath : null,
        );
    }
}
