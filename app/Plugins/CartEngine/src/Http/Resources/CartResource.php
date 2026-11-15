<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Resources;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property list<CartLine>|iterable<CartLine> $resource
 */
final class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = is_array($this->resource) ? $this->resource : iterator_to_array($this->resource);

        if (empty($lines)) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $productIdsToLoad = array_values(array_unique(array_filter(
            array_map(
                fn (CartLine $line) => $line->sellByWeight ? $line->productId : null,
                $lines
            )
        )));

        $products = empty($productIdsToLoad)
            ? collect()
            : Product::with('weightOption.values')->whereIn('id', $productIdsToLoad)->get()->keyBy('id');

        $items = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $total += $line->subtotal;
            $product = $line->productId !== null ? $products->get($line->productId) : null;
            $items[] = (new CartItemResource($line, $product))->toArray($request);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
