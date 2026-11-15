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
        private readonly string $pluginsPath,
        private readonly ?string $manifestCachePath = null
    ) {
        $this->manifests = collect();
    }

    public function getCachePath(): ?string
    {
        return $this->manifestCachePath;
    }

    public function hasCache(): bool
    {
        return $this->manifestCachePath !== null && file_exists($this->manifestCachePath);
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
            if ($this->hasCache()) {
                $this->loadFromCache();
            } else {
                $this->discover();
            }
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

    /**
     * Write manifests cache file at the specified path or default manifestCachePath.
     */
    public function writeCache(?string $path = null): void
    {
        $targetPath = $path ?? $this->manifestCachePath;

        if ($targetPath === null) {
            return;
        }

        $dir = dirname($targetPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cacheData = [];
        foreach ($this->all() as $manifest) {
            $cacheData[$manifest->id] = $manifest->toArray();
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($cacheData, true).";\n";
        file_put_contents($targetPath, $content, LOCK_EX);
    }

    /**
     * Remove the manifest cache file.
     */
    public function clearCache(?string $path = null): void
    {
        $targetPath = $path ?? $this->manifestCachePath;

        if ($targetPath !== null && file_exists($targetPath)) {
            unlink($targetPath);
        }
    }

    private function loadFromCache(): void
    {
        if ($this->manifestCachePath === null || ! file_exists($this->manifestCachePath)) {
            $this->discover();

            return;
        }

        $cached = require $this->manifestCachePath;
        if (! is_array($cached)) {
            $this->discover();

            return;
        }

        $manifests = collect();
        foreach ($cached as $item) {
            if (! is_array($item)) {
                continue;
            }

            $basePath = (string) ($item['basePath'] ?? '');
            $manifest = PluginManifest::fromArray($item, $basePath);
            $manifests->put($manifest->id, $manifest);
        }

        $this->manifests = $manifests;
    }
}
