<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS\Contracts;

use App\Plugins\PosIntegration\src\Exceptions\PosMasterUnavailable;

interface PosMasterClient
{
    /**
     * Get products by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductsByIds(array $ids): array;

    /**
     * Get all product references from the master repository
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getAllProductReferences(): array;

    /**
     * Get all product prices from the master repository
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getAllProductPrices(): array;

    /**
     * Get product prices by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductPricesByIds(array $ids): array;

    /**
     * Get products by their references from the master repository
     *
     * @param  array<string>  $refs
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductsByReferences(array $refs): array;
}
