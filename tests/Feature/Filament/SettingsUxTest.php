<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Core\Plugins\Services\PluginManager;
use App\Enums\SettingKey;
use App\Filament\Pages\ManagePlugins;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use App\Plugins\PosIntegration\src\Filament\Pages\ImportPOSProducts;
use App\Plugins\PosIntegration\src\Filament\Pages\SyncPOSPrices;
use App\Plugins\PosIntegration\src\Settings\PosSettingKey;
use App\Plugins\StoreSettings\src\Filament\Pages\ManifestSettings;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use Tests\TestCase;

final class SettingsUxTest extends TestCase
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

    public function test_navigation_groups_labels_and_sorts_match_specification(): void
    {
        // Settings: group 'الإعدادات والنظام', label 'إعدادات المتجر', sort 1
        $this->assertSame('الإعدادات والنظام', Settings::getNavigationGroup());
        $this->assertSame('إعدادات المتجر', Settings::getNavigationLabel());
        $this->assertSame(1, Settings::getNavigationSort());

        // ManagePlugins: group 'الإعدادات والنظام', label 'سوق الإضافات', sort 2
        $this->assertSame('الإعدادات والنظام', ManagePlugins::getNavigationGroup());
        $this->assertSame('سوق الإضافات', ManagePlugins::getNavigationLabel());
        $this->assertSame(2, ManagePlugins::getNavigationSort());

        // ManifestSettings: group 'الإعدادات والنظام', label 'تطبيق الويب (PWA)', sort 3
        $this->assertSame('الإعدادات والنظام', ManifestSettings::getNavigationGroup());
        $this->assertSame('تطبيق الويب (PWA)', ManifestSettings::getNavigationLabel());
        $this->assertSame(3, ManifestSettings::getNavigationSort());

        // POS Import: group 'الكتالوج والمنتجات', label 'استيراد منتجات POS', sort 6
        $this->assertSame('الكتالوج والمنتجات', ImportPOSProducts::getNavigationGroup());
        $this->assertSame('استيراد منتجات POS', ImportPOSProducts::getNavigationLabel());
        $this->assertSame(6, ImportPOSProducts::getNavigationSort());

        // POS Price Sync: group 'الكتالوج والمنتجات', label 'مزامنة الأسعار', sort 7
        $this->assertSame('الكتالوج والمنتجات', SyncPOSPrices::getNavigationGroup());
        $this->assertSame('مزامنة الأسعار', SyncPOSPrices::getNavigationLabel());
        $this->assertSame(7, SyncPOSPrices::getNavigationSort());
    }

    public function test_settings_page_has_four_arabic_tabs(): void
    {
        $page = new Settings;
        $schema = $page->form(Schema::make($page));

        $components = $schema->getComponents();
        $this->assertNotEmpty($components);

        // Find Tabs component
        $formComponent = $components[0];
        $tabsComponent = null;

        foreach ($formComponent->getChildComponents() as $child) {
            if ($child instanceof Tabs) {
                $tabsComponent = $child;
                break;
            }
        }

        $this->assertNotNull($tabsComponent, 'Tabs component should be present in Settings form.');

        $tabs = $tabsComponent->getChildComponents();
        $tabLabels = array_map(function ($tab) {
            return $tab instanceof Tab ? $tab->getLabel() : null;
        }, $tabs);

        $expectedTabs = [
            'الهوية والمتجر',
            'المظهر والألوان',
            'الطلبات',
            'المالية وبوابات الدفع',
            'نظام نقاط البيع (POS)',
            'النظام وساعات العمل',
            'بطاقات الواجهة',
            'تسويق ميتا (Meta)',
        ];

        $this->assertSame($expectedTabs, array_values(array_filter($tabLabels)));
    }

    public function test_settings_page_contains_allow_manual_order_status_update_toggle(): void
    {
        $page = new Settings;
        $schema = $page->form(Schema::make($page));

        $components = $schema->getFlatFields(withHidden: true);

        $this->assertArrayHasKey(
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value,
            $components,
            'OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE toggle must exist in the schema.'
        );

        $field = $components[OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value];
        $this->assertSame('السماح بالتعديل اليدوي لحالة الطلبات من لوحة الإدارة', $field->getLabel());
    }

    public function test_all_existing_setting_keys_remain_in_form_schema(): void
    {
        $page = new Settings;
        $schema = $page->form(Schema::make($page));
        $fields = $schema->getFlatFields(withHidden: true);

        $requiredKeys = [
            SettingKey::SITE_NAME->value,
            SettingKey::SITE_NAME_AR->value,
            SettingKey::CONTACT_EMAIL->value,
            SettingKey::CONTACT_PHONE->value,
            SettingKey::CONTACT_ADDRESS->value,
            SettingKey::SITE_DESCRIPTION->value,
            SettingKey::SITE_LOGO->value,
            SettingKey::SITE_FAVICON->value,
            SettingKey::IMAGE_PLACEHOLDER->value,
            SettingKey::SVG_LOGO->value,
            SettingKey::SOCIAL_LINKS->value,
            SettingKey::CURRENCY->value,
            OrderSettingKey::TAX_RATE->value,
            PaymentSettingKey::COD_FEE->value,
            OrderSettingKey::MIN_ORDER_AMOUNT->value,
            PaymentSettingKey::ONLINE_PAYMENTS_ENABLED->value,
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value,
            PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY->value,
            PaymentSettingKey::PAYMOB_BASE_URL->value,
            PaymentSettingKey::PAYMOB_PUBLIC_KEY->value,
            PaymentSettingKey::PAYMOB_SECRET_KEY->value,
            PaymentSettingKey::PAYMOB_INTEGRATION_IDS->value,
            PaymentSettingKey::PAYMOB_HMAC_SECRET->value,
            PaymentSettingKey::PAYMOB_CURRENCY->value,
            PaymentSettingKey::PAYMOB_MODE->value,
            PaymentSettingKey::KASHIER_MERCHANT_ID->value,
            PaymentSettingKey::KASHIER_API_KEY->value,
            PaymentSettingKey::KASHIER_SECRET_KEY->value,
            PaymentSettingKey::KASHIER_MODE->value,
            PaymentSettingKey::KASHIER_CURRENCY->value,
            PaymentSettingKey::KASHIER_ALLOWED_METHODS->value,
            SettingKey::MAINTENANCE_MODE->value,
            PosSettingKey::PRODUCTS_REPO_LINK->value,
            SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value,
            SettingKey::WORK_TIMES->value,
            SettingKey::GOOGLE_CLIENT_ID->value,
            SettingKey::GOOGLE_CLIENT_SECRET->value,
            SettingKey::GOOGLE_REDIRECT_URL->value,
            SettingKey::MAIL_MAILER->value,
            SettingKey::MAIL_HOST->value,
            SettingKey::MAIL_PORT->value,
            SettingKey::MAIL_USERNAME->value,
            SettingKey::MAIL_PASSWORD->value,
            SettingKey::MAIL_ENCRYPTION->value,
            SettingKey::MAIL_FROM_ADDRESS->value,
            SettingKey::MAIL_FROM_NAME->value,
            SettingKey::PRODUCT_SHOW_CARDS->value,
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $fields, "SettingKey [{$key}] must be present in the form.");
        }
    }

    public function test_settings_livewire_component_mounts_and_saves_allow_manual_order_status_update(): void
    {
        $settingService = app(SettingService::class);
        $settingService->setMultiple([
            SettingKey::SITE_NAME->value => 'Restaurant',
            PaymentSettingKey::PAYMOB_BASE_URL->value => 'https://accept.paymob.com',
            PaymentSettingKey::PAYMOB_PUBLIC_KEY->value => 'pk_test_123',
            PaymentSettingKey::PAYMOB_SECRET_KEY->value => 'sk_test_123',
            PaymentSettingKey::PAYMOB_INTEGRATION_IDS->value => '12345',
            PaymentSettingKey::PAYMOB_HMAC_SECRET->value => 'hmac_123',
            PaymentSettingKey::PAYMOB_CURRENCY->value => 'EGP',
            PaymentSettingKey::PAYMOB_MODE->value => 'test',
            SettingKey::MAIL_MAILER->value => 'smtp',
            SettingKey::MAIL_HOST->value => 'smtp.example.com',
            SettingKey::MAIL_PORT->value => '587',
            SettingKey::MAIL_FROM_ADDRESS->value => 'test@example.com',
            SettingKey::MAIL_FROM_NAME->value => 'Restaurant',
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value => 'false',
        ]);

        $component = Livewire::test(Settings::class);
        $component->assertOk();

        // Check initial state
        $this->assertFalse($component->get('data.'.OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value));

        // Update toggle to true and save
        $component->set('data.'.OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value, true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('تم الحفظ بنجاح');

        $this->assertTrue($settingService->get(OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE));

        // Re-mount and verify filled state
        $reloaded = Livewire::test(Settings::class);
        $this->assertTrue($reloaded->get('data.'.OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value));
    }

    public function test_manifest_settings_page_mounts_and_contains_arabic_labels(): void
    {
        $page = new ManifestSettings;
        $schema = $page->form(Schema::make($page));
        $fields = $schema->getFlatFields();

        $this->assertArrayHasKey('name', $fields);
        $this->assertSame('الاسم الكامل', $fields['name']->getLabel());

        $this->assertArrayHasKey('short_name', $fields);
        $this->assertSame('الاسم المختصر', $fields['short_name']->getLabel());

        $this->assertArrayHasKey('id', $fields);
        $this->assertSame('معرف التطبيق', $fields['id']->getLabel());

        $this->assertArrayHasKey('theme_color', $fields);
        $this->assertSame('لون السمة (Theme Color)', $fields['theme_color']->getLabel());

        $this->assertArrayHasKey('background_color', $fields);
        $this->assertSame('لون الخلفية (Background Color)', $fields['background_color']->getLabel());

        $this->assertArrayHasKey('start_url', $fields);
        $this->assertSame('رابط البداية (Start URL)', $fields['start_url']->getLabel());

        $this->assertArrayHasKey('display', $fields);
        $this->assertSame('وضع العرض (Display Mode)', $fields['display']->getLabel());

        $this->assertArrayHasKey('orientation', $fields);
        $this->assertSame('اتجاه الشاشة (Orientation)', $fields['orientation']->getLabel());
    }

    public function test_settings_page_shows_active_tabs_only_and_hides_payments_tab_when_inactive(): void
    {
        $tenant = tenant();
        $this->assertNotNull($tenant);

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'payment-processing')
            ->delete();

        app(PluginManager::class)->resetTenant($tenant);

        $page = new Settings;
        $schema = $page->form(Schema::make($page));

        $components = $schema->getComponents();
        $formComponent = $components[0];
        $tabsComponent = null;

        foreach ($formComponent->getChildComponents() as $child) {
            if ($child instanceof Tabs) {
                $tabsComponent = $child;
                break;
            }
        }

        $this->assertNotNull($tabsComponent);
        $tabLabels = array_map(fn ($tab) => $tab instanceof Tab ? $tab->getLabel() : null, $tabsComponent->getChildComponents());

        $this->assertNotContains('المالية وبوابات الدفع', $tabLabels);
        $this->assertContains('الهوية والمتجر', $tabLabels);
    }

    public function test_saving_settings_round_trips_boolean_and_json_key(): void
    {
        $settingService = app(SettingService::class);
        $settingService->setMultiple([
            SettingKey::SITE_NAME->value => 'Restaurant',
            PaymentSettingKey::PAYMOB_BASE_URL->value => 'https://accept.paymob.com',
            PaymentSettingKey::PAYMOB_PUBLIC_KEY->value => 'pk_test_123',
            PaymentSettingKey::PAYMOB_SECRET_KEY->value => 'sk_test_123',
            PaymentSettingKey::PAYMOB_INTEGRATION_IDS->value => '12345',
            PaymentSettingKey::PAYMOB_HMAC_SECRET->value => 'hmac_123',
            PaymentSettingKey::PAYMOB_CURRENCY->value => 'EGP',
            PaymentSettingKey::PAYMOB_MODE->value => 'test',
            SettingKey::MAIL_MAILER->value => 'smtp',
            SettingKey::MAIL_HOST->value => 'smtp.example.com',
            SettingKey::MAIL_PORT->value => '587',
            SettingKey::MAIL_FROM_ADDRESS->value => 'test@example.com',
            SettingKey::MAIL_FROM_NAME->value => 'Restaurant',
        ]);

        $inputCards = [
            [
                'title' => 'Roundtrip Title',
                'description' => 'Roundtrip Desc',
                'icon' => ['card.png'],
            ],
        ];

        Livewire::test(Settings::class)
            ->set('data.'.SettingKey::MAINTENANCE_MODE->value, true)
            ->set('data.'.SettingKey::PRODUCT_SHOW_CARDS->value, $inputCards)
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('تم الحفظ بنجاح');

        // Check DB raw strings
        $this->assertDatabaseHas('settings', [
            'key' => SettingKey::MAINTENANCE_MODE->value,
            'value' => 'true',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => SettingKey::PRODUCT_SHOW_CARDS->value,
            'value' => json_encode([
                [
                    'title' => 'Roundtrip Title',
                    'description' => 'Roundtrip Desc',
                    'icon' => 'card.png',
                ],
            ]),
        ]);

        // Check typed retrieval from SettingService
        $this->assertTrue($settingService->get(SettingKey::MAINTENANCE_MODE));
        $this->assertSame([
            [
                'title' => 'Roundtrip Title',
                'description' => 'Roundtrip Desc',
                'icon' => 'card.png',
            ],
        ], $settingService->get(SettingKey::PRODUCT_SHOW_CARDS));
    }

    public function test_bootstrap_json_still_has_cod_fee_and_online_payments_enabled(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(PaymentSettingKey::COD_FEE, 25.50);
        $settingService->set(PaymentSettingKey::ONLINE_PAYMENTS_ENABLED, true);

        $response = $this->getJson('/api/tenant/bootstrap');

        $response->assertOk()
            ->assertJsonPath('settings.cod_fee', 25.50)
            ->assertJsonPath('settings.online_payments_enabled', true);
    }
}
