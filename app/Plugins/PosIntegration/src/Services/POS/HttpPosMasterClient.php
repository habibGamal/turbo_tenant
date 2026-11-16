<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\PosIntegration\src\DTOs\PosIntegrationConfig;
use App\Plugins\PosIntegration\src\Exceptions\PosMasterUnavailable;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HttpPosMasterClient implements PosMasterClient
{
    public function __construct(
        private readonly PosIntegrationConfig $config
    ) {}

    /**
     * Get products by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductsByIds(array $ids): array
    {
        return $this->fetch('/api/get-products-master', ['ids' => $ids], 10);
    }

    /**
     * Get all product references from the master repository
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getAllProductReferences(): array
    {
        return $this->fetch('/api/all-products-refs-master', [], 5);
    }

    /**
     * Get all product prices from the master repository
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getAllProductPrices(): array
    {
        return $this->fetch('/api/all-products-prices-master', [], 5);
    }

    /**
     * Get product prices by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductPricesByIds(array $ids): array
    {
        return $this->fetch('/api/get-products-prices-master', ['ids' => $ids], 10);
    }

    /**
     * Get products by their references from the master repository
     *
     * @param  array<string>  $refs
     * @return array<int, array<string, mixed>>
     *
     * @throws PosMasterUnavailable
     */
    public function getProductsByReferences(array $refs): array
    {
        return $this->fetch('/api/get-products-master-by-refs', ['refs' => $refs], 10);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     *
     * @throws PosMasterUnavailable
     */
    private function fetch(string $path, array $query = [], int $timeout = 5): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        $queryString = [];
        foreach ($query as $key => $value) {
            $formattedValue = is_array($value) ? ','.implode(',', $value) : (string) $value;
            $queryString[] = $key.'='.$formattedValue;
        }

        $url = $baseUrl.$path.(! empty($queryString) ? '?'.implode('&', $queryString) : '');

        try {
            // The master POS answers 401 without its key.
            $headers = $this->config->masterApiKey !== '' ? ['X-Api-Key' => $this->config->masterApiKey] : [];

            $response = Http::withHeaders($headers)->timeout($timeout)->connectTimeout(3)->get($url);
        } catch (Throwable $e) {
            Log::error('POS master request failed', ['path' => $path, 'query' => $query, 'error' => $e->getMessage()]);

            throw new PosMasterUnavailable(__('pos-integration::messages.master_unavailable'), 0, $e);
        }

        if (! $response->successful()) {
            Log::error('POS master returned an error', ['path' => $path, 'query' => $query, 'status' => $response->status()]);

            throw new PosMasterUnavailable(__('pos-integration::messages.master_unavailable'));
        }

        return (array) $response->json();
    }

    /**
     * Get the master products repository base URL
     *
     * @throws PosMasterUnavailable
     */
    private function getMasterRepoUrl(): string
    {
        $url = $this->config->masterRepoUrl;

        if (empty($url)) {
            throw new PosMasterUnavailable(__('pos-integration::messages.master_unavailable'));
        }

        return $url;
    }
}
