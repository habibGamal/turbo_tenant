<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CustomerFavorites\src\DTOs\CustomerFavoritesConfig;
use App\Plugins\CustomerFavorites\src\Http\Controllers\FavoritesController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('inertia.testing.page_paths', array_merge(
        config('inertia.testing.page_paths', []),
        [resource_path('js/themes/default/pages')]
    ));
});

it('resolves legacy controller alias to plugin favorites controller', function () {
    expect(class_exists(\App\Http\Controllers\FavoritesController::class))->toBeTrue();

    $controller = app(\App\Http\Controllers\FavoritesController::class);
    expect($controller)->toBeInstanceOf(FavoritesController::class);
});

it('binds CustomerFavoritesConfig DTO via composition root', function () {
    $config = app(CustomerFavoritesConfig::class);

    expect($config)->toBeInstanceOf(CustomerFavoritesConfig::class)
        ->and($config->enabled)->toBeTrue();
});

it('renders favorites page via inertia for guests', function () {
    $response = $this->get(route('favorites.index'));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Favorites')
        );
});

it('renders favorites page via inertia for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('favorites.index'));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Favorites')
        );
});
