<?php

declare(strict_types=1);

namespace Tests\Feature\MetaMarketing\Diagnostics;

use App\Core\Contracts\SettingsRepository;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\MetaMarketing\src\Diagnostics\MetaIdConsistencyChecker;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Filament\Pages\MetaDiagnostics;
use App\Plugins\MetaMarketing\src\Jobs\SendMetaPurchaseEvent;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class MetaDiagnosticsPageTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_page_renders_for_an_admin_and_shows_missing_config_badges_with_nothing_configured(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, null);
        $settings->set(MetaSettingKey::CAPI_ENABLED, false);
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, null);
        $settings->set(MetaSettingKey::CAPI_TEST_EVENT_CODE, null);
        $settings->set(MetaSettingKey::CATALOG_ENABLED, false);
        $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, null);
        $settings->set(MetaSettingKey::DEBUG_MODE, false);

        app()->forgetInstance(MetaMarketingConfig::class);

        Livewire::test(MetaDiagnostics::class)
            ->assertOk()
            ->assertSee('تشخيص تكامل ميتا')
            ->assertSee('غير محدد')
            ->assertSee('معطّلة')
            ->assertSee('إعدادات ميتا');
    }

    public function test_token_check_button_disabled_without_token(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, null);
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, null);

        app()->forgetInstance(MetaMarketingConfig::class);

        Livewire::test(MetaDiagnostics::class)
            ->assertActionDisabled('verifyCredentials')
            ->assertActionDisabled('sendTestEvent');
    }

    public function test_token_check_success_with_http_fake(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, '987654321');
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('valid-meta-token-123'));

        app()->forgetInstance(MetaMarketingConfig::class);

        Http::fake([
            'https://graph.facebook.com/v26.0/987654321*' => Http::response([
                'id' => '987654321',
                'name' => 'Main Store Dataset',
            ], 200),
        ]);

        Livewire::test(MetaDiagnostics::class)
            ->assertActionEnabled('verifyCredentials')
            ->callAction('verifyCredentials')
            ->assertHasNoActionErrors()
            ->assertSee('Main Store Dataset')
            ->assertSee('987654321')
            ->assertNotified('الرمز صالح');
    }

    public function test_token_check_meta_error_with_http_fake(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, '987654321');
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('invalid-meta-token-456'));

        app()->forgetInstance(MetaMarketingConfig::class);

        Http::fake([
            'https://graph.facebook.com/v26.0/987654321*' => Http::response([
                'error' => [
                    'message' => 'The access token could not be decrypted',
                    'fbtrace_id' => 'trace_err_abc123',
                ],
            ], 400),
        ]);

        Livewire::test(MetaDiagnostics::class)
            ->callAction('verifyCredentials')
            ->assertSee('The access token could not be decrypted')
            ->assertSee('trace_err_abc123')
            ->assertNotified('فشل فحص الرمز');
    }

    public function test_send_test_event_posts_exactly_one_pageview_with_test_event_code_and_shows_events_received(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, '987654321');
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('valid-meta-token-123'));
        $settings->set(MetaSettingKey::CAPI_TEST_EVENT_CODE, 'TEST98765');

        app()->forgetInstance(MetaMarketingConfig::class);

        Http::fake([
            'https://graph.facebook.com/v26.0/987654321/events' => Http::response([
                'events_received' => 1,
                'fbtrace_id' => 'fb_trace_pageview_test',
            ], 200),
        ]);

        Livewire::test(MetaDiagnostics::class)
            ->callAction('sendTestEvent')
            ->assertHasNoActionErrors()
            ->assertSee('1')
            ->assertSee('fb_trace_pageview_test')
            ->assertSee('Events Manager')
            ->assertNotified('تم إرسال الحدث التجريبي');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://graph.facebook.com/v26.0/987654321/events'
                && $request['test_event_code'] === 'TEST98765'
                && is_array($request['data'])
                && count($request['data']) === 1
                && $request['data'][0]['event_name'] === 'PageView'
                && str_starts_with((string) $request['data'][0]['event_id'], 'diagnostics.');
        });
    }

    public function test_retry_action_dispatches_job_for_failed_purchase_row_with_original_event_time_and_is_not_visible_for_sent_rows(): void
    {
        Queue::fake();

        $originalEventTime = 1712345678;

        $failedRow = MetaCapiEvent::create([
            'event_name' => 'Purchase',
            'event_id' => 'purchase.888',
            'order_id' => 888,
            'status' => 'failed',
            'attempts' => 1,
            'http_status' => 500,
            'error' => 'Graph API internal error',
            'payload' => [
                'event_name' => 'Purchase',
                'event_time' => $originalEventTime,
            ],
        ]);

        $sentRow = MetaCapiEvent::create([
            'event_name' => 'Purchase',
            'event_id' => 'purchase.889',
            'order_id' => 889,
            'status' => 'sent',
            'attempts' => 1,
            'http_status' => 200,
            'payload' => [
                'event_name' => 'Purchase',
                'event_time' => $originalEventTime,
            ],
        ]);

        Livewire::test(MetaDiagnostics::class)
            ->assertTableActionVisible('retry', $failedRow)
            ->assertTableActionHidden('retry', $sentRow)
            ->callTableAction('retry', $failedRow)
            ->assertHasNoTableActionErrors()
            ->assertNotified('تمت جدولة إعادة الإرسال');

        Queue::assertPushed(SendMetaPurchaseEvent::class, function (SendMetaPurchaseEvent $job) use ($originalEventTime) {
            return $job->orderId === 888 && $job->eventTime === $originalEventTime;
        });
    }

    public function test_catalog_urls_appear_only_when_catalog_is_enabled_and_catalog_check_shows_issues_for_product_without_image(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::CATALOG_ENABLED, false);
        $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'testtoken40charsrandomfeedtoken12345');

        app()->forgetInstance(MetaMarketingConfig::class);

        Livewire::test(MetaDiagnostics::class)
            ->assertDontSee('/meta/catalog/testtoken40charsrandomfeedtoken12345.csv')
            ->assertSee('كتالوج المنتجات غير مفعل حالياً');

        $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
        app()->forgetInstance(MetaMarketingConfig::class);

        $csvUrl = route('meta.catalog.feed', ['token' => 'testtoken40charsrandomfeedtoken12345', 'format' => 'csv']);
        $xmlUrl = route('meta.catalog.feed', ['token' => 'testtoken40charsrandomfeedtoken12345', 'format' => 'xml']);
        $arUrl = route('meta.catalog.feed.ar', ['token' => 'testtoken40charsrandomfeedtoken12345']);

        Product::factory()->create([
            'name' => 'Product Without Image',
            'image' => '',
            'base_price' => 50,
            'is_active' => true,
        ]);

        Livewire::test(MetaDiagnostics::class)
            ->assertSee($csvUrl)
            ->assertSee($xmlUrl)
            ->assertSee($arUrl)
            ->callAction('runCatalogCheck')
            ->assertHasNoActionErrors()
            ->assertSee('image')
            ->assertSee('المنتج بدون صورة')
            ->assertNotified('اكتمل فحص الكتالوج');
    }

    public function test_id_consistency_service_reports_100_percent_when_all_order_items_exist_in_feed_and_lists_missing_id_otherwise(): void
    {
        $productWithImage = Product::factory()->create([
            'name' => 'Valid Product With Image',
            'image' => 'products/item1.jpg',
            'base_price' => 120.0,
            'is_active' => true,
        ]);

        $order1 = Order::factory()->create([
            'type' => OrderType::WEB_DELIVERY,
        ]);
        $order1->items()->create([
            'product_id' => $productWithImage->id,
            'product_name' => $productWithImage->name,
            'quantity' => 2,
            'unit_price' => 120.0,
            'total' => 240.0,
        ]);

        $checker = app(MetaIdConsistencyChecker::class);
        $report1 = $checker->check(50, [$order1]);

        $this->assertSame(100.0, $report1->matchPercentage);
        $this->assertEmpty($report1->missingIds);
        $this->assertTrue($report1->isPerfectMatch());

        $productWithoutImage = Product::factory()->create([
            'name' => 'Invalid Product No Image',
            'image' => '',
            'base_price' => 80.0,
            'is_active' => true,
        ]);

        $order2 = Order::factory()->create([
            'type' => OrderType::WEB_DELIVERY,
        ]);
        $order2->items()->create([
            'product_id' => $productWithoutImage->id,
            'product_name' => $productWithoutImage->name,
            'quantity' => 1,
            'unit_price' => 80.0,
            'total' => 80.0,
        ]);

        $report2 = $checker->check(50, [$order2]);

        $this->assertSame(0.0, $report2->matchPercentage);
        $this->assertContains((string) $productWithoutImage->id, $report2->missingIds);
        $this->assertFalse($report2->isPerfectMatch());
    }

    public function test_access_token_string_never_appears_in_rendered_html(): void
    {
        $secretToken = 'VERY_SECRET_META_ACCESS_TOKEN_DO_NOT_EXPOSE_999';

        $settings = app(SettingsRepository::class);
        $settings->set(MetaSettingKey::PIXEL_ID, '987654321');
        $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString($secretToken));

        app()->forgetInstance(MetaMarketingConfig::class);

        Livewire::test(MetaDiagnostics::class)
            ->assertOk()
            ->assertDontSee($secretToken);
    }
}
