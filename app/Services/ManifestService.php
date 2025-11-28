<?php

declare(strict_types=1);

namespace App\Services;

final class ManifestService
{
    private const MANIFEST_FILE = 'app/manifest.json';

    public function getManifestPath(): string
    {
        return storage_path(self::MANIFEST_FILE);
    }

    public function getManifest(): array
    {
        $path = $this->getManifestPath();

        if (!file_exists($path)) {
            return $this->getDefaultManifest();
        }

        $content = file_get_contents($path);
        $manifest = json_decode($content, true);

        return is_array($manifest) ? $manifest : $this->getDefaultManifest();
    }

    public function saveManifest(array $data): void
    {
        $manifest = [
            'name' => $data['name'] ?? '',
            'short_name' => $data['short_name'] ?? '',
            'theme_color' => $data['theme_color'] ?? '#893c25',
            'background_color' => $data['background_color'] ?? '#ffffff',
            'id' => $data['id'] ?? '',
            'start_url' => $data['start_url'] ?? '.',
            'display' => $data['display'] ?? 'standalone',
            'orientation' => $data['orientation'] ?? 'natural',
            'icons' => $data['icons'] ?? $this->getDefaultIcons(),
        ];

        $path = $this->getManifestPath();
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getDefaultManifest(): array
    {
        return [
            'name' => 'Restaurant',
            'short_name' => 'Restaurant',
            'theme_color' => '#893c25',
            'background_color' => '#ffffff',
            'id' => 'com.restaurant',
            'start_url' => '.',
            'display' => 'standalone',
            'orientation' => 'natural',
            'icons' => $this->getDefaultIcons(),
        ];
    }

    private function getDefaultIcons(): array
    {
        return [
            [
                'src' => 'favicon.ico',
                'sizes' => '64x64',
                'type' => 'image/x-icon',
            ],
            [
                'src' => 'android-chrome-192x192.png',
                'type' => 'image/png',
                'sizes' => '192x192',
            ],
            [
                'src' => 'android-chrome-512x512.png',
                'type' => 'image/png',
                'sizes' => '512x512',
            ],
        ];
    }
}
