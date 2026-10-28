<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\POSApiClient;
use App\Support\Network\InvalidEndpointException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->client = new POSApiClient();
});

describe('POSApiClient - getShiftId', function () {
    it('retrieves shift ID from POS system', function () {
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

        Http::assertSent(function ($request) {
            return $request->url() === 'https://pos.example.com/api/get-shift-id';
        });
    });

    it('throws exception when API request fails with 500', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([], 500),
        ]);

        expect(fn () => $this->client->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');
    });

    it('throws exception when shift_id is null or missing in response', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([
                'shift_id' => null,
            ], 200),
        ]);

        expect(fn () => $this->client->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');
    });

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
    it('returns true when branch accepts orders', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
        ]);

        expect($this->client->canAcceptOrder($branch))->toBeTrue();
    });

    it('returns false when branch does not accept orders', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => false,
            ], 200),
        ]);

        expect($this->client->canAcceptOrder($branch))->toBeFalse();
    });

    it('throws exception when can-accept-order fails', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([], 500),
        ]);

        expect(fn () => $this->client->canAcceptOrder($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن قبول الطلبات');
    });
});

describe('POSApiClient - sendOrder', function () {
    it('sends order payload to POS endpoint with expected headers', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        $payload = [
            'user' => ['name' => 'Ahmed'],
            'order' => ['orderNumber' => 'WEB-1001'],
        ];

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'تم إنشاء الطلب بنجاح',
            ], 200),
        ]);

        $result = $this->client->sendOrder($branch, $payload);

        expect($result['successful'])->toBeTrue()
            ->and($result['status'])->toBe(200)
            ->and($result['data']['message'])->toBe('تم إنشاء الطلب بنجاح');

        Http::assertSent(function ($request) use ($payload) {
            return $request->url() === 'https://pos.example.com/api/web-orders/place-order'
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('Accept')[0] === 'application/json'
                && $request->data() === $payload;
        });
    });

    it('returns unsuccessful result array when POS returns 400 error', function () {
        $branch = Branch::factory()->make([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'Product not found',
                'notFoundProducts' => ['REF-999'],
            ], 400),
        ]);

        $result = $this->client->sendOrder($branch, []);

        expect($result['successful'])->toBeFalse()
            ->and($result['status'])->toBe(400)
            ->and($result['data']['notFoundProducts'])->toBe(['REF-999']);
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
