<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface;
use App\Support\Network\PublicEndpointGuard;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class POSApiClient implements POSApiClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const SHIFT_ID_FAILURE = 'حدث خطاء اثناء الاستعلام عن رقم الوردية';

    private const CAN_ACCEPT_FAILURE = 'حدث خطاء اثناء الاستعلام عن قبول الطلبات';

    private const SEND_ORDER_FAILURE = 'لا يمكن التواصل مع الفرع في الوقت الحالي';

    public function __construct(
        private readonly PublicEndpointGuard $endpointGuard = new PublicEndpointGuard()
    ) {}

    /**
     * Get the current shift ID from the POS system.
     *
     * @throws Exception
     */
    public function getShiftId(Branch $branch): int
    {
        $response = $this->request($branch, 'get', '/api/get-shift-id', self::SHIFT_ID_FAILURE);
        $data = $response->json();

        if (! $response->successful() || ! is_array($data) || empty($data['shift_id'])) {
            throw new Exception(self::SHIFT_ID_FAILURE);
        }

        return (int) $data['shift_id'];
    }

    /**
     * Check if the branch can accept orders.
     *
     * @throws Exception
     */
    public function canAcceptOrder(Branch $branch): bool
    {
        $response = $this->request($branch, 'get', '/api/can-accept-order', self::CAN_ACCEPT_FAILURE);

        if (! $response->successful()) {
            throw new Exception(self::CAN_ACCEPT_FAILURE);
        }

        $data = $response->json();

        if (! is_array($data)) {
            return false;
        }

        return (bool) ($data['can_accept'] ?? false);
    }

    /**
     * Send order payload to POS system.
     *
     * @param  array<string, mixed>  $payload
     * @return array{successful: bool, status: int, data: array<string, mixed>}
     *
     * @throws Exception
     */
    public function sendOrder(Branch $branch, array $payload): array
    {
        $response = $this->request($branch, 'post', '/api/web-orders/place-order', self::SEND_ORDER_FAILURE, $payload);

        $data = $response->json() ?? [];

        return [
            'successful' => $response->successful(),
            'status' => $response->status(),
            'data' => is_array($data) ? $data : [],
        ];
    }

    /**
     * Execute HTTP request against branch POS endpoint with guard, options, timing, and logging.
     * Transport failures surface as $failureMessage, which admins see as the order's POS failure reason.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws Exception
     */
    private function request(Branch $branch, string $method, string $endpoint, string $failureMessage, array $payload = []): Response
    {
        $url = $this->getBranchUrl($branch, $endpoint);
        $this->endpointGuard->guard($url);

        $orderNumber = $this->extractOrderNumber($payload);
        $startTime = microtime(true);

        try {
            $pendingRequest = Http::withOptions([
                'verify' => false,
                'allow_redirects' => false,
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            ])->withHeaders([
                'Accept' => 'application/json',
            ])->timeout(self::TIMEOUT_SECONDS);

            $response = mb_strtolower($method) === 'post'
                ? $pendingRequest->asJson()->post($url, $payload)
                : $pendingRequest->get($url);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $logContext = [
                'branch_id' => $branch->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'duration_ms' => $durationMs,
            ];
            if ($orderNumber !== null) {
                $logContext['order_number'] = $orderNumber;
            }

            Log::info('POS request completed', $logContext);

            return $response;
        } catch (Exception $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $logContext = [
                'branch_id' => $branch->id,
                'endpoint' => $endpoint,
                'status' => null,
                'duration_ms' => $durationMs,
            ];
            if ($orderNumber !== null) {
                $logContext['order_number'] = $orderNumber;
            }

            Log::error('POS request failed', $logContext);

            throw new Exception($failureMessage, 0, $e);
        }
    }

    /**
     * Build endpoint URL for the branch.
     */
    private function getBranchUrl(Branch $branch, string $endpoint): string
    {
        $baseUrl = mb_rtrim((string) $branch->link, '/');

        return $baseUrl.$endpoint;
    }

    /**
     * Extract order number from payload if present.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractOrderNumber(array $payload): ?string
    {
        if (isset($payload['order']['orderNumber']) && is_string($payload['order']['orderNumber'])) {
            return $payload['order']['orderNumber'];
        }

        if (isset($payload['order_number']) && is_string($payload['order_number'])) {
            return $payload['order_number'];
        }

        if (isset($payload['orderNumber']) && is_string($payload['orderNumber'])) {
            return $payload['orderNumber'];
        }

        return null;
    }
}
