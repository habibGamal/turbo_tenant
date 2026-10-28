<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderUpdateMail;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use App\Plugins\ProductReviews\src\Models\Review;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Livewire;
use Tests\TestCase;

final class PluginBoundariesTest extends TestCase
{
    public function test_with_guest_checkout_inactive_order_update_mail_can_be_built_and_rendered(): void
    {
        $this->setPluginActive('guest-checkout', false);

        $order = Order::factory()->create([
            'order_number' => 'ORD-BOUNDARY-01',
            'customer_name' => 'John Doe',
            'payment_method' => PaymentMethod::COD,
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $mail = new OrderUpdateMail($order);
        $rendered = $mail->render();

        $this->assertStringContainsString('ORD-BOUNDARY-01', $rendered);
        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertFalse($order->relationLoaded('guestUser'));
    }

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
        $result = $couponService->validateCoupon(
            $coupon,
            null,
            [],
            100.0,
            null,
            null,
            null,
            '01099998888'
        );

        $this->assertTrue($result['valid']);
    }

    public function test_with_guest_checkout_active_guest_names_still_appear_and_are_searchable(): void
    {
        $this->setPluginActive('guest-checkout', true);

        $guestUser = GuestUser::factory()->create([
            'name' => 'DistinctGuestName',
            'phone' => '01012344321',
        ]);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'customer_name' => null,
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

        $cartItems = [
            [
                'product' => $product->toArray(),
                'quantity' => 1,
                'price' => 50.0,
                'subtotal' => 50.0,
                'extras' => [],
            ],
        ];

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

    public function test_orders_api_clamps_per_page_to_max_50(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(55)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders?per_page=1000');

        $response->assertOk();
        $this->assertSame(50, $response->json('data.per_page'));
        $this->assertCount(50, $response->json('data.data'));
    }

    public function test_orders_api_clamps_per_page_below_1_to_1(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders?per_page=0');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.per_page'));
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_orders_api_defaults_per_page_to_10(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk();
        $this->assertSame(10, $response->json('data.per_page'));
        $this->assertCount(10, $response->json('data.data'));
    }

    public function test_order_customer_relations_returns_expected_relations_based_on_guest_checkout_status(): void
    {
        $this->setPluginActive('guest-checkout', false);
        $this->assertSame(['user'], Order::customerRelations());

        $this->setPluginActive('guest-checkout', true);
        $this->assertSame(['user', 'guestUser'], Order::customerRelations());
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
            ->assertJsonPath('review.is_approved', false)
            ->assertJsonPath('review.is_published', false)
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
            ->assertJsonPath('review.is_approved', true)
            ->assertJsonPath('review.is_published', true)
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

    public function test_order_repository_requires_cart_service_and_plugin_manager(): void
    {
        $cartService = $this->app->make(\App\Plugins\CartEngine\src\Services\CartService::class);
        $pluginManager = $this->app->make(PluginManager::class);

        $repo = new OrderRepository($cartService, $pluginManager);
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
    }
}
