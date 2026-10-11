<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\DTOs\PluginManifest;
use Illuminate\Support\Collection;
use RuntimeException;

final class PluginDependencyResolver
{
    /**
     * Topologically sort manifests so dependencies boot before dependants.
     *
     * @param  Collection<string, PluginManifest>  $manifests
     * @return array<string, PluginManifest>
     */
    public function sort(Collection $manifests): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach ($manifests as $id => $manifest) {
            $this->visit($id, $manifests, $visited, $visiting, $sorted);
        }

        return $sorted;
    }

    /**
     * @param  Collection<string, PluginManifest>  $manifests
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     * @param  array<string, PluginManifest>  $sorted
     */
    private function visit(
        string $id,
        Collection $manifests,
        array &$visited,
        array &$visiting,
        array &$sorted
    ): void {
        if (isset($visited[$id])) {
            return;
        }

        if (isset($visiting[$id])) {
            throw new RuntimeException("Circular dependency detected involving plugin '{$id}'");
        }

        $visiting[$id] = true;
        $manifest = $manifests->get($id);

        if ($manifest !== null) {
            foreach ($manifest->dependencies as $depId) {
                if ($manifests->has($depId)) {
                    $this->visit($depId, $manifests, $visited, $visiting, $sorted);
                }
            }

            $sorted[$id] = $manifest;
        }

        unset($visiting[$id]);
        $visited[$id] = true;
    }

    /**
     * Check if all dependencies for a plugin are satisfied in an active set.
     *
     * @param  array<string>  $activePluginIds
     * @return array<string> Missing plugin IDs
     */
    public function getMissingDependencies(PluginManifest $manifest, array $activePluginIds): array
    {
        $missing = [];

        foreach ($manifest->dependencies as $requiredId) {
            if (! in_array($requiredId, $activePluginIds, true)) {
                $missing[] = $requiredId;
            }
        }

        return $missing;
    }
}
