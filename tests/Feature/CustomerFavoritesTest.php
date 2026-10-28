<?php

declare(strict_types=1);

use App\Plugins\CustomerFavorites\src\DTOs\CustomerFavoritesConfig;

it('binds CustomerFavoritesConfig DTO via composition root', function () {
    $config = app(CustomerFavoritesConfig::class);

    expect($config)->toBeInstanceOf(CustomerFavoritesConfig::class)
        ->and($config->enabled)->toBeTrue();
});

it('returns customer favorites api response via rest endpoint', function () {
    $response = $this->getJson(route('api.customer-favorites.index'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'data' => [
                'enabled',
                'count',
                'products',
            ],
        ]);
});

it('syncs customer favorites via rest api', function () {
    $response = $this->postJson(route('api.customer-favorites.sync'), [
        'ids' => [1, 2, 3],
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'message' => 'Favorites synced successfully.',
            'data' => [
                'synced_ids' => [1, 2, 3],
            ],
        ]);
});

it('stores favorite item via rest api', function () {
    $response = $this->postJson(route('api.customer-favorites.store'), [
        'product_id' => 42,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'product_id' => 42,
            ],
        ]);
});
