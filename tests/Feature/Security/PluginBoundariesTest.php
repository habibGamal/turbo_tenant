<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use App\Plugins\PaymentProcessing\src\Services\PaymentStateMachine;
use App\Plugins\ProductReviews\src\Models\Review;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Livewire;
use Tests\Support\CartLines;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('security')]
final class PluginBoundariesTest extends TestCase
{
    public function test_with_guest_checkout_inactive_filament_orders_table_search_works(): void
    {
        $this->setPluginActive('guest-checkout', false);

        $order = Order::factory()->create([
            'order_number' => 'ORD-SEARCH-01',
        ]);

        Livewire::test(ListOrders::class)
            ->searchTable('ORD-SEARCH')
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_with_guest_checkout_inactive_coupon_service_first_order_check_does_not_throw(): void
    {
        $this->setPluginActive('guest-checkout', false);

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => now()->addDay(),
            'conditions' => [
                'usage_restrictions' => [
                    'first_order_only' => true,
                ],
            ],
        ]);

        $couponService = $this->app->make(CouponService::class);
        $context = new CouponContext(
            customer: null,
            lines: [],
            subtotal: 100.0,
            phone: '01099998888',
        );
        $result = $couponService->validateCoupon($coupon, $context);

        $this->assertTrue($result->isValid());
    }

    public function test_with_guest_checkout_active_guest_names_still_appear_and_are_searchable(): void
    {
        $this->setPluginActive('guest-checkout', true);

        $guestUser = GuestUser::factory()->create([
            'name' => 'DistinctGuestName',
            'phone' => '01012344321',
        ]);

        // Guest name/phone are snapshotted on the order at placement (and backfilled for legacy rows),
        // so OrderManagement never has to read GuestCheckout's table to display or search them.
        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'customer_name' => $guestUser->name,
            'customer_phone' => $guestUser->phone,
            'user_id' => null,
        ]);

        $this->assertSame('DistinctGuestName', $order->getCustomerName());

        Livewire::test(ListOrders::class)
            ->searchTable('DistinctGuestName')
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_moved_cross_plugin_relations_resolve_correctly(): void
    {
        $product = Product::factory()->create();
        $this->assertInstanceOf(HasMany::class, $product->orderItems());
        $this->assertInstanceOf(HasMany::class, $product->reviews());
        $this->assertInstanceOf(BelongsToMany::class, $product->packages());
        $this->assertInstanceOf(HasMany::class, $product->posMappings());

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $this->assertInstanceOf(HasMany::class, $variant->orderItems());
        $this->assertInstanceOf(HasMany::class, $variant->posMappings());

        $extraOption = ExtraOption::factory()->create();
        $extraOptionItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $this->assertInstanceOf(HasMany::class, $extraOptionItem->posMappings());

        $order = Order::factory()->create();
        $this->assertInstanceOf(BelongsTo::class, $order->guestUser());
    }

    public function test_coupon_id_is_stored_as_null_when_coupons_promotions_is_inactive(): void
    {
        $this->setPluginActive('coupons-promotions', false);

        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $product = Product::factory()->create(['base_price' => 50.0]);

        $placeOrderData = new PlaceOrderData(
            user: $user,
            branchId: $branch->id,
            addressId: null,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway',
            note: 'Boundary test',
            couponId: 999
        );

        $cartItems = [CartLines::make($product->id, 50.0, productName: (string) $product->name)];

        $totals = new OrderTotals(
            subTotal: 50.0,
            discount: 0.0,
            tax: 0.0,
            service: 0.0,
            deliveryFee: 0.0,
            total: 50.0
        );

        $orderRepository = $this->app->make(OrderRepository::class);
        $order = $orderRepository->createOrderWithItems($placeOrderData, $cartItems, $totals);

        $this->assertNull($order->coupon_id);
        $this->assertNull($order->coupon_code);
    }

    public function test_order_placed_with_valid_coupon_stores_coupon_id_and_coupon_code(): void
    {
        $this->setPluginActive('coupons-promotions', true);

        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 100.0,
        ]);

        $coupon = Coupon::factory()->create([
            'code' => 'PROMO10',
            'type' => 'fixed',
            'value' => 10.0,
            'is_active' => true,
            'expiry_date' => now()->addDays(5),
            'conditions' => [
                'min_order_amount' => 50.0,
            ],
        ]);

        $cartService = $this->app->make(CartServiceInterface::class);
        $cartService->addItem($user, $product->id, null, '1');

        $placeOrderData = new PlaceOrderData(
            user: $user,
            branchId: $branch->id,
            addressId: null,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway',
            couponId: $coupon->id
        );

        $placeOrderService = $this->app->make(PlaceOrderServiceInterface::class);
        $result = $placeOrderService->placeOrder($placeOrderData);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame($coupon->id, $result->order->coupon_id);
        $this->assertSame('PROMO10', $result->order->coupon_code);
    }

    public function test_completed_online_payment_pos_status_ready_when_pos_active_and_untouched_when_inactive(): void
    {
        // 1. When pos-integration is active
        $this->setPluginActive('pos-integration', true);
        $orderActive = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'pos_status' => OrderPosStatus::NOT_READY,
        ]);

        $stateMachine = $this->app->make(PaymentStateMachine::class);
        $stateMachine->transition($orderActive, PaymentStatus::COMPLETED);

        $this->assertSame(OrderPosStatus::READY, $orderActive->fresh()->pos_status);

        // 2. When pos-integration is inactive
        $this->setPluginActive('pos-integration', false);
        $orderInactive = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'pos_status' => OrderPosStatus::NOT_READY,
        ]);

        $stateMachine->transition($orderInactive, PaymentStatus::COMPLETED);

        $this->assertSame(OrderPosStatus::NOT_READY, $orderInactive->fresh()->pos_status);
    }

    public function test_cod_placement_pos_status_ready_when_pos_active_and_untouched_when_inactive(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.0,
        ]);

        $cartService = $this->app->make(CartServiceInterface::class);
        $placeOrderService = $this->app->make(PlaceOrderServiceInterface::class);

        // 1. When pos-integration is active
        $this->setPluginActive('pos-integration', true);
        $cartService->addItem($user, $product->id, null, '1');

        $dataActive = new PlaceOrderData(
            user: $user,
            branchId: $branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway'
        );

        $resultActive = $placeOrderService->placeOrder($dataActive);
        $this->assertTrue($resultActive->isSuccessful());
        $this->assertSame(OrderPosStatus::READY, $resultActive->order->fresh()->pos_status);

        // 2. When pos-integration is inactive
        $this->setPluginActive('pos-integration', false);
        $cartService->addItem($user, $product->id, null, '1');

        $dataInactive = new PlaceOrderData(
            user: $user,
            branchId: $branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway'
        );

        $resultInactive = $placeOrderService->placeOrder($dataInactive);
        $this->assertTrue($resultInactive->isSuccessful());
        $this->assertSame(OrderPosStatus::NOT_READY, $resultInactive->order->fresh()->pos_status);
    }

    public function test_orders_api_clamps_per_page_to_max_50(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(55)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders?per_page=1000');

        $response->assertOk();
        $this->assertSame(50, $response->json('meta.per_page'));
        $this->assertCount(50, $response->json('data'));
    }

    public function test_orders_api_clamps_per_page_below_1_to_1(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders?per_page=0');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.per_page'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_orders_api_defaults_per_page_to_10(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk();
        $this->assertSame(10, $response->json('meta.per_page'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_order_customer_relations_do_not_depend_on_guest_checkout(): void
    {
        $this->setPluginActive('guest-checkout', false);
        $this->assertSame(['user'], Order::customerRelations());

        $this->setPluginActive('guest-checkout', true);
        $this->assertSame(['user'], Order::customerRelations());
    }

    public function test_review_is_not_approved_by_default_and_hidden_from_public_list_until_auto_approve(): void
    {
        $settingService = $this->app->make(SettingService::class);
        $settingService->set(Review::SETTING_AUTO_APPROVE, false);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $submitResponse = $this->postJson(route('reviews.store', $product), [
            'guest_name' => 'Moderated Guest',
            'guest_email' => 'mod@example.com',
            'rating' => 5,
            'comment' => 'Review awaiting moderation',
        ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('message', 'Review submitted successfully and is awaiting approval.');

        // Public index must NOT show it
        $indexResponse = $this->getJson(route('reviews.index', $product));
        $indexResponse->assertOk()
            ->assertJsonCount(0, 'data');

        // With auto-approve enabled
        $settingService->set(Review::SETTING_AUTO_APPROVE, true);

        $autoResponse = $this->postJson(route('reviews.store', $product), [
            'guest_name' => 'Auto Approved Guest',
            'guest_email' => 'auto@example.com',
            'rating' => 5,
            'comment' => 'Review immediately visible',
        ]);

        $autoResponse->assertStatus(201)
            ->assertJsonPath('data.is_approved', true)
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('message', 'Review submitted successfully!');

        // Public index MUST show only the approved one
        $indexResponseAfter = $this->getJson(route('reviews.index', $product));
        $indexResponseAfter->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Review immediately visible');
    }

    public function test_catalog_products_show_api_does_not_return_reviews_array(): void
    {
        $this->setPluginActive('product-reviews', true);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson(route('api.catalog-products.show', $product->id));
        $response->assertOk()
            ->assertJsonMissing(['reviews'])
            ->assertJsonPath('data.reviews_count', 0);
    }

    public function test_catalog_products_when_product_reviews_inactive_defaults_reviews_count_to_zero(): void
    {
        $this->setPluginActive('product-reviews', false);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson(route('api.catalog-products.show', $product->id));
        $response->assertOk()
            ->assertJsonMissing(['reviews'])
            ->assertJsonPath('data.reviews_count', 0);
    }

    public function test_order_repository_requires_cart_service_and_address_book(): void
    {
        $cartService = $this->app->make(CartServiceInterface::class);
        $addressBook = $this->app->make(\App\Plugins\LocationsDelivery\src\Contracts\AddressBook::class);

        $repo = new OrderRepository($cartService, $addressBook);
        $this->assertInstanceOf(OrderRepository::class, $repo);
    }

    private function setPluginActive(string $pluginId, bool $active): void
    {
        $tenant = self::$testTenant;
        $manager = app(PluginManager::class);

        TenantPlugin::query()->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', $pluginId)
            ->update(['status' => $active ? PluginStatus::ACTIVE : PluginStatus::DEACTIVATED]);

        $manager->resetTenant($tenant);
        if ($active) {
            $manager->bootTenant($tenant);
        }
    }
}
