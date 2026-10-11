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
