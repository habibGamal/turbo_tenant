<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrderDeliveryReview
{
    public function canReview(Order $order): bool
    {
        $delivery = $order->posDelivery;

        return $order->pos_status === OrderPosStatus::NEEDS_VERIFICATION
            || $delivery?->isOverdue()
            || (! $delivery && $order->pos_status === OrderPosStatus::SENDING && $order->updated_at->lt(now()->subMinutes(5)));
    }

    public function verify(Order $order, User $actor, bool $received, string $evidence): void
    {
        if (! tenancy()->initialized || ! $actor->is_admin) {
            throw new AuthorizationException;
        }
        $evidence = mb_trim(strip_tags($evidence));
        if (mb_strlen($evidence) < 10 || mb_strlen($evidence) > 1000) {
            throw ValidationException::withMessages(['evidence' => 'اكتب تفاصيل التحقق مع الفرع من 10 إلى 1000 حرف.']);
        }

        DB::connection('tenant')->transaction(function () use ($order, $actor, $received, $evidence): void {
            $locked = Order::on('tenant')->lockForUpdate()->findOrFail($order->id);
            if (! app(\App\Core\Plugins\Services\PluginManager::class)->isPluginActive('pos-integration') || ! $this->canReview($locked)) {
                throw ValidationException::withMessages(['pos_delivery' => 'حالة الإرسال لا تسمح بالمراجعة الآن. حدث الصفحة وأعد المحاولة.']);
            }
            $delivery = $locked->posDelivery ?? OrderPosDelivery::create([
                'order_id' => $locked->id,
                'generation' => (string) Str::uuid(),
                'status' => OrderPosStatus::NEEDS_VERIFICATION,
                'trigger' => 'automatic',
                'history_complete' => false,
            ])->refresh();
            $open = $delivery->attempts()->whereNull('finished_at')->get();
            foreach ($open as $attempt) {
                $attempt->update([
                    'outcome' => 'uncertain',
                    'finished_at' => now(),
                    'duration_ms' => max(0, (int) $attempt->started_at->diffInMilliseconds(now())),
                    'error' => 'توقفت متابعة المحاولة؛ تم تسجيل نتيجة التحقق مع الفرع.',
                ]);
            }
            $delivery->attempts()->create([
                'generation' => $delivery->generation,
                'attempt_number' => $delivery->attempts_count,
                'trigger' => 'verification',
                'requested_by' => $actor->id,
                'requested_by_name' => $actor->name,
                'outcome' => $received ? 'verified_sent' : 'verified_not_sent',
                'started_at' => now(),
                'finished_at' => now(),
                'error' => $evidence,
            ]);
            $status = $received ? OrderPosStatus::SENT : OrderPosStatus::FAILED;
            $reason = $received ? null : 'تم التحقق مع الفرع وتأكيد عدم استلام الطلب. يمكنك إعادة الإرسال.';
            $delivery->update([
                // Fence delayed jobs and callbacks from the previous delivery generation.
                'generation' => (string) Str::uuid(),
                'status' => $status,
                'next_retry_at' => null,
                'sent_at' => $received ? now() : null,
                'last_error' => $reason ?? $delivery->last_error,
                'failures_count' => $delivery->failures_count + $open->count(),
            ]);
            $locked->updateQuietly(['pos_status' => $status, 'pos_failure_reason' => $reason]);
        });
    }
}
