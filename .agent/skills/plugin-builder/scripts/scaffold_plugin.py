#!/usr/bin/env python3
"""
Turbo Tenant - Plugin Scaffolder
Automates the generation of compliant plugin structures for the Microkernel SaaS Architecture.
"""

import os
import sys
import json
import argparse
from datetime import datetime

def to_studly_case(text: str) -> str:
    parts = text.replace('_', '-').split('-')
    return ''.join(p.capitalize() for p in parts if p)

def to_snake_case(text: str) -> str:
    return text.replace('-', '_').lower()

def main():
    parser = argparse.ArgumentParser(description="Scaffold a new Turbo Tenant plugin.")
    parser.add_argument("slug", help="Kebab-case plugin identifier (e.g. pos-integration)")
    parser.add_argument("--name", help="Display name of the plugin")
    parser.add_argument("--category", default="addon", choices=["foundation", "commerce", "marketing", "communication", "storefront", "addon"], help="Plugin category")
    parser.add_argument("--description", default="Modular plugin for Turbo Tenant", help="Description")
    parser.add_argument("--author", default="Turbo Tenant Team", help="Author")
    parser.add_argument("--deps", default="", help="Comma-separated dependency plugin IDs")
    parser.add_argument("--is-core", action="store_true", help="Mark as core foundation plugin")
    parser.add_argument("--with-filament", action="store_true", help="Scaffold Filament plugin class")
    parser.add_argument("--with-migrations", action="store_true", help="Scaffold tenant migrations directory")
    parser.add_argument("--with-frontend", action="store_true", help="Scaffold React frontend slot component")
    parser.add_argument("--base-dir", default=os.getcwd(), help="Root directory of the project")

    args = parser.parse_args()

    slug = args.slug.strip().lower()
    studly = to_studly_case(slug)
    snake = to_snake_case(slug)
    display_name = args.name or studly
    deps = [d.strip() for d in args.deps.split(",") if d.strip()]

    target_dir = os.path.join(args.base_dir, "app", "Plugins", studly)

    if os.path.exists(target_dir):
        print(f"❌ Error: Plugin directory already exists at {target_dir}")
        sys.exit(1)

    os.makedirs(target_dir, exist_ok=True)
    os.makedirs(os.path.join(target_dir, "src", "DTOs"), exist_ok=True)
    os.makedirs(os.path.join(target_dir, "src", "Services"), exist_ok=True)
    os.makedirs(os.path.join(target_dir, "src", "Models"), exist_ok=True)

    filament_class = f"App\\Plugins\\{studly}\\src\\Filament\\{studly}FilamentPlugin" if args.with_filament else None

    # 1. plugin.json
    manifest_data = {
        "id": slug,
        "name": display_name,
        "version": "1.0.0",
        "category": args.category,
        "description": args.description,
        "author": args.author,
        "is_core": args.is_core,
        "dependencies": deps,
        "providers": [
            f"App\\Plugins\\{studly}\\{studly}ServiceProvider"
        ]
    }
    if filament_class:
        manifest_data["filament"] = filament_class

    manifest_path = os.path.join(target_dir, "plugin.json")
    with open(manifest_path, "w", encoding="utf-8") as f:
        json.dump(manifest_data, f, indent=2)

    # 2. Config DTO (Pattern 1)
    dto_content = f"""<?php

declare(strict_types=1);

namespace App\\Plugins\\{studly}\\src\\DTOs;

final readonly class {studly}Config
{{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {{}}
}}
"""
    with open(os.path.join(target_dir, "src", "DTOs", f"{studly}Config.php"), "w", encoding="utf-8") as f:
        f.write(dto_content)

    # 3. ServiceProvider (Composition Root)
    sp_content = f"""<?php

declare(strict_types=1);

namespace App\\Plugins\\{studly};

use App\\Plugins\\{studly}\\src\\DTOs\\{studly}Config;
use App\\Plugins\\StoreSettings\\src\\Services\\SettingService;
use Illuminate\\Support\\ServiceProvider;

final class {studly}ServiceProvider extends ServiceProvider
{{
    public function register(): void
    {{
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton({studly}Config::class, function ($app) {{
            $settings = $app->make(SettingService::class);

            return new {studly}Config(
                enabled: (bool) $settings->get('{slug}.enabled', true)
            );
        }});

        // Bind domain services
    }}

    public function boot(): void
    {{
        // Register global routes or dynamic relationships (resolveRelationUsing)
        // ❌ NEVER register pipeline pipes here! Register pipes in {studly}Plugin::bootForTenant()
    }}
}}
"""
    with open(os.path.join(target_dir, f"{studly}ServiceProvider.php"), "w", encoding="utf-8") as f:
        f.write(sp_content)

    # 3. TenantPlugin class
    tp_content = f"""<?php

declare(strict_types=1);

namespace App\\Plugins\\{studly};

use App\\Core\\Plugins\\AbstractTenantPlugin;
use App\\Models\\Tenant;

final class {studly}Plugin extends AbstractTenantPlugin
{{
    public function bootForTenant(Tenant $tenant): void
    {{
        // Runtime tenant initialization:
        // Register pipeline pipes (OrderPipelineRegistry), tenant middleware, or tenant hooks here
    }}

    public function onActivate(Tenant $tenant): void
    {{
        // Post-activation setup (seeders, default configs)
    }}

    public function onDeactivate(Tenant $tenant): void
    {{
        // Deactivation cleanup
    }}

    public function onUninstall(Tenant $tenant): void
    {{
        // Pre-purge cleanup
    }}
}}
"""
    with open(os.path.join(target_dir, f"{studly}Plugin.php"), "w", encoding="utf-8") as f:
        f.write(tp_content)

    # 4. Filament Plugin (if requested)
    if args.with_filament:
        fil_dir = os.path.join(target_dir, "src", "Filament")
        os.makedirs(fil_dir, exist_ok=True)
        os.makedirs(os.path.join(fil_dir, "Resources"), exist_ok=True)
        os.makedirs(os.path.join(fil_dir, "Pages"), exist_ok=True)

        fil_content = f"""<?php

declare(strict_types=1);

namespace App\\Plugins\\{studly}\\src\\Filament;

use Filament\\Contracts\\Plugin;
use Filament\\Panel;

final class {studly}FilamentPlugin implements Plugin
{{
    public static function make(): static
    {{
        return app(static::class);
    }}

    public function getId(): string
    {{
        return '{slug}';
    }}

    public function register(Panel $panel): void
    {{
        $panel
            ->resources([
                // Register Filament Resources here
            ])
            ->pages([
                // Register Filament Pages here
            ]);
    }}

    public function boot(Panel $panel): void
    {{
        //
    }}
}}
"""
        with open(os.path.join(fil_dir, f"{studly}FilamentPlugin.php"), "w", encoding="utf-8") as f:
            f.write(fil_content)

    # 5. Migrations (if requested)
    if args.with_migrations:
        mig_dir = os.path.join(target_dir, "database", "migrations", "tenant")
        os.makedirs(mig_dir, exist_ok=True)
        timestamp = datetime.now().strftime("%Y_%m_%d_%H%M%S")
        mig_file = f"{timestamp}_create_{snake}_table.php"
        table_name = snake

        mig_content = f"""<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{{
    public function up(): void
    {{
        if (Schema::hasTable('{table_name}')) {{
            return;
        }}

        Schema::create('{table_name}', function (Blueprint $table) {{
            $table->id();
            $table->timestamps();
        }});
    }}

    public function down(): void
    {{
        Schema::dropIfExists('{table_name}');
    }}
}};
"""
        with open(os.path.join(mig_dir, mig_file), "w", encoding="utf-8") as f:
            f.write(mig_content)

    # 6. Frontend Slot (if requested)
    if args.with_frontend:
        fe_dir = os.path.join(target_dir, "resources", "js")
        os.makedirs(fe_dir, exist_ok=True)
        widget_file = f"{studly}Widget.tsx"

        fe_content = f"""import React from 'react';

interface Props {{
    data?: Record<string, unknown>;
}}

export const {studly}Widget: React.FC<Props> = () => {{
    return (
        <div className="plugin-{slug}-container p-4 border rounded-lg bg-card">
            <h3 className="font-semibold text-lg">{display_name}</h3>
        </div>
    );
}};

export default {studly}Widget;
"""
        with open(os.path.join(fe_dir, widget_file), "w", encoding="utf-8") as f:
            f.write(fe_content)

    print(f"[OK] Successfully scaffolded plugin '{slug}' at:")
    print(f"   [DIR]  {target_dir}")
    print(f"   [FILE] plugin.json")
    print(f"   [FILE] {studly}ServiceProvider.php")
    print(f"   [FILE] {studly}Plugin.php")
    if args.with_filament:
        print(f"   [FILE] src/Filament/{studly}FilamentPlugin.php")
    if args.with_migrations:
        print(f"   [DIR]  database/migrations/tenant/")
    if args.with_frontend:
        print(f"   [FILE] resources/js/{studly}Widget.tsx")
    print("\nNext steps:")
    print(f"  1. Implement your domain models & logic in app/Plugins/{studly}/src/")
    print(f"  2. Run 'php artisan plugin:discover' to register into central database.")

if __name__ == "__main__":
    main()
