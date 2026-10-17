<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Tenant;
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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // ->login()
            // ->spa()
            // ->profile()
            // ->multiFactorAuthentication(
            //     AppAuthentication::make()
            //         ->recoverable(),
            // )
            ->sidebarCollapsibleOnDesktop()
            //            ->topNavigation()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->resources([
                \App\Filament\Resources\Users\UserResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'CTRL + K',
                Platform::Mac => '⌘ + K',
                default => null,
            });

        $panel->bootUsing(function (Panel $panel) {
            if (function_exists('tenant') && tenant()) {
                $activeFilamentPlugins = app(\App\Core\Plugins\Services\PluginManager::class)
                    ->getActiveFilamentPluginsForTenant(tenant());
                foreach ($activeFilamentPlugins as $filamentPlugin) {
                    if (! $panel->hasPlugin($filamentPlugin->getId())) {
                        $panel->plugin($filamentPlugin);
                    }
                }
            }
        });

        if (function_exists('tenant') && tenant()) {
            $activeFilamentPlugins = app(\App\Core\Plugins\Services\PluginManager::class)
                ->getActiveFilamentPluginsForTenant(tenant());
            foreach ($activeFilamentPlugins as $filamentPlugin) {
                if (! $panel->hasPlugin($filamentPlugin->getId())) {
                    $panel->plugin($filamentPlugin);
                }
            }
        }

        return $panel;
    }
}
