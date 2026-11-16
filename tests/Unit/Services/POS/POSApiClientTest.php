<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\POSApiClient;
use App\Support\Network\InvalidEndpointException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->client = new POSApiClient();
});

describe('POSApiClient - getShiftId', function () {
    it('retrieves shift ID from POS system sending GET with Accept application/json (doc: Endpoint 2)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([
                'shift_id' => 42,
            ], 200),
        ]);

        $shiftId = $this->client->getShiftId($branch);

        expect($shiftId)->toBe(42);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/get-shift-id'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    });

    it('throws exception when API request fails with 500 (doc: Endpoint 2 error handling)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([], 500),
        ]);

        expect(fn () => $this->client->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/get-shift-id'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    });

    it('throws exception when shift_id is null or missing in response (doc: Endpoint 2 note)', function (array $body) {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response($body, 200),
        ]);

        expect(fn () => $this->client->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/get-shift-id'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    })->with([
        'null shift_id' => [['shift_id' => null]],
        'missing shift_id' => [[]],
    ]);

    it('rejects private loopback host before sending request', function () {
        $branch = Branch::factory()->make([
            'link' => 'http://127.0.0.1:8000',
        ]);

        Http::fake();

        expect(fn () => $this->client->getShiftId($branch))
            ->toThrow(InvalidEndpointException::class);

        Http::assertNothingSent();
    });
});

describe('POSApiClient - canAcceptOrder', function () {
    it('returns true when branch accepts orders sending GET with Accept application/json (doc: Endpoint 1)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
        ]);

        expect($this->client->canAcceptOrder($branch))->toBeTrue();

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/can-accept-order'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    });

    it('returns false when branch does not accept orders (doc: Endpoint 1)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => false,
            ], 200),
        ]);

        expect($this->client->canAcceptOrder($branch))->toBeFalse();

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/can-accept-order'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    });

    it('throws exception when can-accept-order fails (doc: Endpoint 1 error handling)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([], 500),
        ]);

        expect(fn () => $this->client->canAcceptOrder($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن قبول الطلبات');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://pos.example.com/api/can-accept-order'
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === '';
        });
        Http::assertSentCount(1);
    });
});

describe('POSApiClient - sendOrder', function () {
    it('sends order payload to POS endpoint with exact headers, payload and returns exact structure (doc: Endpoint 3)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        $payload = [
            'user' => [
                'name' => 'أحمد مصطفى',
                'phone' => '01012345678',
                'area' => 'المعادي',
                'address' => 'شارع 9، عمارة 15',
            ],
            'order' => [
                'type' => 'web_delivery',
                'shiftId' => 42,
                'orderNumber' => 'WEB-10542',
                'subTotal' => 320.0,
                'tax' => 44.8,
                'service' => 25.0,
                'discount' => 20.0,
                'total' => 369.8,
                'note' => 'يرجى الاتصال عند الوصول',
                'webPreferences' => [
                    'payment_method' => 'cash',
                    'transaction_id' => null,
                ],
                'items' => [
                    [
                        'quantity' => 2,
                        'notes' => 'اللحم مستوي جيداً',
                        'posRefObj' => [
                            [
                                'productRef' => 'BURGER-DBL-CHEESE',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'تم إنشاء الطلب بنجاح',
            ], 200),
        ]);

        $result = $this->client->sendOrder($branch, $payload);

        expect($result)->toBe([
            'successful' => true,
            'status' => 200,
            'data' => [
                'message' => 'تم إنشاء الطلب بنجاح',
            ],
        ]);

        Http::assertSent(function (Request $request) use ($payload) {
            expect($request->data())->toBe($payload);

            return $request->method() === 'POST'
                && $request->url() === 'https://pos.example.com/api/web-orders/place-order'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Accept', 'application/json');
        });
        Http::assertSentCount(1);
    });

    it('returns unsuccessful result array on error responses 400, 422, 500 (doc: Endpoint 3 responses)', function (int $status, array $responseBody) {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        $payload = ['dummy' => 'payload'];

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response($responseBody, $status),
        ]);

        $result = $this->client->sendOrder($branch, $payload);

        expect($result)->toBe([
            'successful' => false,
            'status' => $status,
            'data' => $responseBody,
        ]);

        Http::assertSent(function (Request $request) use ($payload) {
            expect($request->data())->toBe($payload);

            return $request->method() === 'POST'
                && $request->url() === 'https://pos.example.com/api/web-orders/place-order'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Accept', 'application/json');
        });
        Http::assertSentCount(1);
    })->with([
        '400 bad request' => [400, ['message' => 'فشل في إنشاء الطلب', 'error' => 'الوردية غير صحيحة']],
        '422 unprocessable' => [422, ['message' => 'Validation error', 'errors' => ['order.type' => ['Invalid']]]],
        '500 server error' => [500, ['message' => 'Server Error']],
    ]);

    it('returns empty array in data when response body is not JSON (doc: Endpoint 3 transport)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        $payload = ['dummy' => 'payload'];

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response('oops', 500),
        ]);

        $result = $this->client->sendOrder($branch, $payload);

        expect($result)->toBe([
            'successful' => false,
            'status' => 500,
            'data' => [],
        ]);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://pos.example.com/api/web-orders/place-order'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Accept', 'application/json');
        });
        Http::assertSentCount(1);
    });

    it('throws exact exception when connection failure occurs (doc: Endpoint 3 transport)', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        expect(fn () => $this->client->sendOrder($branch, []))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');
    });

    it('no longer logs the payload or response body and only logs metadata', function () {
        Log::spy();

        $branch = Branch::factory()->make([
            'id' => 99,
            'link' => 'https://pos.example.com',
        ]);

        $payload = [
            'sensitive_credit_card' => '4111-2222-3333-4444',
            'order' => ['orderNumber' => 'WEB-9999'],
        ];

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'secret_pos_key' => 'very_confidential',
                'message' => 'Accepted',
            ], 200),
        ]);

        $this->client->sendOrder($branch, $payload);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'POS request completed') {
                return false;
            }

            // Assert metadata fields exist
            expect($context)->toHaveKeys(['branch_id', 'endpoint', 'order_number', 'status', 'duration_ms'])
                ->and($context['branch_id'])->toBe(99)
                ->and($context['endpoint'])->toBe('/api/web-orders/place-order')
                ->and($context['order_number'])->toBe('WEB-9999')
                ->and($context['status'])->toBe(200);

            // Assert sensitive content is omitted
            expect($context)->not->toHaveKey('payload')
                ->and($context)->not->toHaveKey('response')
                ->and($context)->not->toHaveKey('url');

            return true;
        });
    });
});

describe('POSApiClient - API key', function () {
    it('sends the branch POS key as X-Api-Key on every endpoint', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
            'pos_api_key' => 'branch-secret',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response(['shift_id' => 1], 200),
            'https://pos.example.com/api/can-accept-order' => Http::response(['can_accept' => true], 200),
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['status' => 'success'], 200),
        ]);

        $this->client->getShiftId($branch);
        $this->client->canAcceptOrder($branch);
        $this->client->sendOrder($branch, ['order' => ['orderNumber' => 'W-1']]);

        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request) => ! $request->hasHeader('X-Api-Key', 'branch-secret'));
    });

    it('sends no X-Api-Key header when the branch has no key', function () {
        $branch = Branch::factory()->make(['link' => 'https://pos.example.com']);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response(['shift_id' => 1], 200),
        ]);

        $this->client->getShiftId($branch);

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('X-Api-Key'));
    });
});

describe('Branch - POS API key storage', function () {
    it('stores the key encrypted and keeps it out of serialization', function () {
        $branch = Branch::factory()->create(['pos_api_key' => 'branch-secret']);

        $raw = Illuminate\Support\Facades\DB::table('branches')->where('id', $branch->id)->value('pos_api_key');

        expect($raw)->not->toBe('branch-secret')
            ->and($branch->fresh()->pos_api_key)->toBe('branch-secret')
            ->and($branch->toArray())->not->toHaveKey('pos_api_key');
    });
});
