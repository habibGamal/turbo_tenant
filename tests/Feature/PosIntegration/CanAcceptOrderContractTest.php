<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->service = app(OrderPOSService::class);
});

it('sends GET with Accept application/json to exact URL and returns true when store can accept orders (doc: Endpoint 1)', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    Http::fake([
        'https://pos.example.com/api/can-accept-order' => Http::response([
            'can_accept' => true,
        ], 200),
    ]);

    $result = $this->service->canAcceptOrder($branch);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/can-accept-order'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});

it('sends GET with Accept application/json to exact URL and returns false when store cannot accept orders (doc: Endpoint 1)', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    Http::fake([
        'https://pos.example.com/api/can-accept-order' => Http::response([
            'can_accept' => false,
        ], 200),
    ]);

    $result = $this->service->canAcceptOrder($branch);

    expect($result)->toBeFalse();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/can-accept-order'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});

it('normalizes branch link trailing slash to exact URL (doc: Endpoint 1 URL)', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com/',
    ]);

    Http::fake([
        'https://pos.example.com/api/can-accept-order' => Http::response([
            'can_accept' => true,
        ], 200),
    ]);

    $result = $this->service->canAcceptOrder($branch);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/can-accept-order'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});

it('throws exact exception on HTTP error or connection failure (doc: Endpoint 1 error handling)', function (string $type, ?int $status) {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    if ($type === 'connection') {
        Http::fake([
            'https://pos.example.com/api/can-accept-order' => fn () => throw new ConnectionException('down'),
        ]);
    } else {
        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([], (int) $status),
        ]);
    }

    expect(fn () => $this->service->canAcceptOrder($branch))
        ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن قبول الطلبات');

    if ($type !== 'connection') {
        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/can-accept-order'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    }
})->with([
    'HTTP 500' => ['http', 500],
    'HTTP 404' => ['http', 404],
    'connection failure' => ['connection', null],
]);

it('current behavior (not in doc): a 200 body {} -> false', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    Http::fake([
        'https://pos.example.com/api/can-accept-order' => Http::response([], 200),
    ]);

    $result = $this->service->canAcceptOrder($branch);

    expect($result)->toBeFalse();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/can-accept-order'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});
