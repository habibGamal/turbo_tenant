<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Enums\SettingKey;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ManagePlugins;
use App\Http\Middleware\SetAdminLocale;
use App\Models\Tenant;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Platform;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Facades\Tenancy;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // ->login()
            ->spa()
            ->profile()
            // ->multiFactorAuthentication(
            //     AppAuthentication::make()
            //         ->recoverable(),
            // )
            // Branding comes from Store Settings; null falls back to Filament defaults (e.g. before tenancy starts).
            ->brandName(fn (): ?string => self::storeSetting(fn (SettingService $settings) => $settings->get(SettingKey::SITE_NAME_AR) ?: $settings->get(SettingKey::SITE_NAME)))
            ->brandLogo(fn (): ?string => self::storeSetting(fn (SettingService $settings) => self::fileUrl($settings->get(SettingKey::SITE_LOGO))))
            ->favicon(fn (): ?string => self::storeSetting(fn (SettingService $settings) => self::fileUrl($settings->get(SettingKey::SITE_FAVICON))))
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            //            ->topNavigation()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                'إدارة المبيعات',
                'الكتالوج والمنتجات',
                'العروض والخصومات',
                'التوصيل والمناطق',
                'العملاء والتقييمات',
                'المحتوى والتسويق',
                'الإعدادات والنظام',
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->resources([
                \App\Filament\Resources\Users\UserResource::class,
            ])
            ->pages([
                Dashboard::class,
                ManagePlugins::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            // ->tenant(Tenant::class)
            // ->tenantDomain('{tenant:id}.localhost')
            ->middleware([
                'universal',
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
            ], true)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetAdminLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'CTRL + K',
                Platform::Mac => '⌘ + K',
                default => null,
            });

        $registry = app(\App\Core\Plugins\Services\PluginRegistry::class);
        foreach ($registry->all() as $manifest) {
            if ($manifest->filamentPlugin && class_exists($manifest->filamentPlugin)) {
                $class = $manifest->filamentPlugin;
                if (is_subclass_of($class, \Filament\Contracts\Plugin::class)) {
                    $panel->plugin($class::make());
                }
            }
        }

        return $panel;
    }

    /**
     * Read a store setting safely: returns null outside a tenant or when settings can't be read.
     *
     * @param  callable(SettingService): mixed  $read
     */
    private static function storeSetting(callable $read): ?string
    {
        return rescue(function () use ($read): ?string {
            if (! Tenancy::isInitialized()) {
                return null;
            }

            $value = $read(app(SettingService::class));

            return filled($value) ? (string) $value : null;
        }, rescue: null, report: false);
    }

    private static function fileUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://') ? $path : Storage::url($path);
    }
}
