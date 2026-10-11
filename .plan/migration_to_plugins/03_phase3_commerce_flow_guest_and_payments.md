# Phase 3 Migration Plan: Commerce Flow (Guest Checkout & Payments)

> **Milestone:** M3  
> **Plugins:** `guest-checkout`, `payment-processing`  
> **Scope:** Customer acquisition modes, checkout gating, redirect-to-login enforcement, and payment gateway execution  
> **Target Path:** `.plan/migration_to_plugins/03_phase3_commerce_flow_guest_and_payments.md`  

---

## 1. Objectives & Overview

Phase 3 introduces customer acquisition flexibility and payment settlement:
1. **`guest-checkout`**: Provides guest customer accounts, friction-free ordering, and order tracking. **Per user decision: When deactivated, unauthenticated users navigating to checkout are immediately redirected to `/login`.**
2. **`payment-processing`**: Centralizes payment gateways (Paymob, Kashier, COD), webhook receivers, and payment callbacks.

---

## 2. Plugin 6: `guest-checkout`

### 2.1 Manifest Specification (`app/Plugins/GuestCheckout/plugin.json`)
```json
{
  "id": "guest-checkout",
  "name": "Guest Checkout & Order Tracking",
  "version": "1.0.0",
  "category": "commerce",
  "description": "Friction-free guest ordering, guest order tracking, and guest-to-account conversion.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "order-management"
  ],
  "providers": [
    "App\\Plugins\\GuestCheckout\\GuestCheckoutServiceProvider"
  ],
  "filament": "App\\Plugins\\GuestCheckout\\src\\Filament\\GuestCheckoutFilamentPlugin"
}
```

### 2.2 Strict Redirect-to-Login Enforcement
In accordance with user design requirements, when `guest-checkout` is **deactivated**:
1. **Storefront Route Guard (`EnsureCheckoutAccessMiddleware`):**
   ```php
   public function handle(Request $request, Closure $next)
   {
       $guestActive = app(PluginManager::class)->isPluginActive(tenant(), 'guest-checkout');

       if (! auth()->check() && ! $guestActive) {
           session()->put('url.intended', route('checkout'));

           return redirect()->route('login')->with('warning', 'Please sign in or create an account to proceed with checkout.');
       }

       return $next($request);
   }
   ```
2. **Order Placement Pipeline Guard (`GuestCheckoutPermissionPipe`):**
   ```php
   public function handle(OrderValidationContext $context, Closure $next)
   {
       $guestActive = app(PluginManager::class)->isPluginActive(tenant(), 'guest-checkout');

       if ($context->data->isGuest()) {
           if (! $guestActive) {
               throw new OrderValidationException('Guest checkout is not permitted for this store. Please log in.');
           }

           $guestUser = app(GuestUserService::class)->findOrCreate($context->data->guestData);
           $context->setResolvedGuest($guestUser);
       }

       return $next($context);
   }
   ```
3. **Guest Order Tracking Routes:**
   - Routes `/orders/track` and `/track-order` are registered **only** by `GuestCheckoutServiceProvider`. When the plugin is deactivated, visiting `/track-order` returns HTTP 404.

### 2.3 Files to Migrate & Relocate
| Original Location | Destination in Plugin | Action |
| :--- | :--- | :--- |
| `app/Models/GuestUser.php` | `app/Plugins/GuestCheckout/src/Models/GuestUser.php` | Move model |
| `app/Services/GuestUserService.php` | `app/Plugins/GuestCheckout/src/Services/GuestUserService.php` | Move service |
| `app/Filament/Resources/GuestUsers/` | `app/Plugins/GuestCheckout/src/Filament/Resources/GuestUsers/` | Move resource |
| `database/migrations/tenant/2026_02_12_000001_create_guest_users_table.php` | `app/Plugins/GuestCheckout/database/migrations/tenant/...` | Relocate |
| `database/migrations/tenant/2026_02_12_000002_add_guest_support_to_orders_table.php` | `app/Plugins/GuestCheckout/database/migrations/tenant/...` | Relocate |
| `resources/js/themes/default/pages/Track.tsx` | `app/Plugins/GuestCheckout/resources/js/Track.tsx` | Relocate view |

---

## 3. Plugin 7: `payment-processing`

### 3.1 Manifest Specification (`app/Plugins/PaymentProcessing/plugin.json`)
```json
{
  "id": "payment-processing",
  "name": "Payment Processing & Gateways",
  "version": "1.0.0",
  "category": "commerce",
  "description": "Multi-gateway payment orchestration supporting Cash on Delivery (COD), Paymob, and Kashier.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "order-management"
  ],
  "providers": [
    "App\\Plugins\\PaymentProcessing\\PaymentProcessingServiceProvider"
  ]
}
```

### 3.2 Files to Migrate & Relocate
- **Services:**
  - `PaymentGatewayFactory.php`, `PaymobService.php`, `KashierService.php`, `app/Services/Payment/*` (Callbacks, Webhooks).
- **Controllers:**
  - `PaymentController.php`, `PaymobWebhookController.php`, `KashierWebhookController.php`.
- **Routes:**
  - Webhooks `/api/webhooks/paymob` and `/api/webhooks/kashier` loaded by `PaymentProcessingServiceProvider`.
- **Migrations:**
  - `2025_11_20_164107_add_payment_fields_to_orders_table.php`.
- **Frontend Views:**
  - `Kashier.tsx`, `PaymentCallback.tsx`.

---

## 4. Phase 3 Verification Checklist
1. **Guest Mode Active:**
   - Unauthenticated customer adds items $\to$ visits `/checkout` $\to$ guest form appears $\to$ order places as guest $\to$ redirects to `/orders/track`.
2. **Guest Mode Deactivated (Redirect Verification):**
   - Run `php artisan tenant:plugin:deactivate {tenant} guest-checkout`.
   - As an unauthenticated customer, navigate to `/checkout`.
   - **Verify:** System immediately redirects to `/login` with return intent and flash warning.
   - Direct POST `/orders/place` with guest data fails with HTTP 422.
   - Admin sidebar hides the "Guest Users" navigation item.
3. **Payment Gateways:**
   - Place order using COD $\to$ marked as pending/placed.
   - Place order using Paymob $\to$ redirect to Paymob iframe $\to$ simulate webhook $\to$ marked as paid.
