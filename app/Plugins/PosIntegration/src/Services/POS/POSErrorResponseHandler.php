<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSErrorResponseHandlerInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Exception;
use Illuminate\Support\Facades\Log;

final class POSErrorResponseHandler implements POSErrorResponseHandlerInterface
{
    public function __construct(
        private readonly PosMasterClient $posMasterClient
    ) {}

    /**
     * Handle non-successful response from POS place-order endpoint.
     *
     * @param  array<string, mixed>  $responseData
     * @return array{success: false, message: string, notFoundProducts: array<string>}
     *
     * @throws Exception
     */
    public function handle(array $responseData, int $statusCode, Branch $branch): array
    {
        $error = $responseData['error'] ?? null;

        // 1. status 400 AND error is a string starting with "منتجات غير موجودة:" and containing a JSON array
        if ($statusCode === 400 && is_string($error) && str_starts_with($error, 'منتجات غير موجودة:')) {
            $refs = $this->extractMissingProductReferences($error);

            if (! empty($refs)) {
                $names = $this->getProductNamesByReferences($refs);

                return [
                    'success' => false,
                    'message' => 'المنتجات التالية غير موجودة بهذا الفرع: '.implode(', ', $names),
                    'notFoundProducts' => $refs,
                ];
            }
        }

        // 2. status 400 AND error is any other non-empty string (Scenario A, e.g. "الوردية غير صحيحة")
        if ($statusCode === 400 && is_string($error) && $error !== '') {
            throw new Exception($error);
        }

        // 3. status 422 AND message is a non-empty string
        $message = $responseData['message'] ?? null;
        if ($statusCode === 422 && is_string($message) && $message !== '') {
            throw new Exception($message);
        }

        // 4. anything else -> log a warning (keep the existing log call) and throw generic exception
        Log::warning('POS error received without recognized missing products', [
            'status' => $statusCode,
            'branch_id' => $branch->id,
            'response' => $responseData,
        ]);

        throw new Exception('لا يمكن التواصل مع الفرع في الوقت الحالي');
    }

    /**
     * Extract missing product references from error string.
     *
     * @return array<string>
     */
    private function extractMissingProductReferences(string $error): array
    {
        if (preg_match('/\[.*\]/s', $error, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (! is_array($decoded)) {
                $decoded = json_decode(stripslashes($matches[0]), true);
            }

            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }

        return [];
    }

    /**
     * Get product names by their POS references from master repository.
     *
     * @param  array<string>  $posRefs
     * @return array<string>
     */
    private function getProductNamesByReferences(array $posRefs): array
    {
        try {
            $products = $this->posMasterClient->getProductsByReferences($posRefs);

            $names = collect($products)->pluck('name')->filter()->toArray();

            return ! empty($names) ? $names : $posRefs;
        } catch (Exception $e) {
            Log::error('Failed to get product names by references', [
                'refs' => $posRefs,
                'error' => $e->getMessage(),
            ]);

            return $posRefs;
        }
    }
}
