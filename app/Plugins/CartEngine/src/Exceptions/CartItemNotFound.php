<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class CartItemNotFound extends RuntimeException
{
    public function __construct(string $message = 'Cart item not found')
    {
        parent::__construct($message);
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->is('api/*')) {
            return ApiResponse::error('Cart item not found', 404);
        }

        return null;
    }
}
