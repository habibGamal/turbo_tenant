<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Context;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;

/**
 * Public API: other plugins' pipes receive this class. Change it compatibly.
 */
final class OrderValidationContext
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $isAuthorized = false;

    private ?int $appliedPromotionId = null;

    private ?string $appliedPromotionCode = null;

    /**
     * @param  list<CartLine>  $lines
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PlaceOrderData $data,
        public readonly array $lines,
        public readonly array $payload = []
    ) {
        $this->isAuthorized = ! $data->isGuest();
    }

    public function getUser(): object
    {
        return $this->data->user;
    }

    /**
     * @return list<CartLine>
     */
    public function getLines(): array
    {
        return $this->lines;
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

    public function applyPromotion(int $promotionId, string $promotionCode): void
    {
        $this->appliedPromotionId = $promotionId;
        $this->appliedPromotionCode = $promotionCode;
    }

    public function appliedPromotionId(): ?int
    {
        return $this->appliedPromotionId;
    }

    public function appliedPromotionCode(): ?string
    {
        return $this->appliedPromotionCode;
    }
}
