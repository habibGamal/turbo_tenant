<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\ProductPosMapping;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OrderPOSService
{
    public function __construct(
        private readonly ProductPOSImporterService $productImporter
    ) {}

    /**
     * Get the current shift ID from the POS system
     *
     * @throws Exception
     */
    public function getShiftId(Branch $branch): int
    {
        $url = $this->getBranchUrl($branch, '/api/get-shift-id');

        try {
            $response = Http::withOptions([
                'verify' => false, // Handle self-signed certificates
            ])->timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('حدث خطاء اثناء الاستعلام عن رقم الوردية');
            }

            $data = $response->json();

            return $data['shift_id'] ?? throw new Exception('حدث خطاء اثناء الاستعلام عن رقم الوردية');
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
     * Check if the branch can accept orders
     *
     * @throws Exception
     */
    public function canAcceptOrder(Branch $branch): bool
    {
        $url = $this->getBranchUrl($branch, '/api/can-accept-order');

        try {
            $response = Http::withOptions([
                'verify' => false, // Handle self-signed certificates
            ])->timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('حدث خطاء اثناء الاستعلام عن قبول الطلبات', 'branch_not_accepting_orders');
            }

            $data = $response->json();

            return $data['can_accept'] ?? false;
        } catch (Exception $e) {
            Log::error('Failed to check if branch can accept orders', [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('حدث خطاء اثناء الاستعلام عن قبول الطلبات', 'branch_not_accepting_orders');
        }
    }

    /**
     * Place an order with the POS system
     *
     * @return array{success: bool, message?: string, notFoundProducts?: array<string>}
     *
     * @throws Exception
     */
    public function placeOrder(Order $order): array
    {
        // Load required relationships
        $order->load([
            'user',
            'address.area.governorate',
            'branch',
            'items.product',
            'items.variant',
            'items.extras.extraOptionItem',
        ]);

        $branch = $order->branch;

        if (! $branch) {
            throw new Exception('Order does not have a branch assigned');
        }

        // Build the order payload
        $payload = $this->buildOrderPayload($order);

        // Send order to POS
        $url = $this->getBranchUrl($branch, '/api/web-orders/place-order');

        try {
            logger()->info('Url', ['url' => $url,'payload' => $payload]);
            $response = Http::withOptions([
                'verify' => false, // Handle self-signed certificates
            ])->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post($url, $payload);
            logger()->info('POS Order Payload', ['response' => $response]);
            if (! $response->successful()) {
                $data = $response->json();

                // Check for product not found error
                if (isset($data['message']) && $data['message'] === 'Product not found') {
                    $notFoundProducts = $data['notFoundProducts'] ?? [];

                    // Get product names from master repository
                    $productNames = $this->getProductNamesByReferences($notFoundProducts);

                    return [
                        'success' => false,
                        'message' => 'المنتجات التالية غير موجودة بهذا الفرع: '.implode(', ', $productNames),
                        'notFoundProducts' => $notFoundProducts,
                    ];
                }

                throw new Exception('لا يمكن التواصل مع الفرع في الوقت الحالي');
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::error('Failed to place order with POS', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن التواصل مع الفرع في الوقت الحالي');
        }
    }

    /**
     * Build the order payload for the POS system
     *
     * @return array{user: array, order: array}
     */
    private function buildOrderPayload(Order $order): array
    {
        $user = $order->user;
        $address = $order->address;

        // Build user data
        $userData = [
            'name' => $user->name,
            'phone' => $address->phone_number,
            'area' => $address?->area?->name ?? 'N/A',
            'address' => $address ? $this->formatAddress($address) : 'N/A',
        ];

        // Build order items
        $orderItems = [];

        foreach ($order->items as $item) {
            $posRefs = $this->buildPosReferences($item);

            $orderItems[] = [
                'quantity' => (int) $item->quantity,
                'notes' => $item->notes,
                'posRefObj' => $posRefs,
            ];
        }

        // Build web preferences (payment info)
        $webPreferences = null;

        if ($order->payment_method) {
            $webPreferences = [
                'payment_method' => $order->payment_method->value,
            ];

            if ($order->transaction_id) {
                $webPreferences['transaction_id'] = $order->transaction_id;
            }
        }

        // Build order data
        $orderData = [
            'type' => $order->type === 'delivery' ? 'web_delivery' : 'web_takeaway',
            'shiftId' => $order->shift_id,
            'orderNumber' => $order->order_number,
            'subTotal' => $order->sub_total,
            'tax' => $order->tax,
            'service' => $order->service,
            'discount' => $order->discount,
            'total' => $order->total,
            'items' => $orderItems,
        ];

        if ($order->note) {
            $orderData['note'] = $order->note;
        }

        if ($webPreferences) {
            $orderData['webPreferences'] = $webPreferences;
        }

        return [
            'user' => $userData,
            'order' => $orderData,
        ];
    }

    /**
     * Build POS references for an order item
     *
     * @return array<array{productRef: string, quantity: int}>
     */
    private function buildPosReferences($item): array
    {
        $posRefs = [];

        // Get the main product POS reference
        $productMapping = ProductPosMapping::query()
            ->where('product_id', $item->product_id)
            ->whereNull('variant_id')
            ->whereNull(columns: 'extra_option_item_id')
            ->where(function ($query) use ($item) {
                $query->where('branch_id', $item->order->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->first();

        if ($productMapping) {
            $posRefs[] = [
                'productRef' => $productMapping->pos_item_id,
                'quantity' => 1,
            ];
        }

        // Get variant POS reference if exists
        if ($item->variant_id) {
            $variantMapping = ProductPosMapping::query()
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->where(function ($query) use ($item) {
                    $query->where('branch_id', $item->order->branch_id)
                        ->orWhereNull('branch_id');
                })
                ->first();

            if ($variantMapping) {
                $posRefs[] = [
                    'productRef' => $variantMapping->pos_item_id,
                    'quantity' => 1,
                ];
            }
        }

        // Get extras POS references
        foreach ($item->extras as $extra) {
            $extraMapping = ProductPosMapping::query()
                ->where('extra_option_item_id', $extra->extra_option_item_id)
                ->where(function ($query) use ($item) {
                    $query->where('branch_id', $item->order->branch_id)
                        ->orWhereNull('branch_id');
                })
                ->first();

            if ($extraMapping) {
                $posRefs[] = [
                    'productRef' => $extraMapping->pos_item_id,
                    'quantity' => $extra->quantity,
                ];
            }
        }

        return $posRefs;
    }

    /**
     * Format address for display
     */
    private function formatAddress($address): string
    {
        $parts = [];

        if ($address->street) {
            $parts[] = $address->street;
        }

        if ($address->building) {
            $parts[] = 'Building '.$address->building;
        }

        if ($address->apartment) {
            $parts[] = 'Apartment '.$address->apartment;
        }

        if ($address->area) {
            $parts[] = $address->area->name;
        }

        if ($address->area?->governorate) {
            $parts[] = $address->area->governorate->name;
        }

        return implode(', ', $parts);
    }

    /**
     * Get product names by their POS references
     *
     * @param  array<string>  $posRefs
     * @return array<string>
     */
    private function getProductNamesByReferences(array $posRefs): array
    {
        try {
            $products = $this->productImporter->getProductsByReferences($posRefs);

            return collect($products)->pluck('name')->toArray();
        } catch (Exception $e) {
            Log::error('Failed to get product names by references', [
                'refs' => $posRefs,
                'error' => $e->getMessage(),
            ]);

            return $posRefs; // Return the refs if we can't get the names
        }
    }

    /**
     * Get the full URL for a branch endpoint
     */
    private function getBranchUrl(Branch $branch, string $endpoint): string
    {
        $baseUrl = mb_rtrim($branch->link, '/');

        return $baseUrl.$endpoint;
    }
}
