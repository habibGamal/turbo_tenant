<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Services\POS\POSErrorResponseHandler;
use App\Services\ProductPOSImporterService;

beforeEach(function () {
    $this->productImporter = mock(ProductPOSImporterService::class);
    $this->handler = new POSErrorResponseHandler($this->productImporter);
});

describe('POSErrorResponseHandler', function () {
    it('handles legacy Product not found format with notFoundProducts array', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'Product not found',
            'notFoundProducts' => ['PROD-001', 'PROD-002'],
        ];

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['PROD-001', 'PROD-002'])
            ->andReturn([
                ['name' => 'Pizza Margherita', 'posRef' => 'PROD-001'],
                ['name' => 'Cola', 'posRef' => 'PROD-002'],
            ]);

        $result = $this->handler->handle($responseData, 400, $branch);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Pizza Margherita')
            ->and($result['message'])->toContain('Cola')
            ->and($result['notFoundProducts'])->toBe(['PROD-001', 'PROD-002']);
    });

    it('handles CREATE_ORDER_API_DOC Scenario B format with error string containing missing product refs', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: ["BURGER-REF-1", "DRINK-REF-2"]',
        ];

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['BURGER-REF-1', 'DRINK-REF-2'])
            ->andReturn([
                ['name' => 'Beef Burger', 'posRef' => 'BURGER-REF-1'],
                ['name' => 'Pepsi', 'posRef' => 'DRINK-REF-2'],
            ]);

        $result = $this->handler->handle($responseData, 400, $branch);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Beef Burger')
            ->and($result['message'])->toContain('Pepsi')
            ->and($result['notFoundProducts'])->toBe(['BURGER-REF-1', 'DRINK-REF-2']);
    });

    it('falls back to raw references if product importer throws exception', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'Product not found',
            'notFoundProducts' => ['RAW-REF-001'],
        ];

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->andThrow(new Exception('Network error'));

        $result = $this->handler->handle($responseData, 400, $branch);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('RAW-REF-001')
            ->and($result['notFoundProducts'])->toBe(['RAW-REF-001']);
    });

    it('throws communication exception on unexpected error responses', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'Internal POS failure',
        ];

        expect(fn () => $this->handler->handle($responseData, 500, $branch))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');
    });

    it('handles missing_products array key format', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'Product not found',
            'missing_products' => ['ALT-001'],
        ];

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['ALT-001'])
            ->andReturn([
                ['name' => 'Fries', 'posRef' => 'ALT-001'],
            ]);

        $result = $this->handler->handle($responseData, 400, $branch);

        expect($result['success'])->toBeFalse()
            ->and($result['notFoundProducts'])->toBe(['ALT-001'])
            ->and($result['message'])->toContain('Fries');
    });

    it('handles Scenario B with escaped backslash quotes in error string', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'منتجات غير موجودة: [\"ESCAPED-101\"]',
        ];

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['ESCAPED-101'])
            ->andReturn([
                ['name' => 'Salad', 'posRef' => 'ESCAPED-101'],
            ]);

        $result = $this->handler->handle($responseData, 400, $branch);

        expect($result['success'])->toBeFalse()
            ->and($result['notFoundProducts'])->toBe(['ESCAPED-101']);
    });

    it('throws shift error exception for CREATE_ORDER_API_DOC Scenario A', function () {
        $branch = Branch::factory()->make();

        $responseData = [
            'message' => 'فشل في إنشاء الطلب',
            'error' => 'الوردية غير صحيحة',
        ];

        expect(fn () => $this->handler->handle($responseData, 400, $branch))
            ->toThrow(Exception::class, 'الوردية غير صحيحة');
    });
});
