<?php

declare(strict_types=1);

namespace App\Services\POS\Contracts;

use App\Models\Branch;
use Exception;

interface POSErrorResponseHandlerInterface
{
    /**
     * Handle non-successful response from POS place-order endpoint.
     *
     * @param  array<string, mixed>  $responseData
     * @return array{success: false, message: string, notFoundProducts: array<string>}
     *
     * @throws Exception
     */
    public function handle(array $responseData, int $statusCode, Branch $branch): array;
}
