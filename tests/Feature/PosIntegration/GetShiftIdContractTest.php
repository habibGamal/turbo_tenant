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

it('sends GET with Accept application/json to exact URL and retrieves shift ID (doc: Endpoint 2)', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    Http::fake([
        'https://pos.example.com/api/get-shift-id' => Http::response([
            'shift_id' => 42,
        ], 200),
    ]);

    $shiftId = $this->service->getShiftId($branch);

    expect($shiftId)->toBe(42);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/get-shift-id'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});

it('throws exception when shift_id is null indicating no open shift (doc: Endpoint 2 note)', function () {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    Http::fake([
        'https://pos.example.com/api/get-shift-id' => Http::response([
            'shift_id' => null,
        ], 200),
    ]);

    expect(fn () => $this->service->getShiftId($branch))
        ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://pos.example.com/api/get-shift-id'
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '';
    });
    Http::assertSentCount(1);
});

it('throws exception when shift response is invalid or request fails (doc: Endpoint 2 error handling)', function (string $type, ?int $status, array $body) {
    $branch = Branch::factory()->make([
        'link' => 'https://pos.example.com',
    ]);

    if ($type === 'connection') {
        Http::fake([
            'https://pos.example.com/api/get-shift-id' => fn () => throw new ConnectionException('down'),
        ]);
    } else {
        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response($body, (int) $status),
        ]);
    }

    expect(fn () => $this->service->getShiftId($branch))
        ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');

    if ($type !== 'connection') {
        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/get-shift-id'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    }
})->with([
    'a 200 {}' => ['response', 200, []],
    'HTTP 500' => ['response', 500, []],
    'connection failure' => ['connection', null, []],
]);
