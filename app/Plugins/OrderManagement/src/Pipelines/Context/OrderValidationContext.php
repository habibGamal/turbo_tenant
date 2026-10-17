<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Context;

use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;

final class OrderValidationContext
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $isAuthorized = false;

    /**
     * @param  array<string, mixed>  $cart
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PlaceOrderData $data,
        public readonly array $cart,
        public readonly array $payload = []
    ) {
        $this->isAuthorized = ! $data->isGuest();
    }

    public function getUser(): object
    {
        return $this->data->user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCartItems(): array
    {
        return $this->cart['items'] ?? [];
    }

    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function authorize(): self
    {
        $this->isAuthorized = true;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->isAuthorized;
    }
}
