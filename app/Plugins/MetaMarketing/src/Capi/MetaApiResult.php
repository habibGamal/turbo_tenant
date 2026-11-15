<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Capi;

final readonly class MetaApiResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public ?int $httpStatus = null,
        public ?int $eventsReceived = null,
        public ?string $fbtraceId = null,
        public ?string $errorMessage = null,
        public array $raw = [],
    ) {}
}
