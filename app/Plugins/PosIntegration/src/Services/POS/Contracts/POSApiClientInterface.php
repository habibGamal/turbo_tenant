<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS\Contracts;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use Exception;

interface POSApiClientInterface
{
    /**
     * Get the current shift ID from the POS system.
     *
     * @throws Exception
     */
    public function getShiftId(Branch $branch): int;

    /**
     * Check if the branch can accept orders.
     *
     * @throws Exception
     */
    public function canAcceptOrder(Branch $branch): bool;

    /**
     * Send order payload to the branch POS endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array{successful: bool, status: int, data: array<string, mixed>}
     *
     * @throws Exception
     */
    public function sendOrder(Branch $branch, array $payload): array;
}
