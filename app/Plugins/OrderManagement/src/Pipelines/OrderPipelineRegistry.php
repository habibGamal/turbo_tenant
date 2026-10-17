<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines;

final class OrderPipelineRegistry
{
    /** @var array<string, array{pipe: string|callable|object, priority: int}> */
    private array $validationPipes = [];

    /** @var array<string, array{pipe: string|callable|object, priority: int}> */
    private array $pricingPipes = [];

    private function resolveKey(string|callable|object $pipe): string
    {
        return match (true) {
            is_string($pipe) => $pipe,
            is_object($pipe) => get_class($pipe),
            default => spl_object_hash((object) $pipe),
        };
    }

    public function addValidationPipe(string|callable|object $pipe, int $priority = 100): self
    {
        $key = $this->resolveKey($pipe);
        $this->validationPipes[$key] = [
            'pipe' => $pipe,
            'priority' => $priority,
        ];

        return $this;
    }

    public function registerValidationPipe(string|callable|object $pipe, int $priority = 100): self
    {
        return $this->addValidationPipe($pipe, $priority);
    }

    public function addPricingPipe(string|callable|object $pipe, int $priority = 100): self
    {
        $key = $this->resolveKey($pipe);
        $this->pricingPipes[$key] = [
            'pipe' => $pipe,
            'priority' => $priority,
        ];

        return $this;
    }

    public function registerPricingPipe(string|callable|object $pipe, int $priority = 100): self
    {
        return $this->addPricingPipe($pipe, $priority);
    }

    /**
     * @return array<int, class-string|callable|object>
     */
    public function getValidationPipes(): array
    {
        $pipes = array_values($this->validationPipes);
        usort($pipes, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_map(fn ($item) => $item['pipe'], $pipes);
    }

    /**
     * @return array<int, class-string|callable|object>
     */
    public function getPricingPipes(): array
    {
        $pipes = array_values($this->pricingPipes);
        usort($pipes, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_map(fn ($item) => $item['pipe'], $pipes);
    }

    public function reset(): void
    {
        $this->validationPipes = [];
        $this->pricingPipes = [];
    }
}
