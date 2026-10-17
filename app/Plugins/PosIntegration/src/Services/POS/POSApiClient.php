<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class POSApiClient implements POSApiClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    /**
     * Get the current shift ID from the POS system.
     *
     * @throws Exception
     */
    public function getShiftId(Branch $branch): int
    {
        $url = $this->getBranchUrl($branch, '/api/get-shift-id');

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->timeout(self::TIMEOUT_SECONDS)->get($url);

            if (! $response->successful()) {
                throw new Exception('حدث خطاء اثناء الاستعلام عن رقم الوردية');
            }

            $data = $response->json();

            if (! is_array($data) || empty($data['shift_id'])) {
                throw new Exception('حدث خطاء اثناء الاستعلام عن رقم الوردية');
            }

            return (int) $data['shift_id'];
        } catch (Exception $e) {
            Log::error('Failed to get shift ID from POS', [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('حدث خطاء اثناء الاستعلام عن رقم الوردية');
        }
    }

    /**
     * Check if the branch can accept orders.
     *
     * @throws Exception
     */
    public function canAcceptOrder(Branch $branch): bool
    {
        $url = $this->getBranchUrl($branch, '/api/can-accept-order');

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->timeout(self::TIMEOUT_SECONDS)->get($url);

            if (! $response->successful()) {
                throw new Exception('حدث خطاء اثناء الاستعلام عن قبول الطلبات');
            }

            $data = $response->json();

            if (! is_array($data)) {
                return false;
            }

            return (bool) ($data['can_accept'] ?? false);
        } catch (Exception $e) {
            Log::error('Failed to check if branch can accept orders', [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('حدث خطاء اثناء الاستعلام عن قبول الطلبات');
        }
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
        $url = $this->getBranchUrl($branch, '/api/web-orders/place-order');

        try {
            Log::info('Sending order to POS', ['url' => $url, 'payload' => $payload]);

            $response = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(self::TIMEOUT_SECONDS)->post($url, $payload);

            $data = $response->json() ?? [];

            Log::info('POS response received', ['status' => $response->status(), 'response' => $data]);

            return [
                'successful' => $response->successful(),
                'status' => $response->status(),
                'data' => is_array($data) ? $data : [],
            ];
        } catch (Exception $e) {
            Log::error('Failed to communicate with POS endpoint', [
                'url' => $url,
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن التواصل مع الفرع في الوقت الحالي');
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
}
