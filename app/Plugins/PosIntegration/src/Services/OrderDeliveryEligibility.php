<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Core\Plugins\Services\PluginManager;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;

final class OrderDeliveryEligibility
{
    public function blockingReason(Order $order): ?string
    {
        if (! tenancy()->initialized || ! app(PluginManager::class)->isPluginActive('pos-integration')) {
            return 'تكامل نقطة البيع غير مفعل لهذا المتجر.';
        }

        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::DELIVERED], true)) {
            return 'لا يمكن إرسال طلب ملغي أو مكتمل.';
        }

        if (! in_array($order->type, [OrderType::WEB_DELIVERY, OrderType::WEB_TAKEAWAY], true)) {
            return 'هذا الطلب ليس طلب متجر إلكتروني.';
        }

        if ($order->payment_status === PaymentStatus::REFUNDED) {
            return 'لا يمكن إرسال طلب مسترجع الدفع.';
        }

        if ($order->payment_method?->requiresOnlinePayment() && $order->payment_status !== PaymentStatus::COMPLETED) {
            return 'بانتظار تأكيد سداد الطلب.';
        }

        if (! $order->branch_id || ! $order->branch) {
            return 'لم يتم تحديد فرع صالح للطلب.';
        }

        return null;
    }

    public function isEligible(Order $order): bool
    {
        return $this->blockingReason($order) === null;
    }
}
