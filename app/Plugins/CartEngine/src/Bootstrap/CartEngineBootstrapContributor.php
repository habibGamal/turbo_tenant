<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Bootstrap;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

final class CartEngineBootstrapContributor implements BootstrapContributor
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
    ) {}

    public function pluginId(): string
    {
        return 'cart-engine';
    }

    public function contribute(?Authenticatable $user): array
    {
        $cartCount = 0;
        $lines = [];
        try {
            $targetUser = $user instanceof User ? $user : null;
            $cartCount = $this->cartService->getCartCount($targetUser);
            $lines = $this->cartService->getLines($targetUser);
        } catch (Throwable) {
            $cartCount = 0;
            $lines = [];
        }

        return [
            'cart_count' => $cartCount,
            'query_data' => [
                [
                    'key' => ['cart'],
                    'data' => (new \App\Plugins\CartEngine\src\Http\Resources\CartResource($lines))->resolve(),
                ],
            ],
        ];
    }
}
