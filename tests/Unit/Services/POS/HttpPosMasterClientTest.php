<?php

declare(strict_types=1);

use App\Plugins\PosIntegration\src\DTOs\PosIntegrationConfig;
use App\Plugins\PosIntegration\src\Exceptions\PosMasterUnavailable;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use App\Plugins\PosIntegration\src\Services\POS\HttpPosMasterClient;
use Illuminate\Support\Facades\Http;

uses()->group('unit');

beforeEach(function () {
    $this->config = new PosIntegrationConfig(
        masterRepoUrl: 'https://master.example.com',
    );
    $this->client = new HttpPosMasterClient($this->config);
});

describe('HttpPosMasterClient', function () {
    it('implements PosMasterClient interface', function () {
        expect($this->client)->toBeInstanceOf(PosMasterClient::class);
    });

    it('fetches products by ids and verifies exact requested url', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1,2' => Http::response([
                ['id' => 1, 'name' => 'Pizza'],
            ], 200),
        ]);

        $result = $this->client->getProductsByIds([1, 2]);

        expect($result)->toBeArray()->toHaveCount(1)
            ->and($result[0]['name'])->toBe('Pizza');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/get-products-master?ids=,1,2';
        });
    });

    it('throws PosMasterUnavailable when masterRepoUrl is empty', function () {
        $client = new HttpPosMasterClient(new PosIntegrationConfig(masterRepoUrl: ''));

        expect(fn () => $client->getAllProductReferences())
            ->toThrow(PosMasterUnavailable::class, __('pos-integration::messages.master_unavailable'));
    });

    it('throws PosMasterUnavailable when HTTP request fails', function () {
        Http::fake([
            'https://master.example.com/api/all-products-refs-master' => Http::response([], 500),
        ]);

        expect(fn () => $this->client->getAllProductReferences())
            ->toThrow(PosMasterUnavailable::class, __('pos-integration::messages.master_unavailable'));
    });

    it('fetches all product references and verifies exact requested url', function () {
        Http::fake([
            'https://master.example.com/api/all-products-refs-master' => Http::response([
                ['id' => 1, 'posRef' => 'REF-1'],
            ], 200),
        ]);

        $result = $this->client->getAllProductReferences();

        expect($result)->toBeArray()->toHaveCount(1)
            ->and($result[0]['posRef'])->toBe('REF-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/all-products-refs-master';
        });
    });

    it('fetches all product prices and verifies exact requested url', function () {
        Http::fake([
            'https://master.example.com/api/all-products-prices-master' => Http::response([
                ['id' => 1, 'prices' => [['price' => 10.0]]],
            ], 200),
        ]);

        $result = $this->client->getAllProductPrices();

        expect($result)->toBeArray()->toHaveCount(1);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/all-products-prices-master';
        });
    });

    it('fetches product prices by ids and verifies exact requested url', function () {
        Http::fake([
            'https://master.example.com/api/get-products-prices-master?ids=,10' => Http::response([
                ['id' => 10, 'prices' => [['price' => 20.0]]],
            ], 200),
        ]);

        $result = $this->client->getProductPricesByIds([10]);

        expect($result)->toBeArray()->toHaveCount(1);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/get-products-prices-master?ids=,10';
        });
    });

    it('fetches products by references and verifies exact requested url', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master-by-refs?refs=,REF-A' => Http::response([
                ['id' => 1, 'posRef' => 'REF-A'],
            ], 200),
        ]);

        $result = $this->client->getProductsByReferences(['REF-A']);

        expect($result)->toBeArray()->toHaveCount(1)
            ->and($result[0]['posRef'])->toBe('REF-A');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/get-products-master-by-refs?refs=,REF-A';
        });
    });

    it('provides localized messages in arabic and english', function () {
        $previousLocale = app()->getLocale();

        try {
            app()->setLocale('ar');
            expect(__('pos-integration::messages.master_unavailable'))->toBe('لا يمكن الاتصال بالنقطة الرئيسية');

            app()->setLocale('en');
            expect(__('pos-integration::messages.master_unavailable'))->toBe('Cannot connect to master POS repository');
        } finally {
            app()->setLocale($previousLocale);
        }
    });
});
