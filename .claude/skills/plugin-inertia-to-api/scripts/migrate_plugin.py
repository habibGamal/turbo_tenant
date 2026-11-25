#!/usr/bin/env python3
"""
Turbo Tenant: Plugin Inertia-to-API Migration Scaffolder
Scaffolds the REST API controller, headless React module, fallback UI, and theme override stubs.
"""

import sys
import os
import json
import re
from pathlib import Path

def to_studly(s: str) -> str:
    parts = re.split(r'[-_ ]+', s)
    return ''.join(p.capitalize() for p in parts if p)

def to_kebab(s: str) -> str:
    s = re.sub(r'([a-z0-9])([A-Z])', r'\1-\2', s)
    return s.lower().replace('_', '-')

def main():
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    if len(sys.argv) < 2:
        print("Usage: python migrate_plugin.py <PluginNameOrSlug>")
        sys.exit(1)

    raw_name = sys.argv[1].strip()
    studly_name = to_studly(raw_name)
    kebab_slug = to_kebab(raw_name)

    base_dir = Path(".").resolve()
    plugins_be_dir = base_dir / "app" / "Plugins" / studly_name
    
    # If not found by studly name, look for matching directory
    if not plugins_be_dir.exists():
        found = False
        for p in (base_dir / "app" / "Plugins").iterdir():
            if p.is_dir():
                p_name_lower = p.name.lower()
                p_slug = to_kebab(p.name)
                if (p_name_lower in (studly_name.lower(), studly_name.lower() + "s", studly_name.lower().rstrip("s"))
                    or p_slug in (kebab_slug, kebab_slug + "s", kebab_slug.rstrip("s"))):
                    plugins_be_dir = p
                    studly_name = p.name
                    kebab_slug = to_kebab(p.name)
                    found = True
                    break
        if not found:
            print(f"❌ Error: Backend plugin directory not found for '{raw_name}' in app/Plugins/")
            sys.exit(1)

    # Read manifest if present
    manifest_path = plugins_be_dir / "plugin.json"
    plugin_id = kebab_slug
    if manifest_path.exists():
        try:
            with open(manifest_path, "r", encoding="utf-8") as f:
                data = json.load(f)
                plugin_id = data.get("id", kebab_slug)
        except Exception:
            pass

    print(f"🚀 Preparing Inertia-to-API migration scaffold for:")
    print(f"   Studly Name: {studly_name}")
    print(f"   Plugin ID:   {plugin_id}")
    print(f"   Backend Dir: {plugins_be_dir}")

    # 1. Backend API Controller Stub
    api_dir = plugins_be_dir / "src" / "Http" / "Controllers" / "Api"
    api_dir.mkdir(parents=True, exist_ok=True)
    api_controller = api_dir / f"{studly_name}ApiController.php"

    if not api_controller.exists():
        controller_content = f"""<?php

declare(strict_types=1);

namespace App\\Plugins\\{studly_name}\\src\\Http\\Controllers\\Api;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;

final class {studly_name}ApiController extends Controller
{{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {{
        return response()->json([
            'status' => 'success',
            'data' => [],
        ]);
    }}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {{
        return response()->json([
            'status' => 'success',
            'message' => '{studly_name} record created successfully.',
        ], 201);
    }}
}}
"""
        with open(api_controller, "w", encoding="utf-8") as f:
            f.write(controller_content)
        print(f"   ✅ Created Backend Controller: {api_controller.relative_to(base_dir)}")
    else:
        print(f"   ℹ️ Backend Controller already exists: {api_controller.relative_to(base_dir)}")

    # 2. Frontend Domain Module (resources/js/plugins/{plugin_id})
    fe_plugin_dir = base_dir / "resources" / "js" / "plugins" / plugin_id
    fe_api_dir = fe_plugin_dir / "api"
    fe_comp_dir = fe_plugin_dir / "components"
    fe_types_dir = fe_plugin_dir / "types"

    fe_api_dir.mkdir(parents=True, exist_ok=True)
    fe_comp_dir.mkdir(parents=True, exist_ok=True)
    fe_types_dir.mkdir(parents=True, exist_ok=True)

    # 2.1 Types
    types_file = fe_types_dir / "index.ts"
    if not types_file.exists():
        types_content = f"""/**
 * TypeScript Interfaces for {studly_name} Plugin
 */

export interface {studly_name}Item {{
  id: number | string;
  name?: string;
  created_at?: string;
}}

export interface {studly_name}Response {{
  status: string;
  data: {studly_name}Item[];
}}
"""
        with open(types_file, "w", encoding="utf-8") as f:
            f.write(types_content)
        print(f"   ✅ Created Frontend Types: {types_file.relative_to(base_dir)}")

    # 2.2 Headless Hook Stub
    hook_file = fe_api_dir / f"use{studly_name}.ts"
    if not hook_file.exists():
        hook_content = f"""import {{ useQuery }} from '@tanstack/react-query';
import {{ apiClient }} from '@/core/api/client';
import {{ {studly_name}Response }} from '../types';

export function use{studly_name}(params?: Record<string, any>) {{
  return useQuery<{studly_name}Response>({{
    queryKey: ['{plugin_id}', params],
    queryFn: async () => {{
      const {{ data }} = await apiClient.get('/api/{plugin_id}', {{ params }});
      return data;
    }},
    staleTime: 1000 * 60 * 2,
  }});
}}
"""
        with open(hook_file, "w", encoding="utf-8") as f:
            f.write(hook_content)
        print(f"   ✅ Created Headless Query Hook: {hook_file.relative_to(base_dir)}")

    # 2.3 Default Fallback Component
    default_comp = fe_comp_dir / f"Default{studly_name}.tsx"
    if not default_comp.exists():
        comp_content = f"""import React from 'react';
import {{ use{studly_name} }} from '../api/use{studly_name}';

export default function Default{studly_name}(props: any) {{
  const {{ data, isLoading, error }} = use{studly_name}();

  if (isLoading) {{
    return <div className="py-4 text-center text-sm text-muted-foreground animate-pulse">Loading {studly_name}...</div>;
  }}

  if (error || !data) {{
    return null;
  }}

  return (
    <div className="w-full my-4 p-4 border rounded-lg bg-card text-card-foreground">
      <h3 className="font-semibold text-lg mb-2">{studly_name}</h3>
      <p className="text-sm text-muted-foreground">Default Fallback Presentation</p>
    </div>
  );
}}
"""
        with open(default_comp, "w", encoding="utf-8") as f:
            f.write(comp_content)
        print(f"   ✅ Created Default Fallback Component: {default_comp.relative_to(base_dir)}")

    # 2.4 Plugin index.ts (Slot registration)
    plugin_index = fe_plugin_dir / "index.ts"
    if not plugin_index.exists():
        index_content = f"""import {{ registerPluginDefault, SlotName }} from '@/core/slots/slot-registry';
import Default{studly_name} from './components/Default{studly_name}';

/**
 * Register default fallback component for {studly_name}
 */
export function register{studly_name}Plugin(slot: SlotName = 'home.sections.after') {{
  registerPluginDefault(slot, Default{studly_name});
}}

export * from './api/use{studly_name}';
export * from './types';
"""
        with open(plugin_index, "w", encoding="utf-8") as f:
            f.write(index_content)
        print(f"   ✅ Created Plugin Registration: {plugin_index.relative_to(base_dir)}")

    # 3. Theme Override Stub (resources/js/themes/default/plugins/{plugin_id})
    theme_plugin_dir = base_dir / "resources" / "js" / "themes" / "default" / "plugins" / plugin_id
    theme_plugin_dir.mkdir(parents=True, exist_ok=True)
    theme_index = theme_plugin_dir / "index.ts"
    theme_comp = theme_plugin_dir / f"Themed{studly_name}.tsx"

    if not theme_comp.exists():
        theme_comp_content = f"""import React from 'react';
import {{ use{studly_name} }} from '@/plugins/{plugin_id}';

export default function Themed{studly_name}(props: any) {{
  const {{ data, isLoading }} = use{studly_name}();

  if (isLoading || !data) return null;

  return (
    <div className="themed-widget my-6">
      <div className="container mx-auto">
        {{/* Themed Custom Presentation */}}
      </div>
    </div>
  );
}}
"""
        with open(theme_comp, "w", encoding="utf-8") as f:
            f.write(theme_comp_content)
        print(f"   ✅ Created Theme Override Component: {theme_comp.relative_to(base_dir)}")

    if not theme_index.exists():
        theme_index_content = f"""import {{ registerThemeOverride, SlotName }} from '@/core/slots/slot-registry';
import Themed{studly_name} from './Themed{studly_name}';

export function register{studly_name}ThemeOverride(slot: SlotName = 'home.sections.after') {{
  registerThemeOverride('default', slot, Themed{studly_name});
}}
"""
        with open(theme_index, "w", encoding="utf-8") as f:
            f.write(theme_index_content)
        print(f"   ✅ Created Theme Registration: {theme_index.relative_to(base_dir)}")

    print(f"\n🎉 Migration scaffold ready for '{studly_name}'!")
    print(f"👉 Next Steps:")
    print(f"   1. Implement endpoints in {api_controller.relative_to(base_dir)}")
    print(f"   2. Map routes in app/Plugins/{studly_name}/routes/tenant.php")
    print(f"   3. Adjust query logic in {hook_file.relative_to(base_dir)}")
    print(f"   4. Style theme component in {theme_comp.relative_to(base_dir)}")

if __name__ == "__main__":
    main()
