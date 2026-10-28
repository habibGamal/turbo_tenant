<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\DTOs\PluginManifest;
use App\Models\Plugin;
use Illuminate\Support\Collection;

final class PluginRegistry
{
    /**
     * @var Collection<string, PluginManifest>
     */
    private Collection $manifests;

    /**
     * @var array<string, string|null>
     */
    private array $classToPluginIdMap = [];

    public function __construct(
        private readonly PluginManifestLoader $loader,
        private readonly string $pluginsPath
    ) {
        $this->manifests = collect();
    }

    public function discover(): self
    {
        $this->manifests = $this->loader->loadFromDirectory($this->pluginsPath);

        return $this;
    }

    /**
     * @return Collection<string, PluginManifest>
     */
    public function all(): Collection
    {
        if ($this->manifests->isEmpty()) {
            $this->discover();
        }

        return $this->manifests;
    }

    public function get(string $id): ?PluginManifest
    {
        return $this->all()->get($id);
    }

    public function has(string $id): bool
    {
        return $this->all()->has($id);
    }

    /**
     * Map a class name (e.g. Filament Resource or Page) to its owner plugin ID.
     */
    public function getPluginIdForClass(string $className): ?string
    {
        if (array_key_exists($className, $this->classToPluginIdMap)) {
            return $this->classToPluginIdMap[$className];
        }

        if (preg_match('/^App\\\\Plugins\\\\([^\\\\]+)/', $className, $matches)) {
            $folder = $matches[1];

            foreach ($this->all() as $manifest) {
                if (basename($manifest->basePath) === $folder) {
                    return $this->classToPluginIdMap[$className] = $manifest->id;
                }
            }
        }

        return $this->classToPluginIdMap[$className] = null;
    }

    /**
     * Synchronize discovered manifests with central plugins table.
     */
    public function syncDatabase(): void
    {
        foreach ($this->all() as $manifest) {
            Plugin::updateOrCreate(
                ['id' => $manifest->id],
                [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'category' => $manifest->category,
                    'description' => $manifest->description,
                    'author' => $manifest->author,
                    'is_core' => $manifest->isCore,
                    'dependencies' => $manifest->dependencies,
                ]
            );
        }
    }
}
