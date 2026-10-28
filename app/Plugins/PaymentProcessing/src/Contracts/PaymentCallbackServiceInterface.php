<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Contracts;

interface PaymentCallbackServiceInterface
{
    /**
     * Handle payment redirect callback and return the SPA status ('success', 'failed', 'pending').
     */
    public function handleCallback(int $orderId): string;
}
