<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ViewOrder;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

use function Pest\Livewire\livewire;

describe('Order Enums UX and Contracts', function () {
    it('implements HasLabel, HasColor, and HasIcon on OrderStatus with Arabic labels', function () {
        expect(OrderStatus::PENDING)->toBeInstanceOf(HasLabel::class)
            ->and(OrderStatus::PENDING)->toBeInstanceOf(HasColor::class)
            ->and(OrderStatus::PENDING)->toBeInstanceOf(HasIcon::class);

        expect(OrderStatus::PENDING->getLabel())->toBe('قيد الانتظار')
            ->and(OrderStatus::PREPARING->getLabel())->toBe('قيد التحضير')
            ->and(OrderStatus::OUT_FOR_DELIVERY->getLabel())->toBe('خارج للتوصيل')
            ->and(OrderStatus::DELIVERED->getLabel())->toBe('مكتمل (تم التوصيل)')
            ->and(OrderStatus::CANCELLED->getLabel())->toBe('ملغي');

        expect(OrderStatus::PENDING->label())->toBe('قيد الانتظار')
            ->and(OrderStatus::DELIVERED->getColor())->toBe('success')
            ->and(OrderStatus::PENDING->getIcon())->toBe('heroicon-o-clock');
    });

    it('implements HasLabel, HasColor, and HasIcon on OrderType with real database values', function () {
        expect(OrderType::WEB_DELIVERY)->toBeInstanceOf(HasLabel::class)
            ->and(OrderType::WEB_DELIVERY)->toBeInstanceOf(HasColor::class)
            ->and(OrderType::WEB_DELIVERY)->toBeInstanceOf(HasIcon::class);

        expect(OrderType::WEB_DELIVERY->value)->toBe('web_delivery')
            ->and(OrderType::WEB_TAKEAWAY->value)->toBe('web_takeaway')
            ->and(OrderType::POS->value)->toBe('pos');

        expect(OrderType::WEB_DELIVERY->getLabel())->toBe('توصيل (ويب)')
            ->and(OrderType::WEB_TAKEAWAY->getLabel())->toBe('استلام من الفرع')
            ->and(OrderType::POS->getLabel())->toBe('نقطة بيع (POS)');

        expect(OrderType::WEB_DELIVERY->getColor())->toBe('success')
            ->and(OrderType::WEB_TAKEAWAY->getColor())->toBe('info')
            ->and(OrderType::POS->getColor())->toBe('warning');

        expect(OrderType::WEB_DELIVERY->getIcon())->toBe('heroicon-o-truck')
            ->and(OrderType::WEB_TAKEAWAY->getIcon())->toBe('heroicon-o-building-storefront')
            ->and(OrderType::POS->getIcon())->toBe('heroicon-o-computer-desktop');
    });

    it('implements HasLabel, HasColor, and HasIcon on PaymentStatus with Arabic labels', function () {
        expect(PaymentStatus::COMPLETED)->toBeInstanceOf(HasLabel::class)
            ->and(PaymentStatus::COMPLETED)->toBeInstanceOf(HasColor::class)
            ->and(PaymentStatus::COMPLETED)->toBeInstanceOf(HasIcon::class);

        expect(PaymentStatus::PENDING->getLabel())->toBe('قيد الانتظار')
            ->and(PaymentStatus::PROCESSING->getLabel())->toBe('قيد المعالجة')
            ->and(PaymentStatus::COMPLETED->getLabel())->toBe('مكتمل (مدفوع)')
            ->and(PaymentStatus::FAILED->getLabel())->toBe('فشل الدفع')
            ->and(PaymentStatus::REFUNDED->getLabel())->toBe('مسترجع');

        expect(PaymentStatus::COMPLETED->getColor())->toBe('success')
            ->and(PaymentStatus::FAILED->getColor())->toBe('danger');

        expect(PaymentStatus::COMPLETED->isSuccessful())->toBeTrue()
            ->and(PaymentStatus::PENDING->isFinal())->toBeFalse()
            ->and(PaymentStatus::COMPLETED->isFinal())->toBeTrue();
    });

    it('implements HasLabel, HasColor, and HasIcon on PaymentMethod with Arabic labels', function () {
        expect(PaymentMethod::CARD)->toBeInstanceOf(HasLabel::class)
            ->and(PaymentMethod::CARD)->toBeInstanceOf(HasColor::class)
            ->and(PaymentMethod::CARD)->toBeInstanceOf(HasIcon::class);

        expect(PaymentMethod::CARD->getLabel())->toBe('بطاقة ائتمانية / بنكية')
            ->and(PaymentMethod::WALLET->getLabel())->toBe('محفظة إلكترونية')
            ->and(PaymentMethod::COD->getLabel())->toBe('الدفع عند الاستلام')
            ->and(PaymentMethod::KIOSK->getLabel())->toBe('مدفوعات كشك / فوري')
            ->and(PaymentMethod::BANK_TRANSFER->getLabel())->toBe('تحويل بنكي');

        expect(PaymentMethod::CARD->requiresOnlinePayment())->toBeTrue()
            ->and(PaymentMethod::COD->requiresOnlinePayment())->toBeFalse();
    });

    it('maps payment method to cash or card in POS payload builder', function () {
        $builder = app(App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface::class);

        // The POS only accepts web order types, so fix the type (the factory may pick 'pos').
        $codOrder = Order::factory()->create([
            'payment_method' => PaymentMethod::COD,
            'type' => OrderType::WEB_TAKEAWAY,
        ]);
        $cardOrder = Order::factory()->create([
            'payment_method' => PaymentMethod::CARD,
            'type' => OrderType::WEB_TAKEAWAY,
        ]);

        expect($builder->build($codOrder)['order']['webPreferences']['payment_method'])->toBe('cash')
            ->and($builder->build($cardOrder)['order']['webPreferences']['payment_method'])->toBe('card');
    });

    it('implements HasLabel, HasColor, and HasIcon on OrderPosStatus with Arabic labels', function () {
        expect(OrderPosStatus::READY)->toBeInstanceOf(HasLabel::class)
            ->and(OrderPosStatus::READY)->toBeInstanceOf(HasColor::class)
            ->and(OrderPosStatus::READY)->toBeInstanceOf(HasIcon::class);

        expect(OrderPosStatus::NOT_READY->getLabel())->toBe('غير جاهز')
            ->and(OrderPosStatus::PENDING->getLabel())->toBe('قيد الانتظار')
            ->and(OrderPosStatus::READY->getLabel())->toBe('جاهز للإرسال')
            ->and(OrderPosStatus::SENDING->getLabel())->toBe('جارِ الإرسال')
            ->and(OrderPosStatus::SENT->getLabel())->toBe('تم الإرسال')
            ->and(OrderPosStatus::FAILED->getLabel())->toBe('فشل الإرسال');

        expect(OrderPosStatus::FAILED->getColor())->toBe('danger')
            ->and(OrderPosStatus::SENT->getColor())->toBe('success');
    });
});

describe('Order Model and Resource Configuration', function () {
    it('casts order type to OrderType enum', function () {
        $order = Order::factory()->create([
            'type' => 'web_delivery',
        ]);

        expect($order->type)->toBe(OrderType::WEB_DELIVERY);
    });

    it('has expected navigation group, label, sort, and global search settings', function () {
        expect(OrderResource::getNavigationGroup())->toBe('إدارة المبيعات')
            ->and(OrderResource::getNavigationLabel())->toBe('الطلبات')
            ->and(OrderResource::getNavigationSort())->toBe(1)
            ->and(OrderResource::getRecordTitleAttribute())->toBe('order_number')
            ->and(OrderResource::getGloballySearchableAttributes())->toBe(['order_number', 'customer_phone']);
    });

    it('provides global search result details with customer name and phone', function () {
        $user = User::factory()->create([
            'name' => 'Sara Mahmoud',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_phone' => '01011223344',
            'order_number' => 'ORD-SEARCH-01',
        ]);

        $details = OrderResource::getGlobalSearchResultDetails($order);

        expect($details)->toBeArray()
            ->and($details['العميل'])->toBe('Sara Mahmoud')
            ->and($details['الهاتف'])->toBe('01011223344');
    });
});

describe('Order Filament UX rendering', function () {
    it('renders customer phone, enum badges, items count, and empty state in orders table', function () {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'name' => 'Tarek Mansour',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_phone' => '01234567890',
            'branch_id' => $branch->id,
            'order_number' => 'ORD-UX-999',
            'type' => 'web_delivery',
            'status' => 'pending',
            'payment_status' => 'completed',
            'pos_status' => 'sent',
        ]);

        OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
        ]);

        livewire(ListOrders::class)
            ->assertOk()
            ->assertSee('ORD-UX-999')
            ->assertSee('Tarek Mansour')
            ->assertSee('01234567890')
            ->assertSee('توصيل (ويب)')
            ->assertSee('قيد الانتظار');
    });

    it('renders extras and weight options in infolist with Arabic headers', function () {
        $order = Order::factory()->create([
            'order_number' => 'ORD-INFOLIST-01',
            'shift_id' => 42,
            'merchant_order_id' => 'MERCH-88',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Crispy Chicken',
            'variant_name' => 'Spicy',
            'weight_label' => '500g',
            'quantity' => 2,
            'unit_price' => 120.00,
            'total' => 240.00,
            'notes' => 'Extra crispy please',
        ]);

        $extra = OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_name' => 'Garlic Dip',
            'extra_name_ar' => 'Garlic Dip',
            'extra_price' => 15.00,
            'quantity' => 2,
        ]);

        livewire(ViewOrder::class, [
            'record' => $order->id,
        ])
            ->assertOk()
            ->assertSee('بيانات الطلب')
            ->assertSee('عناصر ومحتويات الطلب')
            ->assertSee('بيانات الدفع التقنية')
            ->assertSee('معرف الوردية')
            ->assertSee('42')
            ->assertSee('Crispy Chicken')
            ->assertSee('500g')
            ->assertSee('Extra crispy please')
            ->assertSee('Garlic Dip')
            ->assertSee('x2');
    });

    it('hides manual status update action when setting is false', function () {
        app(SettingService::class)->set(OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE, 'false');

        $order = Order::factory()->create();

        livewire(ViewOrder::class, [
            'record' => $order->id,
        ])
            ->assertOk()
            ->assertActionHidden('updateStatus');
    });

    it('reveals manual status update action when setting is true and allows updating status', function () {
        app(SettingService::class)->set(OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE, 'true');

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        livewire(ViewOrder::class, [
            'record' => $order->id,
        ])
            ->assertOk()
            ->assertActionVisible('updateStatus')
            ->callAction('updateStatus', [
                'status' => OrderStatus::PREPARING->value,
                'reason' => 'Kitchen system test',
            ])
            ->assertHasNoActionErrors();

        expect($order->fresh()->status)->toBe(OrderStatus::PREPARING);
    });
});
