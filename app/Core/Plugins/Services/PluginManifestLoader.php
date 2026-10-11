<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\DTOs\PluginManifest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

final class PluginManifestLoader
{
    /**
     * @return Collection<string, PluginManifest>
     */
    public function loadFromDirectory(string $pluginsPath): Collection
    {
        if (! File::isDirectory($pluginsPath)) {
            return collect();
        }

        $manifests = collect();
        $directories = File::directories($pluginsPath);

        foreach ($directories as $dir) {
            $manifestFile = $dir . '/plugin.json';

            if (! File::exists($manifestFile)) {
                continue;
            }

            try {
                $contents = File::get($manifestFile);
                $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

                $manifest = PluginManifest::fromArray($data, $dir);
                $manifests->put($manifest->id, $manifest);
            } catch (JsonException $e) {
                throw new RuntimeException("Malformed plugin.json in {$dir}: {$e->getMessage()}", 0, $e);
            }
        }

        return $manifests;
    }
}
