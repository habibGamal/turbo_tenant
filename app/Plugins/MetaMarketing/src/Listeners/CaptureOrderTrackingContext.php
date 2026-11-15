<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Listeners;

use App\Plugins\MetaMarketing\src\Capi\OrderPurchaseContents;
use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs synchronously during order placement and stores the browser context
 * (fbp, fbc, IP, user agent, source URL) the Conversions API needs later.
 */
final class CaptureOrderTrackingContext
{
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;

            if ($order->type === OrderType::POS || $order->type === 'pos') {
                return;
            }

            $request = request();

            $fbp = $request->cookie('_fbp');
            $fbc = $request->cookie('_fbc');

            if (blank($fbc)) {
                $fbclid = $this->extractFbclid($request);
                if (filled($fbclid)) {
                    $fbc = sprintf('fb.1.%d.%s', (int) round(microtime(true) * 1000), $fbclid);
                }
            }

            $clientIp = $request->ip();
            $userAgent = $request->userAgent();
            $referer = $request->header('referer');
            $eventSourceUrl = filled($referer) ? (string) $referer : url('/checkout');

            MetaOrderTracking::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'fbp' => filled($fbp) ? (string) $fbp : null,
                    'fbc' => filled($fbc) ? (string) $fbc : null,
                    'client_ip' => filled($clientIp) ? (string) $clientIp : null,
                    'user_agent' => filled($userAgent) ? (string) $userAgent : null,
                    'event_source_url' => $eventSourceUrl,
                    'purchase_event_id' => OrderPurchaseContents::eventId($order->id),
                ]
            );
        } catch (Throwable $e) {
            Log::error('MetaMarketing: failed to capture order tracking context: '.$e->getMessage(), [
                'order_id' => $event->order->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    private function extractFbclid(Request $request): ?string
    {
        $queryVal = $request->query('fbclid');
        if (is_string($queryVal) && mb_trim($queryVal) !== '') {
            return mb_trim($queryVal);
        }

        $referer = $request->header('referer');
        if (is_string($referer) && $referer !== '') {
            $parts = parse_url($referer);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                if (! empty($query['fbclid']) && is_string($query['fbclid'])) {
                    return mb_trim($query['fbclid']);
                }
            }
        }

        return null;
    }
}
