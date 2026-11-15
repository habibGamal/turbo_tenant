<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use App\Plugins\PosIntegration\src\Services\POS\POSErrorResponseHandler;

beforeEach(function () {
    $this->posMasterClient = mock(PosMasterClient::class);
    $this->handler = new POSErrorResponseHandler($this->posMasterClient);
    $this->branch = Branch::factory()->make();
});

describe('POSErrorResponseHandler', function () {
    it('handles Scenario B format with error string containing missing product refs (doc: Responses section 3 Scenario B)', function () {
        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: ["BURGER-REF-1", "DRINK-REF-2"]',
        ];

        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['BURGER-REF-1', 'DRINK-REF-2'])
            ->andReturn([
                ['name' => 'Beef Burger', 'posRef' => 'BURGER-REF-1'],
                ['name' => 'Pepsi', 'posRef' => 'DRINK-REF-2'],
            ]);

        $result = $this->handler->handle($responseData, 400, $this->branch);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: Beef Burger, Pepsi',
            'notFoundProducts' => ['BURGER-REF-1', 'DRINK-REF-2'],
        ]);
    });

    it('handles Scenario B with escaped backslash quotes in error string (doc: Responses section 3 Scenario B)', function () {
        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: [\"ESCAPED-101\"]',
        ];

        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['ESCAPED-101'])
            ->andReturn([
                ['name' => 'Salad', 'posRef' => 'ESCAPED-101'],
            ]);

        $result = $this->handler->handle($responseData, 400, $this->branch);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: Salad',
            'notFoundProducts' => ['ESCAPED-101'],
        ]);
    });

    it('falls back to raw references if product importer throws exception (doc: Responses section 3 Scenario B)', function () {
        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: ["RAW-REF-001"]',
        ];

        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['RAW-REF-001'])
            ->andThrow(new Exception('Network error'));

        $result = $this->handler->handle($responseData, 400, $this->branch);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: RAW-REF-001',
            'notFoundProducts' => ['RAW-REF-001'],
        ]);
    });

    it('falls back to raw references if product importer returns empty array (doc: Responses section 3 Scenario B)', function () {
        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: ["RAW-REF-002"]',
        ];

        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['RAW-REF-002'])
            ->andReturn([]);

        $result = $this->handler->handle($responseData, 400, $this->branch);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: RAW-REF-002',
            'notFoundProducts' => ['RAW-REF-002'],
        ]);
    });

    it('throws shift error exception for Scenario A (doc: Responses section 3 Scenario A)', function () {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'الوردية غير صحيحة',
        ];

        expect(fn () => $this->handler->handle($responseData, 400, $this->branch))
            ->toThrow(Exception::class, 'الوردية غير صحيحة');
    });

    it('throws validation message exception for status 422 with message (doc: Responses section 2 Validation Failure)', function () {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        $responseData = [
            'message' => 'The order.type field must be one of: web_delivery, web_takeaway.',
            'errors' => [
                'order.type' => [
                    'The order.type field must be one of: web_delivery, web_takeaway.',
                ],
            ],
        ];

        expect(fn () => $this->handler->handle($responseData, 422, $this->branch))
            ->toThrow(Exception::class, 'The order.type field must be one of: web_delivery, web_takeaway.');
    });

    it('throws generic communication exception for legacy formats (doc: Responses strict compliance)', function (array $responseData) {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        expect(fn () => $this->handler->handle($responseData, 400, $this->branch))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');
    })->with('legacyMissingProductFormats');

    it('throws generic communication exception on unexpected error responses (doc: Responses generic fallback)', function (array $responseData, int $status) {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        expect(fn () => $this->handler->handle($responseData, $status, $this->branch))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');
    })->with('unexpectedErrorResponses');
});

dataset('legacyMissingProductFormats', [
    'notFoundProducts array key' => [
        [
            'message' => 'Product not found',
            'notFoundProducts' => ['PROD-001', 'PROD-002'],
        ],
    ],
    'missing_products array key' => [
        [
            'message' => 'Product not found',
            'missing_products' => ['ALT-001'],
        ],
    ],
    'not_found_products array key' => [
        [
            'message' => 'Product not found',
            'not_found_products' => ['LEG-001'],
        ],
    ],
    'message fallback without error key' => [
        [
            'message' => 'منتجات غير موجودة: ["PROD-001"]',
        ],
    ],
]);

dataset('unexpectedErrorResponses', [
    '500 with message' => [
        ['message' => 'Internal POS failure'],
        500,
    ],
    '500 with error on non-400 status' => [
        ['error' => 'الوردية غير صحيحة'],
        500,
    ],
    '404 empty body' => [
        [],
        404,
    ],
    '422 without message' => [
        ['errors' => ['order.type' => ['Required']]],
        422,
    ],
    '400 without error' => [
        ['message' => 'فشل في إنشاء الطلب'],
        400,
    ],
]);
