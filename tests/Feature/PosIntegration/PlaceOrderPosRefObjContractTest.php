<?php

declare(strict_types=1);

use App\Enums\OrderType;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\ProductPOSImporterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->productImporter = mock(ProductPOSImporterService::class);
    $this->app->instance(ProductPOSImporterService::class, $this->productImporter);
    $this->service = app(OrderPOSService::class);
    Http::preventStrayRequests();
});

describe('POST /api/web-orders/place-order posRefObj contract', function () {
    it('formats simple 1-to-1 product mapping as posRefObj with quantity (doc: Deep Dive: posRefObj Mapping Pattern - Pattern 1)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'BURGER-BEEF-SINGLE',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'notes' => 'بدون بصل',
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([
            [
                'quantity' => 2.0,
                'notes' => 'بدون بصل',
                'posRefObj' => [
                    [
                        'productRef' => 'BURGER-BEEF-SINGLE',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);
    });

    it('formats combo bundle with mapped extras and weighted multiplier (doc: Deep Dive: posRefObj Mapping Pattern - Pattern 2)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $weightOption = WeightOption::factory()->create();
        $weightOptionValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
            'label' => '500g',
            'value' => '0.500',
        ]);

        $extraOption = ExtraOption::factory()->create();
        $extraOptionItem = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'صلصة خاصة',
            'pos_mapping_type' => 'pos_item',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'STEAK-MEAT',
            'branch_id' => $branch->id,
        ]);

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $extraOptionItem->id,
            'pos_item_id' => 'EXTRA-SAUCE',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'weight_option_value_id' => $weightOptionValue->id,
            'weight_multiplier' => 2,
            'notes' => 'اللحم مستوي',
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraOptionItem->id,
            'extra_name' => 'صلصة خاصة',
            'quantity' => 2,
        ]);

        $this->service->placeOrder($order);

        $expectedNotes = 'json::'.json_encode([
            'ملاحظات' => 'اللحم مستوي',
            'الوزن' => '(2 x) 500g',
            'اضافات' => 'صلصة خاصة (x2)',
        ], JSON_UNESCAPED_UNICODE);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([
            [
                'quantity' => 1.0,
                'notes' => $expectedNotes,
                'posRefObj' => [
                    [
                        'productRef' => 'STEAK-MEAT',
                        'quantity' => 1,
                    ],
                    [
                        'productRef' => 'EXTRA-SAUCE',
                        'quantity' => 4.0,
                    ],
                ],
            ],
        ]);
    });

    it('filters extras by pos_mapping_type, extra_option_item_id, and mapping presence (doc: Deep Dive: posRefObj Mapping Pattern)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'BURGER-MAIN',
            'branch_id' => $branch->id,
        ]);

        $extraOption = ExtraOption::factory()->create();

        // Extra 1: pos_mapping_type = 'notes'
        $notesExtra = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'مخلل',
            'pos_mapping_type' => 'notes',
        ]);
        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $notesExtra->id,
            'pos_item_id' => 'SHOULD-NOT-APPEAR',
            'branch_id' => $branch->id,
        ]);

        // Extra 2: pos_mapping_type = 'pos_item' but NO mapping row
        $unmappedExtra = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'جبنة إضافية',
            'pos_mapping_type' => 'pos_item',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        // Extra 1 (type notes)
        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $notesExtra->id,
            'extra_name' => 'مخلل',
            'quantity' => 1,
        ]);

        // Extra 2 (no mapping row)
        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $unmappedExtra->id,
            'extra_name' => 'جبنة إضافية',
            'quantity' => 1,
        ]);

        // Extra 3 (no extra_option_item_id)
        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => null,
            'extra_name' => 'بهارات خاصة',
            'quantity' => 1,
        ]);

        $this->service->placeOrder($order);

        $expectedNotes = 'json::'.json_encode([
            'اضافات' => 'مخلل (x1), جبنة إضافية (x1), بهارات خاصة (x1)',
        ], JSON_UNESCAPED_UNICODE);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([
            [
                'quantity' => 1.0,
                'notes' => $expectedNotes,
                'posRefObj' => [
                    [
                        'productRef' => 'BURGER-MAIN',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);
    });

    it('resolves variants prioritizing variant mapping, falling back to base with notes, or variant-only (doc: Deep Dive: posRefObj Mapping Pattern)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();

        // Product 1: variant mapping wins over base
        $prod1 = Product::factory()->create();
        $var1 = ProductVariant::factory()->create(['product_id' => $prod1->id, 'name' => 'Double']);
        ProductPosMapping::factory()->create([
            'product_id' => $prod1->id,
            'variant_id' => null,
            'pos_item_id' => 'BASE-P1',
            'branch_id' => $branch->id,
        ]);
        ProductPosMapping::factory()->create([
            'product_id' => $prod1->id,
            'variant_id' => $var1->id,
            'pos_item_id' => 'VAR-P1',
            'branch_id' => $branch->id,
        ]);

        // Product 2: no variant mapping -> fall back to base and put variant name in notes
        $prod2 = Product::factory()->create();
        $var2 = ProductVariant::factory()->create(['product_id' => $prod2->id, 'name' => 'حجم عائلي']);
        ProductPosMapping::factory()->create([
            'product_id' => $prod2->id,
            'variant_id' => null,
            'pos_item_id' => 'BASE-P2',
            'branch_id' => $branch->id,
        ]);

        // Product 3: variant-only mapping (no base mapping)
        $prod3 = Product::factory()->create();
        $var3 = ProductVariant::factory()->create(['product_id' => $prod3->id, 'name' => 'Single']);
        ProductPosMapping::factory()->create([
            'product_id' => $prod3->id,
            'variant_id' => $var3->id,
            'pos_item_id' => 'VAR-ONLY-P3',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prod1->id,
            'variant_id' => $var1->id,
            'quantity' => 1,
            'notes' => 'سريع',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prod2->id,
            'variant_id' => $var2->id,
            'quantity' => 1,
            'notes' => 'بدون شطة',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prod3->id,
            'variant_id' => $var3->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([
            [
                'quantity' => 1.0,
                'notes' => 'سريع',
                'posRefObj' => [
                    [
                        'productRef' => 'VAR-P1',
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'quantity' => 1.0,
                'notes' => 'json::'.json_encode(['ملاحظات' => 'بدون شطة', 'النوع' => 'حجم عائلي'], JSON_UNESCAPED_UNICODE),
                'posRefObj' => [
                    [
                        'productRef' => 'BASE-P2',
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'quantity' => 1.0,
                'notes' => '',
                'posRefObj' => [
                    [
                        'productRef' => 'VAR-ONLY-P3',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);
    });

    it('prioritizes branch-specific mapping over global and ignores other branch mappings (doc: Deep Dive: posRefObj Mapping Pattern)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $otherBranch = Branch::factory()->create(['link' => 'https://pos2.example.com']);
        $user = User::factory()->create();

        // Product A: branch-specific and global -> branch wins
        $prodA = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $prodA->id,
            'branch_id' => null,
            'pos_item_id' => 'GLOBAL-A',
        ]);
        ProductPosMapping::factory()->create([
            'product_id' => $prodA->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'BRANCH-A',
        ]);

        // Product B: global only -> global is used
        $prodB = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $prodB->id,
            'branch_id' => null,
            'pos_item_id' => 'GLOBAL-B',
        ]);

        // Product C: other branch only -> ignored, item dropped
        $prodC = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $prodC->id,
            'branch_id' => $otherBranch->id,
            'pos_item_id' => 'OTHER-BRANCH-C',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodA->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodB->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodC->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([
            [
                'quantity' => 1.0,
                'notes' => '',
                'posRefObj' => [
                    [
                        'productRef' => 'BRANCH-A',
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'quantity' => 1.0,
                'notes' => '',
                'posRefObj' => [
                    [
                        'productRef' => 'GLOBAL-B',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);
    });

    it('formats plain note as string and empty note as empty string (doc: Request Payload Requirements)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $prodA = Product::factory()->create();
        $prodB = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $prodA->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'PROD-A',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $prodB->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'PROD-B',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodA->id,
            'quantity' => 1,
            'notes' => 'يرجى وضع الكاتشب بالخارج',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodB->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'][0]['notes'])->toBe('يرجى وضع الكاتشب بالخارج')
            ->and($body['order']['items'][1]['notes'])->toBe('');
    });

    it('loads all product pos mappings in exactly one database query per order (doc: Deep Dive: posRefObj Mapping Pattern)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $prodA = Product::factory()->create();
        $prodB = Product::factory()->create();
        $extra = ExtraOptionItem::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $prodA->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'REF-A',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $prodB->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'REF-B',
        ]);

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $extra->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'REF-EXTRA',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $itemA = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodA->id,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $itemA->id,
            'extra_option_item_id' => $extra->id,
            'quantity' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $prodB->id,
            'quantity' => 1,
        ]);

        DB::connection('tenant')->enableQueryLog();
        DB::connection('tenant')->flushQueryLog();

        $this->service->placeOrder($order);

        $queries = DB::connection('tenant')->getQueryLog();
        $mappingQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'product_pos_mappings'));

        expect(count($mappingQueries))->toBe(1);
    });

    it('prevents duplicate mapping entries even when some columns are NULL (doc: posRefObj mapping)', function () {
        $product = Product::factory()->create();

        ProductPosMapping::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'POS-1',
        ]);

        expect(function () use ($product) {
            ProductPosMapping::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'extra_option_item_id' => null,
                'branch_id' => null,
                'pos_item_id' => 'POS-2',
            ]);
        })->toThrow(Illuminate\Database\QueryException::class);
    });

    it('uses snapshot extra_name and variant_name in item notes, falling back only when empty (doc: Request Payload Requirements order.items.*.notes)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Live Variant Name',
        ]);
        $extraItem = ExtraOptionItem::factory()->create([
            'name' => 'Live Extra Name',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BASE',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        // Item 1: with snapshot names
        $item1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'variant_name' => 'Snapshot Variant Name',
            'quantity' => 1,
            'notes' => null,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item1->id,
            'extra_option_item_id' => $extraItem->id,
            'extra_name' => 'Snapshot Extra Name',
            'quantity' => 1,
        ]);

        // Item 2: fallback when snapshot names are null/empty
        $item2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'variant_name' => null,
            'quantity' => 1,
            'notes' => null,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item2->id,
            'extra_option_item_id' => $extraItem->id,
            'extra_name' => '',
            'quantity' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        $items = $body['order']['items'];

        expect($items[0]['notes'])->toBe('json::'.json_encode([
            'اضافات' => 'Snapshot Extra Name (x1)',
            'النوع' => 'Snapshot Variant Name',
        ], JSON_UNESCAPED_UNICODE))
            ->and($items[1]['notes'])->toBe('json::'.json_encode([
                'اضافات' => 'Live Extra Name (x1)',
                'النوع' => 'Live Variant Name',
            ], JSON_UNESCAPED_UNICODE));
    });
});
