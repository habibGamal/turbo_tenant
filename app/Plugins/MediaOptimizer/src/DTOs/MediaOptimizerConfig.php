<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer\src\DTOs;

final readonly class MediaOptimizerConfig
{
    public function __construct(
        public int $maxWidth = 1920,
        public int $mobileWidth = 480,
        public int $placeholderWidth = 20,
        public int $quality = 80,
        public int $placeholderQuality = 30,
        public array $nonConvertibleExtensions = ['svg', 'ico', 'gif']
    ) {}
}
