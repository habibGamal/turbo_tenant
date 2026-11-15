<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single JSON envelope for every storefront API endpoint.
 *
 *   success: { "status": "success", "data": ..., "message"?: string, "meta"?: {...} }
 *   error:   { "status": "error", "message": string, "errors"?: { field: [messages] } }
 *
 * Paginated lists put the page info in "meta": current_page, last_page, per_page, total.
 * TypeScript mirror: resources/js/core/api/types.ts.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['status' => 'success', 'data' => $data];

        if ($message !== null) {
            $body['message'] = $message;
        }

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource  wraps each item when given
     */
    public static function paginated(LengthAwarePaginator $paginator, ?string $resource = null): JsonResponse
    {
        $items = $resource !== null
            ? $resource::collection($paginator->items())
            : $paginator->items();

        return self::success($items, meta: [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @param  array<string, array<int, string>>|MessageBag|null  $errors
     */
    public static function error(string $message, int $status = 400, array|MessageBag|null $errors = null): JsonResponse
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== null) {
            $body['errors'] = $errors instanceof MessageBag ? $errors->toArray() : $errors;
        }

        return new JsonResponse($body, $status);
    }
}
