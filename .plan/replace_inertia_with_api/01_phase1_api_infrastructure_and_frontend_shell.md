# Phase 1: API Infrastructure & Frontend Shell

> **Focus:** Laying the foundational API gateway, Laravel Sanctum stateful authentication, React Router SPA shell, TanStack Query v5 client, and the **Theme-Aware `<PluginSlot />` engine**.  
> **Target Path:** `.plan/replace_inertia_with_api/01_phase1_api_infrastructure_and_frontend_shell.md`  

---

## 1. Objectives & Overview

Phase 1 establishes the core infrastructure that replaces Inertia's server-driven page-rendering mechanism with a modern, decoupled Single Page Application (SPA) architecture while maintaining full compatibility with Stancl Tenancy and **Multiple Storefront Themes**.

At the end of Phase 1:
- Laravel Sanctum provides secure, stateful multi-tenant cookie authentication and CSRF protection.
- A centralized `/api/tenant/bootstrap` endpoint serves tenant metadata, active theme name (`default`, `modern`, etc.), auth status, and active plugin entitlements.
- A standard SPA Blade shell (`resources/views/app.blade.php`) mounts the React application with zero Inertia dependencies.
- React Router v6/v7 routes all storefront pages on the client based on the active theme.
- TanStack Query v5 handles server state, caching, and mutation invalidation.
- The **Theme-Aware `<PluginSlot />` engine** is ready to resolve theme overrides with fallback to default plugin components.

---

## 2. Backend Implementation Tasks

### 2.1 Configure Laravel Sanctum for Stateful Tenancy
- Ensure `config/sanctum.php` stateful domains include tenant wildcards (e.g. `*.localhost`, `*.test`, or root domains from `config/tenancy.php`).
- Verify CSRF cookie initialization route (`/sanctum/csrf-cookie`) is accessible within tenant subdomains.

### 2.2 Create the Tenant Bootstrap API Endpoint
- **Route:** `GET /api/tenant/bootstrap`
- **Controller:** `App\Http\Controllers\Api\BootstrapController`
- **Payload Contract:**
  ```json
  {
    "tenant": {
      "id": "tenant1",
      "name": "My Store",
      "theme": "default"
    },
    "user": {
      "id": 1,
      "name": "Customer Name",
      "email": "customer@example.com"
    },
    "settings": {
      "site_name": "Store Name",
      "site_description": "Store Description",
      "site_logo": "/storage/logo.png",
      "currency": "EGP",
      "facebook_app_id": "123456",
      "online_payments_enabled": true
    },
    "active_plugins": [
      "catalog-products",
      "cart-engine",
      "order-management",
      "product-reviews",
      "hero-banners"
    ],
    "cart_count": 3
  }
  ```
- **Architectural Value:** Replaces `HandleInertiaRequests::share()` with an explicit, cacheable REST endpoint queried once at initial app mount.

### 2.3 Refactor Tenant Auth Routes to REST JSON
Create `App\Http\Controllers\Api\AuthController` responding strictly in JSON:
- `POST /api/tenant/auth/login` (Returns `{ user, message }` with 200 on success, or 422 with validation errors)
- `POST /api/tenant/auth/register` (Registers customer, logs in statefully)
- `POST /api/tenant/auth/logout` (Invalidates session and regenerates CSRF token)
- `GET /api/tenant/auth/user` (Current authenticated user profile)

---

## 3. Frontend Implementation Tasks

### 3.1 Install Frontend Dependencies
```bash
npm install react-router-dom@^6.28.0 @tanstack/react-query@^5.62.0
```

### 3.2 Implement Core API Client (`resources/js/core/api/client.ts`)
- Axios instance with `withCredentials: true`, `X-Requested-With: XMLHttpRequest`, and automatic CSRF handling (`XSRF-TOKEN`).
- Request/Response interceptors to gracefully catch `401 Unauthorized` and `419 CSRF Token Mismatch`.

### 3.3 Configure TanStack Query (`resources/js/core/api/query-client.ts`)
- Pre-configured `QueryClient` with default stale times (e.g., 2 minutes for products, 0 minutes for cart/checkout).
- Global mutation error handlers.

### 3.4 Build the Theme-Aware `<PluginSlot />` Engine (`resources/js/core/slots/`)

To support multiple themes seamlessly, the slot registry supports two registration levels:
1. **Plugin Default:** The generic/fallback component shipped with the plugin.
2. **Theme Override:** An optional, highly customized component registered by a specific theme.

#### `slot-registry.ts`
```typescript
import React from 'react';

export type SlotName = 
  | 'home.hero'
  | 'home.sections.after'
  | 'product.details.after'
  | 'product.tabs'
  | 'product.upsell'
  | 'cart.summary.coupon'
  | 'checkout.payment.methods';

type SlotComponent = React.ComponentType<any>;

// 1. Defaults registered by plugins
const pluginDefaultRegistry: Partial<Record<SlotName, SlotComponent>> = {};

// 2. Overrides registered by themes: themeName -> slotName -> Component
const themeOverrideRegistry: Record<string, Partial<Record<SlotName, SlotComponent>>> = {};

/**
 * Register default fallback component for a slot (called by plugin module)
 */
export function registerPluginDefault(slot: SlotName, component: SlotComponent): void {
  pluginDefaultRegistry[slot] = component;
}

/**
 * Register theme-specific override for a slot (called by active theme module)
 */
export function registerThemeOverride(themeName: string, slot: SlotName, component: SlotComponent): void {
  if (!themeOverrideRegistry[themeName]) {
    themeOverrideRegistry[themeName] = {};
  }
  themeOverrideRegistry[themeName][slot] = component;
}

/**
 * Resolve slot component with Theme Override > Plugin Default hierarchy
 */
export function resolveSlotComponent(slot: SlotName, currentTheme: string): SlotComponent | null {
  // 1. Check if the active theme registered an override
  const themeComponent = themeOverrideRegistry[currentTheme]?.[slot];
  if (themeComponent) {
    return themeComponent;
  }

  // 2. Fall back to the default plugin component
  const defaultComponent = pluginDefaultRegistry[slot];
  if (defaultComponent) {
    return defaultComponent;
  }

  // 3. No component registered
  return null;
}
```

#### `PluginSlot.tsx`
```tsx
import React from 'react';
import { SlotName, resolveSlotComponent } from './slot-registry';
import { useTenant } from '@/core/context/TenantContext';

interface PluginSlotProps {
  name: SlotName;
  pluginId?: string; // Optional: verify tenant has entitlement
  context?: Record<string, any>;
  fallback?: React.ReactNode;
}

export function PluginSlot({ name, pluginId, context, fallback = null }: PluginSlotProps) {
  const { tenant, hasPlugin } = useTenant();

  // If slot is tied to a specific plugin and tenant doesn't have it enabled, unmount
  if (pluginId && !hasPlugin(pluginId)) {
    return <>{fallback}</>;
  }

  const Component = resolveSlotComponent(name, tenant.theme || 'default');

  if (!Component) {
    return <>{fallback}</>;
  }

  return <Component {...context} />;
}
```

### 3.5 Build Root App Router & Contexts (`resources/js/core/`)
- `TenantContext.tsx`: Calls `GET /api/tenant/bootstrap`, holds active theme name and plugin list, provides `hasPlugin(id: string): boolean`.
- `AuthContext.tsx`: Provides customer login/logout methods, user state, and session revalidation.
- `AppRouter.tsx`: Configured with React Router `BrowserRouter` loading page shells based on active theme.

### 3.6 Update Blade SPA Shell (`resources/views/app.blade.php`)
- Replace `@inertia` with `<div id="app"></div>`.
- Replace `@inertiaHead` with clean dynamic HTML headers.
- Update `resources/js/app.tsx` to mount React via `createRoot(document.getElementById('app')!).render(...)`.

---

## 4. Verification & Testing

### 4.1 Automated Backend Tests
- `tests/Feature/Api/BootstrapTest.php`: Assert `GET /api/tenant/bootstrap` returns 200 with tenant (including `theme`), settings, and active plugins list.
- `tests/Feature/Api/AuthTest.php`: Assert login returns JSON with stateful cookie, logout invalidates session.

### 4.2 Frontend Verification
- Run `npm run build` to verify clean TypeScript compilation without Inertia dependencies in the new shell.
- Open tenant domain in browser, verify React boots, fetches `/api/tenant/bootstrap`, and displays the theme layout.
