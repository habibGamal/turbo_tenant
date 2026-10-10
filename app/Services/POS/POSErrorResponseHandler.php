<?php

declare(strict_types=1);

namespace App\Services\POS;

use App\Models\Branch;
use App\Services\POS\Contracts\POSErrorResponseHandlerInterface;
use App\Services\ProductPOSImporterService;
use Exception;
use Illuminate\Support\Facades\Log;

final class POSErrorResponseHandler implements POSErrorResponseHandlerInterface
{
    public function __construct(
        private readonly ProductPOSImporterService $productImporter
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
        $notFoundProducts = $this->extractMissingProductReferences($responseData);

        if (! empty($notFoundProducts)) {
            $productNames = $this->getProductNamesByReferences($notFoundProducts);

            return [
                'success' => false,
                'message' => 'المنتجات التالية غير موجودة بهذا الفرع: '.implode(', ', $productNames),
                'notFoundProducts' => $notFoundProducts,
            ];
        }

        Log::warning('POS error received without recognized missing products', [
            'status' => $statusCode,
            'branch_id' => $branch->id,
            'response' => $responseData,
        ]);

        if (! empty($responseData['error']) && is_string($responseData['error'])) {
            throw new Exception($responseData['error']);
        }

        throw new Exception('لا يمكن التواصل مع الفرع في الوقت الحالي');
    }

    /**
     * Extract missing product references from various POS error response formats.
     *
     * @param  array<string, mixed>  $responseData
     * @return array<string>
     */
    private function extractMissingProductReferences(array $responseData): array
    {
        // 1. Array formats: notFoundProducts, missing_products, not_found_products
        if (! empty($responseData['notFoundProducts']) && is_array($responseData['notFoundProducts'])) {
            return array_values($responseData['notFoundProducts']);
        }

        if (! empty($responseData['missing_products']) && is_array($responseData['missing_products'])) {
            return array_values($responseData['missing_products']);
        }

        if (! empty($responseData['not_found_products']) && is_array($responseData['not_found_products'])) {
            return array_values($responseData['not_found_products']);
        }

        // 2. CREATE_ORDER_API_DOC.md Scenario B: error: "منتجات غير موجودة: [\"INVALID-REF-101\"]"
        $errorString = $responseData['error'] ?? $responseData['message'] ?? '';
        if (is_string($errorString) && str_contains($errorString, 'منتجات غير موجودة:')) {
            if (preg_match('/\[.*\]/s', $errorString, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (! is_array($decoded)) {
                    $decoded = json_decode(stripslashes($matches[0]), true);
                }
                if (is_array($decoded)) {
                    return array_values($decoded);
                }
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
            $products = $this->productImporter->getProductsByReferences($posRefs);

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
