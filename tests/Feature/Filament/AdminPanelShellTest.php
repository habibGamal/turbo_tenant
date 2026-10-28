<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Http\Middleware\SetAdminLocale;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AdminPanelShellTest extends TestCase
{
    public function test_admin_panel_has_profile_enabled(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasProfile());
    }

    public function test_admin_panel_has_spa_mode_enabled(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasSpaMode());
    }

    public function test_admin_panel_registers_exact_navigation_groups_in_order(): void
    {
        $panel = Filament::getPanel('admin');

        $expectedGroups = [
            'إدارة المبيعات',
            'الكتالوج والمنتجات',
            'العروض والخصومات',
            'التوصيل والمناطق',
            'العملاء والتقييمات',
            'المحتوى والتسويق',
            'الإعدادات والنظام',
        ];

        $groups = $panel->getNavigationGroups();

        $groupLabels = array_map(function ($group) {
            return is_string($group) ? $group : $group->getLabel();
        }, $groups);

        $this->assertSame($expectedGroups, array_values($groupLabels));
    }

    public function test_admin_panel_primary_color_is_blue(): void
    {
        $panel = Filament::getPanel('admin');
        $colors = $panel->getColors();

        $this->assertSame(Color::Blue, $colors['primary'] ?? null);
    }

    public function test_admin_panel_brand_name_safe_fallback(): void
    {
        $panel = Filament::getPanel('admin');

        // Without tenant initialized, getBrandName() safely returns app.name
        $brandName = $panel->getBrandName();
        $this->assertNotEmpty($brandName);
    }

    public function test_admin_panel_brand_logo_and_favicon_safe_fallbacks(): void
    {
        $panel = Filament::getPanel('admin');

        // Without tenant initialized or when empty, safely returns null without exception
        $brandLogo = $panel->getBrandLogo();
        $favicon = $panel->getFavicon();

        $this->assertNull($brandLogo);
        $this->assertNull($favicon);
    }

    public function test_admin_panel_middleware_includes_set_admin_locale(): void
    {
        $panel = Filament::getPanel('admin');
        $middleware = $panel->getMiddleware();

        $this->assertContains(SetAdminLocale::class, $middleware);
    }

    public function test_set_admin_locale_middleware_sets_arabic_locale(): void
    {
        app()->setLocale('en');
        $this->assertSame('en', app()->getLocale());

        $middleware = new SetAdminLocale;
        $request = Request::create('/admin');

        $middleware->handle($request, function ($req) {
            $this->assertSame('ar', app()->getLocale());

            return response('OK');
        });
    }
}
