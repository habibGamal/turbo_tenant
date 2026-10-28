# Phase 6: Inertia Removal & Final Verification

> **Focus:** Full decommissioning of Inertia.js from PHP and JavaScript codebases, middleware cleanup, test suite refactoring, and comprehensive system verification.  
> **Target Path:** `.plan/replace_inertia_with_api/06_phase6_inertia_removal_and_final_verification.md`  

---

## 1. Objectives & Overview

Phase 6 is the final cutover milestone. Once all storefront flows (Phases 1 through 5) are verified working on the REST API and React SPA shell:
- Inertia packages (`inertiajs/inertia-laravel` and `@inertiajs/react`) are uninstalled.
- Inertia middleware, root templates, and route helpers are purged.
- Existing PHP test assertions (`assertInertia`) are converted to clean REST assertions (`assertJson`, `assertOk`).
- The entire project passes production builds, type checking, and the Maestro checklist audit.

---

## 2. Decommissioning Tasks

### 2.1 Backend Inertia Removal
1. **Uninstall Composer Package:**
   ```bash
   composer remove inertiajs/inertia-laravel
   ```
2. **Remove Middleware:**
   - Delete `app/Http/Middleware/HandleInertiaRequests.php`.
   - In `bootstrap/app.php`, remove `HandleInertiaRequests::class` from the `$middleware->web()` group.
3. **Clean Route Files:**
   - Scan all `routes/tenant.php` and `app/Plugins/*/routes/tenant.php`.
   - Ensure zero controllers return `Inertia::render()`.
   - Ensure all fallback routes point cleanly to the SPA view:
     ```php
     Route::fallback(function () {
         return view('app');
     });
     ```

### 2.2 Frontend Inertia Removal
1. **Uninstall NPM Package:**
   ```bash
   npm uninstall @inertiajs/react
   ```
2. **Clean `resources/views/app.blade.php`:**
   - Remove `<title inertia>`, `@inertiaHead`, and `@inertia`.
   - The view becomes a pristine HTML shell mounting `<div id="app"></div>`:
     ```html
     <!DOCTYPE html>
     <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
     <head>
         <meta charset="utf-8">
         <meta name="viewport" content="width=device-width, initial-scale=1">
         <title>{{ $siteName }}</title>
         @if($favicon)
             <link rel="icon" type="image/x-icon" href="{{ '/storage/'.$favicon }}">
         @endif
         @viteReactRefresh
         @vite(['resources/js/app.tsx'])
         <link rel="stylesheet" href="/theme.css">
     </head>
     <body class="font-sans antialiased">
         <div id="app"></div>
     </body>
     </html>
     ```
3. **Clean `resources/js/app.tsx`:**
   - Remove `createInertiaApp` and `laravel-vite-plugin/inertia-helpers`.
   - Standard React 18 bootstrap:
     ```tsx
     import "../css/app.css";
     import "./bootstrap";
     import "./i18n";
     import { createRoot } from "react-dom/client";
     import { QueryClientProvider } from "@tanstack/react-query";
     import { queryClient } from "@/core/api/query-client";
     import { TenantProvider } from "@/core/context/TenantContext";
     import { AuthProvider } from "@/core/context/AuthContext";
     import { ThemeProvider } from "@/contexts/ThemeContext";
     import { AppRouter } from "@/core/router/AppRouter";
     import { registerAllPlugins } from "@/plugins";

     // Register active plugin slots
     registerAllPlugins();

     const root = createRoot(document.getElementById("app")!);
     root.render(
       <QueryClientProvider client={queryClient}>
         <TenantProvider>
           <AuthProvider>
             <AppRouter />
           </AuthProvider>
         </TenantProvider>
       </QueryClientProvider>
     );
     ```

---

## 3. Test Suite Migration

### 3.1 Refactor Test Assertions
Find all tests using `assertInertia`:
```bash
git grep "assertInertia" tests/
```
Convert from Inertia assertions to REST JSON assertions:
- **Before:**
  ```php
  $response->assertInertia(fn (Assert $page) => $page
      ->component('ProductShow')
      ->has('product.id')
  );
  ```
- **After:**
  ```php
  $response->assertOk()
      ->assertJsonStructure([
          'product' => ['id', 'name', 'price', 'variants'],
          'relatedProducts'
      ]);
  ```

---

## 4. Final Verification & Quality Gates

Execute the full verification sequence:

### 4.1 TypeScript & Build Verification
```bash
# 1. Type check
npx tsc --noEmit

# 2. Production asset build
npm run build
```
*Pass Criteria:* Zero TypeScript errors, clean bundle generated in `public/build/`.

### 4.2 Backend Test Suite
```bash
php artisan test
```
*Pass Criteria:* All unit, feature, and tenant tests pass with 100% green status.

### 4.3 Checklist Audit
```bash
python .agent/scripts/checklist.py .
```
*Pass Criteria:* Security, Lint, and Schema audit scripts pass with zero critical blockers.
