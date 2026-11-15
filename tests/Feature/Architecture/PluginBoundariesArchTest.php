<?php

declare(strict_types=1);

/**
 * Plugin boundary rules:
 * (1) controllers stay thin (validation in FormRequests);
 * (2) a plugin knows only core and the plugins in its manifest dependencies, never optional plugins;
 * (3) cross-plugin collaboration goes through interfaces the provider publishes; never write another plugin's tables.
 *
 * The allow-list may only shrink.
 */

use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginManifestLoader;

$allowlist = require __DIR__.'/boundary-allowlist.php';

// Rule A: A plugin may use only (a) non-plugin code and (b) plugins listed in its own manifest "dependencies".
$pluginsDir = dirname(__DIR__, 3).'/app/Plugins';
$manifests = [];
$idToDir = [];

foreach (glob($pluginsDir.'/*/plugin.json') as $manifestPath) {
    $dirName = basename(dirname($manifestPath));
    $data = json_decode((string) file_get_contents($manifestPath), true);
    if (isset($data['id'])) {
        $idToDir[$data['id']] = $dirName;
        $manifests[$dirName] = $data;
    }
}

foreach ($manifests as $dir => $manifest) {
    $declaredDeps = $manifest['dependencies'] ?? [];
    $allowedDirs = [$dir];
    foreach ($declaredDeps as $depId) {
        if (isset($idToDir[$depId])) {
            $allowedDirs[] = $idToDir[$depId];
        }
    }

    $forbiddenNamespaces = [];
    foreach (array_keys($manifests) as $otherDir) {
        if (! in_array($otherDir, $allowedDirs, true)) {
            $forbiddenNamespaces[] = "App\\Plugins\\{$otherDir}";
        }
    }

    arch("plugin {$dir} uses only declared plugin dependencies")
        ->expect("App\\Plugins\\{$dir}")
        ->not->toUse($forbiddenNamespaces)
        ->ignoring($allowlist[$dir] ?? []);
}

// Rule B: Core code must not use plugin code
$coreNamespaces = [
    'App\Models',
    'App\Enums',
    'App\Filament',
    'App\Http',
    'App\Console',
    'App\Listeners',
    'App\Mail',
    'App\Services',
    'App\DTOs',
    'App\Exceptions',
    'App\Core',
    'App\Providers',
];

$coreArch = arch('core code does not use plugin code');
foreach ($coreNamespaces as $ns) {
    $coreArch = $coreArch->expect($ns)
        ->not->toUse('App\Plugins')
        ->ignoring($allowlist['core'] ?? []);
}
unset($coreArch);

// Rule C: Manifest validation and dependency graph cycle check
test('every dependency id refers to an existing plugin and declared dependency graph has no cycle', function () {
    $manifestLoader = new PluginManifestLoader();
    $manifests = $manifestLoader->loadFromDirectory(app_path('Plugins'));

    expect($manifests)->not->toBeEmpty();

    $knownIds = $manifests->keys()->all();

    foreach ($manifests as $manifest) {
        foreach ($manifest->dependencies as $dependencyId) {
            expect($knownIds)->toContain($dependencyId);
        }
    }

    $resolver = new PluginDependencyResolver();
    $sorted = $resolver->sort($manifests);

    expect($sorted)->toBeArray()
        ->and(count($sorted))->toBe($manifests->count());
});

// Helper utilities for Rule D (Text-Scan Guards)
$isCommentLine = static function (string $line): bool {
    $trimmed = mb_ltrim($line);

    return str_starts_with($trimmed, '//')
        || str_starts_with($trimmed, '#')
        || str_starts_with($trimmed, '*')
        || str_starts_with($trimmed, '/*');
};

$getRelPath = static function (string $filePath): string {
    return mb_ltrim(str_replace('\\', '/', str_replace(base_path(), '', $filePath)), '/');
};

$scanPhpFiles = static function (string $directory): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
};

// Rule D1a: String-literal or code references to undeclared plugin namespaces
test('text guard D1a: plugins only reference declared plugin namespaces in code', function () use ($pluginsDir, $manifests, $idToDir, $allowlist, $isCommentLine, $getRelPath, $scanPhpFiles) {
    $failures = [];

    foreach ($manifests as $dir => $manifest) {
        $declaredDeps = $manifest['dependencies'] ?? [];
        $allowedDirs = [$dir];
        foreach ($declaredDeps as $depId) {
            if (isset($idToDir[$depId])) {
                $allowedDirs[] = $idToDir[$depId];
            }
        }

        $dirFiles = $scanPhpFiles($pluginsDir.'/'.$dir);

        foreach ($dirFiles as $filePath) {
            $relPath = $getRelPath($filePath);
            $lines = file($filePath);

            foreach ($lines as $lineNum => $line) {
                if ($isCommentLine($line)) {
                    continue;
                }

                if (preg_match_all('/(?:\\\\)?App(?:\\\\|\\\\\\\\)Plugins(?:\\\\|\\\\\\\\)([A-Za-z0-9_]+)/i', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $otherDir = $match[1];
                        if (! in_array($otherDir, $allowedDirs, true)) {
                            if (! in_array($relPath, $allowlist['text']['D1a'] ?? [], true)) {
                                $ln = $lineNum + 1;
                                $failures[] = "{$relPath}:{$ln} matched '{$match[0]}'";
                            }
                        }
                    }
                }
            }
        }
    }

    expect($failures)->toBeEmpty("Violations found in D1a:\n".implode("\n", $failures));
});

// Rule D1b: Querying undeclared plugins via isPluginActive()
test('text guard D1b: plugins do not query undeclared plugin ids via isPluginActive', function () use ($pluginsDir, $manifests, $allowlist, $isCommentLine, $getRelPath, $scanPhpFiles) {
    $failures = [];

    foreach ($manifests as $dir => $manifest) {
        $declaredDeps = $manifest['dependencies'] ?? [];
        $ownId = $manifest['id'] ?? '';
        $dirFiles = $scanPhpFiles($pluginsDir.'/'.$dir);

        foreach ($dirFiles as $filePath) {
            $relPath = $getRelPath($filePath);
            $lines = file($filePath);

            foreach ($lines as $lineNum => $line) {
                if ($isCommentLine($line)) {
                    continue;
                }

                if (preg_match_all('/isPluginActive\(\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/i', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $targetId = $match[1];
                        if ($targetId === $ownId) {
                            continue;
                        }
                        if (! in_array($targetId, $declaredDeps, true)) {
                            if (! in_array($relPath, $allowlist['text']['D1b'] ?? [], true)) {
                                $ln = $lineNum + 1;
                                $failures[] = "{$relPath}:{$ln} matched '{$match[0]}'";
                            }
                        }
                    }
                }
            }
        }
    }

    expect($failures)->toBeEmpty("Violations found in D1b:\n".implode("\n", $failures));
});

// Rule D1b-redundant: Querying declared dependencies via isPluginActive() is redundant
test('text guard D1b-redundant: plugins do not redundantly query declared dependencies via isPluginActive', function () use ($pluginsDir, $manifests, $allowlist, $isCommentLine, $getRelPath, $scanPhpFiles) {
    $failures = [];

    foreach ($manifests as $dir => $manifest) {
        $declaredDeps = $manifest['dependencies'] ?? [];
        $ownId = $manifest['id'] ?? '';
        $dirFiles = $scanPhpFiles($pluginsDir.'/'.$dir);

        foreach ($dirFiles as $filePath) {
            $relPath = $getRelPath($filePath);
            $lines = file($filePath);

            foreach ($lines as $lineNum => $line) {
                if ($isCommentLine($line)) {
                    continue;
                }

                if (preg_match_all('/isPluginActive\(\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/i', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $targetId = $match[1];
                        if ($targetId === $ownId) {
                            continue;
                        }
                        if (in_array($targetId, $declaredDeps, true)) {
                            if (! in_array($relPath, $allowlist['text']['D1b-redundant'] ?? [], true)) {
                                $ln = $lineNum + 1;
                                $failures[] = "{$relPath}:{$ln} matched '{$match[0]}'";
                            }
                        }
                    }
                }
            }
        }
    }

    expect($failures)->toBeEmpty("Violations found in D1b-redundant:\n".implode("\n", $failures));
});

// Rule D1c: Container string aliases belonging to other plugins
test('text guard D1c: plugins do not use container aliases belonging to another plugin', function () use ($pluginsDir, $manifests, $allowlist, $isCommentLine, $getRelPath, $scanPhpFiles) {
    $failures = [];
    $aliasBinders = [
        'guest.user.service' => 'GuestCheckout',
        'setting.service' => 'StoreSettings',
    ];

    foreach ($manifests as $dir => $manifest) {
        $dirFiles = $scanPhpFiles($pluginsDir.'/'.$dir);

        foreach ($dirFiles as $filePath) {
            $relPath = $getRelPath($filePath);
            $lines = file($filePath);

            foreach ($lines as $lineNum => $line) {
                if ($isCommentLine($line)) {
                    continue;
                }

                if (preg_match_all('/app\(\s*[\'"](guest\.user\.service|setting\.service|sample-greeting\.[\w.]+)[\'"]\s*\)/i', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $alias = mb_strtolower($match[1]);
                        $binder = $aliasBinders[$alias] ?? (str_starts_with($alias, 'sample-greeting.') ? 'SampleGreeting' : null);
                        if ($binder !== null && $dir !== $binder) {
                            if (! in_array($relPath, $allowlist['text']['D1c'] ?? [], true)) {
                                $ln = $lineNum + 1;
                                $failures[] = "{$relPath}:{$ln} matched '{$match[0]}'";
                            }
                        }
                    }
                }
            }
        }
    }

    expect($failures)->toBeEmpty("Violations found in D1c:\n".implode("\n", $failures));
});

// Rule D2: Core code does not reference plugin namespaces in code or string literals
test('text guard D2: core code does not reference plugin namespaces', function () use ($pluginsDir, $allowlist, $isCommentLine, $getRelPath) {
    $failures = [];
    $appDir = dirname($pluginsDir);
    $pluginsNormalized = str_replace('\\', '/', $pluginsDir);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $normalized = str_replace('\\', '/', $file->getPathname());
        if (str_starts_with($normalized, $pluginsNormalized)) {
            continue;
        }

        $relPath = $getRelPath($file->getPathname());
        $lines = file($file->getPathname());

        foreach ($lines as $lineNum => $line) {
            if ($isCommentLine($line)) {
                continue;
            }

            if (preg_match('/(?:\\\\)?App(?:\\\\|\\\\\\\\)Plugins(?:\\\\|\\\\\\\\)/i', $line, $match)) {
                if (! in_array($relPath, $allowlist['text']['D2'] ?? [], true)) {
                    $ln = $lineNum + 1;
                    $failures[] = "{$relPath}:{$ln} matched '{$match[0]}'";
                }
            }
        }
    }

    expect($failures)->toBeEmpty("Violations found in D2:\n".implode("\n", $failures));
});
