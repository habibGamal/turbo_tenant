<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Catalog;

use ArrayAccess;
use BadMethodCallException;
use JsonSerializable;

/**
 * @implements ArrayAccess<string, string>
 */
final readonly class CatalogValidationIssue implements ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $id,
        public string $field,
        public string $problem,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['id', 'field', 'problem'], true);
    }

    public function offsetGet(mixed $offset): ?string
    {
        return match ($offset) {
            'id' => $this->id,
            'field' => $this->field,
            'problem' => $this->problem,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('CatalogValidationIssue is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('CatalogValidationIssue is immutable.');
    }

    /**
     * @return array{id: string, field: string, problem: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'field' => $this->field,
            'problem' => $this->problem,
        ];
    }

    /**
     * @return array{id: string, field: string, problem: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
