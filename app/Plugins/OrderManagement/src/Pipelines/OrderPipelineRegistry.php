<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines;

use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;

final class OrderPipelineRegistry implements OrderPipelineRegistrar
{
    /** @var array<string, array{pipe: string|callable|object, priority: int}> */
    private array $validationPipes = [];

    /** @var array<string, array{pipe: string|callable|object, priority: int}> */
    private array $pricingPipes = [];

    public function registerValidationPipe(string|callable|object $pipe, int $priority = 100): static
    {
        $key = $this->resolveKey($pipe);
        $this->validationPipes[$key] = [
            'pipe' => $pipe,
            'priority' => $priority,
        ];

        return $this;
    }

    public function registerPricingPipe(string|callable|object $pipe, int $priority = 100): static
    {
        $key = $this->resolveKey($pipe);
        $this->pricingPipes[$key] = [
            'pipe' => $pipe,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * @return array<int, class-string|callable|object>
     */
    public function getValidationPipes(): array
    {
        return $this->sortPipes($this->validationPipes);
    }

    /**
     * @return array<int, class-string|callable|object>
     */
    public function getPricingPipes(): array
    {
        return $this->sortPipes($this->pricingPipes);
    }

    public function reset(): void
    {
        $this->validationPipes = [];
        $this->pricingPipes = [];
    }

    private function resolveKey(string|callable|object $pipe): string
    {
        return match (true) {
            is_string($pipe) => $pipe,
            is_object($pipe) => get_class($pipe),
            default => spl_object_hash((object) $pipe),
        };
    }

    /**
     * @param  array<string, array{pipe: string|callable|object, priority: int}>  $pipeMap
     * @return array<int, class-string|callable|object>
     */
    private function sortPipes(array $pipeMap): array
    {
        $pipes = array_values($pipeMap);
        usort($pipes, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_map(fn ($item) => $item['pipe'], $pipes);
    }
}
