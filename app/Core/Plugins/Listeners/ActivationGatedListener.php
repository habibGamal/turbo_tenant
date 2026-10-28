<?php

declare(strict_types=1);

namespace App\Core\Plugins\Listeners;

use App\Core\Plugins\Services\PluginManager;

final class ActivationGatedListener
{
    private string $listenerClass;

    private string $method;

    public function __construct(
        private readonly string $pluginId,
        string $listenerSpec,
        private readonly ?PluginManager $pluginManager = null
    ) {
        if (str_contains($listenerSpec, '@')) {
            [$this->listenerClass, $this->method] = explode('@', $listenerSpec, 2);
        } else {
            $this->listenerClass = $listenerSpec;
            $this->method = 'handle';
        }
    }

    public function __invoke(mixed ...$payload): mixed
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        $manager = $this->pluginManager ?? app(PluginManager::class);
        if (! $manager->isPluginActive($this->pluginId, $tenant)) {
            return null;
        }

        $listener = app()->make($this->listenerClass);

        return $listener->{$this->method}(...$payload);
    }

    public function getListenerClass(): string
    {
        return $this->listenerClass;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
