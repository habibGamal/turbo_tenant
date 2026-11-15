<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services;

use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CartEngine\src\Contracts\CartLineTypeHandler;

final class CartLineTypeRegistry
{
    public function __construct(
        private readonly PluginContributions $contributions,
    ) {}

    public function get(string $type): ?CartLineTypeHandler
    {
        /** @var list<mixed> $handlers */
        $handlers = $this->contributions->active('cart-engine.line-types');

        foreach ($handlers as $handler) {
            if ($handler instanceof CartLineTypeHandler && $handler->type() === $type) {
                return $handler;
            }
        }

        return null;
    }

    public function find(string $type): ?CartLineTypeHandler
    {
        return $this->get($type);
    }
}
