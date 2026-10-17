<?php

declare(strict_types=1);

namespace App\Plugins\SampleGreeting\src\DTOs;

final readonly class SampleGreetingConfig
{
    public function __construct(
        public string $defaultGreeting = 'Hello from SampleGreetingPlugin!',
        public bool $enablePrefix = true
    ) {}
}
