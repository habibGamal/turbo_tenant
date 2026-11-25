---
name: plugin-inertia-to-api
description: Step-by-step automated migration engine to convert any Turbo Tenant plugin from legacy Inertia.js to decoupled REST API + Headless React + Multi-Theme Overrides architecture.
allowed-tools: Read, Write, Edit, Glob, Grep, RunCommand
---

# Plugin Inertia-to-API Migration Skill

> Specialized workflow for migrating any Turbo Tenant plugin from legacy Inertia.js tight-coupling to a **Decoupled REST API + Headless React (TanStack Query) + Theme Overrides** architecture.

---

## 1. Architectural Migration Target

When a plugin is migrated using this skill, its architecture transforms:

| Dimension | Legacy Inertia Architecture | New API & Headless Architecture |
| :--- | :--- | :--- |
| **Backend Controller** | Returns `Inertia::render()` or `back()->with(...)` | Dedicated API controller in `src/Http/Controllers/Api/` returning `JsonResponse`. |
| **Tenant Routing** | Web routes mixed with Inertia page rendering | Clean REST endpoints under `/api/...` in `routes/tenant.php`. |
| **Frontend Location** | Scattered in `resources/js/themes/default/pages/` | Centralized domain module in `resources/js/plugins/{plugin-slug}/`. |
| **Data Fetching** | Monolithic `usePage().props` or `Inertia::share()` | Headless TanStack Query v5 hooks (`use{Feature}()`). |
| **UI Presentation** | Hardcoded into theme pages or rigid components | **Default Fallback** in `resources/js/plugins/{slug}/components/Default{Component}.tsx` + **Theme Overrides** in `resources/js/themes/{theme}/plugins/{slug}/`. |
| **Slot Bus** | Rigid imports | Dynamic `<PluginSlot name="..." pluginId="..." />` with fallback hierarchy. |

---

## 2. Directory Structure After Migration

For a given plugin `{StudlyName}` (e.g. `ProductReviews` with slug `product-reviews`):

### 1. Backend: `app/Plugins/{StudlyName}/`
```
app/Plugins/{StudlyName}/
├── routes/
│   └── tenant.php                                     # Registers /api/tenant/{plugin-slug}/...
└── src/
    └── Http/
        └── Controllers/
            └── Api/
                └── {StudlyName}ApiController.php     # Pure JsonResponse API controller
```

### 2. Frontend Domain Module: `resources/js/plugins/{plugin-slug}/`
```
resources/js/plugins/{plugin-slug}/
├── api/
│   ├── use{Feature}.ts                                # TanStack Query query hook
│   └── use{Action}.ts                                 # TanStack Query mutation hook
├── components/
│   └── Default{Component}.tsx                         # Neutral, unstyled fallback UI
├── types/
│   └── index.ts                                       # TypeScript interfaces for entities & API responses
└── index.ts                                           # Calls registerPluginDefault('{slot}', DefaultComponent)
```

### 3. Optional Theme Presentation: `resources/js/themes/{themeName}/plugins/{plugin-slug}/`
```
resources/js/themes/{themeName}/plugins/{plugin-slug}/
├── Themed{Component}.tsx                              # Customized UI matching the theme design system
└── index.ts                                           # Calls registerThemeOverride('{theme}', '{slot}', ThemedComponent)
```

---

## 3. Step-by-Step Migration Execution Protocol

When instructed to migrate a plugin `{PluginName}`:

### Step 1: Discover & Audit
1. Locate the plugin in `app/Plugins/{StudlyName}/`.
2. Inspect `plugin.json` to extract `id` (slug) and dependencies.
3. Locate all controllers returning `Inertia::render()` or using `redirect()`/`back()`.
4. Locate any frontend components or hooks coupled to `@inertiajs/react`.

### Step 2: Scaffold Structure via Python Utility
Run the automated scaffolding script:
```bash
python .claude/skills/plugin-inertia-to-api/scripts/migrate_plugin.py {StudlyName}
```
This automatically scaffolds:
- `app/Plugins/{StudlyName}/src/Http/Controllers/Api/{StudlyName}ApiController.php` (if not already present).
- `resources/js/plugins/{plugin-slug}/api/`
- `resources/js/plugins/{plugin-slug}/components/`
- `resources/js/plugins/{plugin-slug}/types/index.ts`
- `resources/js/plugins/{plugin-slug}/index.ts`
- `resources/js/themes/default/plugins/{plugin-slug}/`

### Step 3: Implement Backend REST Endpoints
1. In `app/Plugins/{StudlyName}/src/Http/Controllers/Api/{StudlyName}ApiController.php`:
   - All actions must return `Illuminate\Http\JsonResponse`.
   - Never import `Inertia\Inertia` or `Inertia\Response`.
   - Use standard HTTP status codes: `200 OK`, `201 Created`, `422 Unprocessable Entity`, `403 Forbidden`.
2. In `app/Plugins/{StudlyName}/routes/tenant.php`:
   - Add routes under the tenant middleware group:
   ```php
   Route::middleware([
       'web',
       InitializeTenancyByDomain::class,
       PreventAccessFromCentralDomains::class,
   ])->prefix('api')->group(function () {
       Route::get('/{plugin-slug}', [{StudlyName}ApiController::class, 'index']);
       Route::post('/{plugin-slug}', [{StudlyName}ApiController::class, 'store']);
   });
   ```

### Step 4: Implement Headless TanStack Query Hooks
In `resources/js/plugins/{plugin-slug}/api/`:
- Use the shared Axios client `@/core/api/client`.
- Define query hook using `useQuery`:
  ```typescript
  import { useQuery } from '@tanstack/react-query';
  import { apiClient } from '@/core/api/client';
  import { {DataType} } from '../types';

  export function use{Feature}(params?: Record<string, any>) {
    return useQuery<{DataType}>({
      queryKey: ['{plugin-slug}', params],
      queryFn: async () => {
        const { data } = await apiClient.get('/api/{plugin-slug}', { params });
        return data;
      },
      staleTime: 1000 * 60 * 2,
    });
  }
  ```
- Define mutation hook using `useMutation` with query invalidation:
  ```typescript
  import { useMutation, useQueryClient } from '@tanstack/react-query';
  import { apiClient } from '@/core/api/client';

  export function useSave{Feature}() {
    const queryClient = useQueryClient();
    return useMutation({
      mutationFn: async (payload: any) => {
        const { data } = await apiClient.post('/api/{plugin-slug}', payload);
        return data;
      },
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['{plugin-slug}'] });
      },
    });
  }
  ```

### Step 5: Implement Default Fallback & Slot Registration
1. In `resources/js/plugins/{plugin-slug}/components/Default{Component}.tsx`:
   - Implement clean, neutral component using the headless hook.
2. In `resources/js/plugins/{plugin-slug}/index.ts`:
   - Register the component as plugin default:
   ```typescript
   import { registerPluginDefault } from '@/core/slots/slot-registry';
   import Default{Component} from './components/Default{Component}';

   export function register{StudlyName}Plugin() {
     registerPluginDefault('{slot.name}', Default{Component});
   }
   ```

### Step 6: Implement Theme Override (if needed)
If the `default` theme has custom styling for this plugin:
1. In `resources/js/themes/default/plugins/{plugin-slug}/Themed{Component}.tsx`:
   - Consume the headless hook from `@/plugins/{plugin-slug}/api/use{Feature}`.
   - Render theme-tailored markup.
2. In `resources/js/themes/default/plugins/{plugin-slug}/index.ts`:
   ```typescript
   import { registerThemeOverride } from '@/core/slots/slot-registry';
   import Themed{Component} from './Themed{Component}';

   export function register{StudlyName}ThemeOverride() {
     registerThemeOverride('default', '{slot.name}', Themed{Component});
   }
   ```

### Step 7: Purge Legacy Inertia Calls
- In theme pages (e.g. `ProductShow.tsx`, `HomePage.tsx`), replace direct imports of the plugin with:
  ```tsx
  <PluginSlot name="{slot.name}" pluginId="{plugin-slug}" context={{ ... }} />
  ```
- Remove all `@inertiajs/react` hooks (`usePage().props`, `router.visit`, etc.) from the plugin.

### Step 8: Verify & Audit
1. Run backend tests:
   ```bash
   php artisan test --filter={StudlyName}
   ```
2. Run TypeScript check:
   ```bash
   npx tsc --noEmit
   ```
3. Verify JSON responses via curl or browser.
