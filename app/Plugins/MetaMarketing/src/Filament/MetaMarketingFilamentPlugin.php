<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class MetaMarketingFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'meta-marketing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                // Register Filament Resources here
            ])
            ->pages([
                Pages\MetaDiagnostics::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
