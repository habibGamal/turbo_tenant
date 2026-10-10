<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Services\POS\POSApiClient;
use Illuminate\Support\Facades\Http;

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
});
