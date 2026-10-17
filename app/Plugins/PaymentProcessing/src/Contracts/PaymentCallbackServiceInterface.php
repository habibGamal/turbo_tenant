<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Contracts;

interface PaymentCallbackServiceInterface
{
    /**
     * Handle payment redirect callback from gateway.
     *
     * @param  array<string, mixed>  $callbackData
     * @return array<string, mixed>
     */
    public function handleCallback(int $orderId, array $callbackData): array;
}
