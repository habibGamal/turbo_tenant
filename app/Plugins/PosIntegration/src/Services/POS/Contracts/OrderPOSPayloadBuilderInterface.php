<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS\Contracts;

use App\Plugins\OrderManagement\src\Models\Order;

interface OrderPOSPayloadBuilderInterface
{
    /**
     * Build the order payload for the POS system conforming to CREATE_ORDER_API_DOC.md.
     *
     * @return array{
     *     user: array{name: string, phone: string, area: string, address: string},
     *     order: array{
     *         type: string,
     *         shiftId: int,
     *         orderNumber: string,
     *         subTotal: float,
     *         tax: float,
     *         service: float,
     *         discount: float,
     *         total: float,
     *         note?: string,
     *         webPreferences?: array{payment_method?: string, transaction_id?: string},
     *         items: array<int, array{
     *             quantity: float|int,
     *             notes: string,
     *             posRefObj: array<int, array{productRef: string, quantity: float|int}>
     *         }>
     *     }
     * }
     */
    public function build(Order $order): array;
}
