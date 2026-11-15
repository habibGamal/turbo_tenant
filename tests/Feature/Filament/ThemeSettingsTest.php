<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Core\Plugins\Services\PluginContributions;
use App\Enums\SettingKey;
use App\Models\User;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use App\Plugins\StoreSettings\src\Services\SettingService;
use App\Plugins\StoreSettings\src\Settings\StoreAppearanceSettingsSection;
use App\Plugins\StoreSettings\src\Theme\ThemeCssBuilder;
use App\Plugins\StoreSettings\src\Theme\ThemePresets;
use Livewire\Livewire;
use Tests\TestCase;

final class ThemeSettingsTest extends TestCase
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

    public function test_theme_presets_contains_six_presets_including_default_and_terracotta(): void
    {
        $presets = ThemePresets::all();
        $this->assertCount(6, $presets);

        $this->assertArrayHasKey('default', $presets);
        $this->assertSame('#171717', $presets['default']['primary']);
        $this->assertSame('lg', $presets['default']['radius']);

        $this->assertArrayHasKey('terracotta', $presets);
        $this->assertSame('#893c25', $presets['terracotta']['primary']);
    }

    public function test_builder_generates_expected_css_variables_for_valid_theme(): void
    {
        $builder = app(ThemeCssBuilder::class);

        $css = $builder->build([
            'preset' => 'terracotta',
            'primary' => '#893c25',
            'secondary' => '#fdf4f0',
            'radius' => 'lg',
        ]);

        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('--primary: #893c25;', $css);
        $this->assertStringContainsString('--primary-foreground: #ffffff;', $css);
        $this->assertStringContainsString('--ring: #893c25;', $css);
        $this->assertStringContainsString('--secondary: #fdf4f0;', $css);
        $this->assertStringContainsString('--secondary-foreground: #171717;', $css);
        $this->assertStringContainsString('--accent: #fdf4f0;', $css);
        $this->assertStringContainsString('--accent-foreground: #171717;', $css);
        $this->assertStringContainsString('--radius: 0.75rem;', $css);
    }

    public function test_builder_resolves_dark_foreground_for_light_background_and_vice_versa(): void
    {
        $builder = app(ThemeCssBuilder::class);

        // Light background -> dark foreground (#171717)
        $lightCss = $builder->build([
            'primary' => '#ffffff',
            'secondary' => '#facc15', // yellow
        ]);
        $this->assertStringContainsString('--primary: #ffffff;', $lightCss);
        $this->assertStringContainsString('--primary-foreground: #171717;', $lightCss);
        $this->assertStringContainsString('--secondary-foreground: #171717;', $lightCss);

        // Dark background -> light foreground (#ffffff)
        $darkCss = $builder->build([
            'primary' => '#000000',
            'secondary' => '#171717',
        ]);
        $this->assertStringContainsString('--primary: #000000;', $darkCss);
        $this->assertStringContainsString('--primary-foreground: #ffffff;', $darkCss);
        $this->assertStringContainsString('--secondary-foreground: #ffffff;', $darkCss);
    }

    public function test_builder_drops_invalid_colors_and_css_injections_and_unknown_radius(): void
    {
        $builder = app(ThemeCssBuilder::class);

        // CSS injection attempts and invalid color syntax
        $css = $builder->build([
            'primary' => '#fff;}</style><script>alert(1)</script>',
            'secondary' => 'invalid-red',
            'radius' => '100px; evil-radius',
        ]);

        $this->assertSame('', $css);

        // Unknown radius with valid colors: colors are kept, unknown radius is dropped
        $partialCss = $builder->build([
            'primary' => '#893c25',
            'secondary' => '#ffffff',
            'radius' => 'unknown_radius',
        ]);

        $this->assertStringContainsString('--primary: #893c25;', $partialCss);
        $this->assertStringNotContainsString('--radius', $partialCss);
    }

    public function test_builder_returns_empty_string_for_empty_input(): void
    {
        $builder = app(ThemeCssBuilder::class);

        $this->assertSame('', $builder->build([]));
        $this->assertSame('', $builder->build(['preset' => 'custom']));
        $this->assertSame('', $builder->build(['primary' => null, 'secondary' => null, 'radius' => null]));
    }

    public function test_store_appearance_settings_section_is_registered_and_tagged(): void
    {
        $sections = app(PluginContributions::class)->active('store-settings.sections');
        $appearanceSection = collect($sections)->first(
            fn ($section) => $section instanceof StoreAppearanceSettingsSection
        );

        $this->assertInstanceOf(StoreAppearanceSettingsSection::class, $appearanceSection);
        $this->assertSame(15, $appearanceSection->sort());
        $this->assertSame([SettingKey::THEME], $appearanceSection->keys());
        $this->assertSame('المظهر والألوان', $appearanceSection->tab()->getLabel());
    }

    public function test_saving_settings_page_persists_theme_json(): void
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

        $themePayload = [
            'preset' => 'terracotta',
            'primary' => '#893c25',
            'secondary' => '#fdf4f0',
            'radius' => 'lg',
        ];

        Livewire::test(Settings::class)
            ->set('data.theme', $themePayload)
            ->call('save')
            ->assertHasNoErrors();

        $savedTheme = $settingService->get(SettingKey::THEME);
        $this->assertIsArray($savedTheme);
        $this->assertSame('terracotta', $savedTheme['preset']);
        $this->assertSame('#893c25', $savedTheme['primary']);
        $this->assertSame('#fdf4f0', $savedTheme['secondary']);
        $this->assertSame('lg', $savedTheme['radius']);

        // Mounting Settings again reloads the saved theme into form state
        $reloaded = Livewire::test(Settings::class);
        $formData = $reloaded->get('data.theme');
        $this->assertSame($savedTheme, $formData);
    }

    public function test_selecting_a_preset_fills_colors_and_radius(): void
    {
        Livewire::test(Settings::class)
            ->set('data.theme.preset', 'ocean')
            ->assertSet('data.theme.primary', '#0284c7')
            ->assertSet('data.theme.secondary', '#f0f9ff')
            ->assertSet('data.theme.radius', 'md');
    }

    public function test_storefront_html_contains_tenant_theme_with_primary_color_when_theme_is_set(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::THEME, [
            'preset' => 'terracotta',
            'primary' => '#893c25',
            'secondary' => '#fdf4f0',
            'radius' => 'lg',
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('id="tenant-theme"', false);
        $response->assertSee('#893c25', false);
    }

    public function test_storefront_html_omits_tenant_theme_when_theme_setting_is_empty(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::THEME, []);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('id="tenant-theme"', false);
    }
}
