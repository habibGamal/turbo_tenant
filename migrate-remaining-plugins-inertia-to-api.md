# Migration Plan: Migrate Remaining 5 Plugins to Decoupled REST API + Headless React SPA

> Objective: Migrate the remaining 5 plugins (`PaymentProcessing`, `ProductReviews`, `CouponsPromotions`, `PushNotifications`, `ComboPackages`) to the Decoupled REST API + Headless React (TanStack Query) + Slots + Themed Components architecture. Drop all backward compatibility, purge Inertia imports and legacy web routes, and colocate all components into plugin directories.

---

## Phase 1: PaymentProcessing (`payment-processing`)
- [x] **Backend Controller**: Create `app/Plugins/PaymentProcessing/src/Http/Controllers/Api/PaymentProcessingApiController.php` providing `paymentStatus` and `kashierConfig` JSON endpoints.
- [x] **Callback Handling**: Update `PaymentController@paymentCallback` to perform server-side verification and return a 302 redirect to the SPA route (`/orders/{orderId}/payment/callback?status=success|failed&message=...`). Purge `Inertia::render('PaymentCallback')` and `Inertia::render('Kashier')`.
- [x] **Tenant Routes**: Update `routes/tenant.php` to register `/api/orders/{orderId}/payment-status` and `/api/payments/{order}/kashier`.
- [x] **Frontend Domain Module**: Scaffold `resources/js/plugins/payment-processing/` (`api/usePaymentStatus.ts`, `api/useKashierConfig.ts`, `types/index.ts`, `components/DefaultPaymentCallback.tsx`, `components/DefaultKashierPayment.tsx`, `index.ts`).
- [x] **Theme Components**: Create `resources/js/themes/default/plugins/payment-processing/ThemedPaymentCallback.tsx` and `ThemedKashierPayment.tsx` using `react-router-dom` and TanStack Query.
- [x] **Purge Old Inertia Views**: Delete `resources/js/themes/default/pages/PaymentCallback.tsx` and `resources/js/themes/default/pages/Kashier.tsx`.
- [x] **Router & Slots**: Wire `/orders/:id/payment/callback` and `/payments/:id/kashier` in `AppRouter.tsx`.

---

## Phase 2: ProductReviews (`product-reviews`)
- [x] **Backend Controller**: Create `app/Plugins/ProductReviews/src/Http/Controllers/Api/ProductReviewsApiController.php` returning pure `JsonResponse` (200 for index, 201 for store). Remove `return back()->with(...)`.
- [x] **Tenant Routes**: In `app/Plugins/ProductReviews/routes/tenant.php`, set routes to `/api/products/{product}/reviews`.
- [x] **Frontend Domain Module**: Scaffold `resources/js/plugins/product-reviews/` (`api/useProductReviews.ts`, `api/useSubmitReview.ts`, `types/index.ts`, `components/DefaultReviewForm.tsx`, `components/DefaultReviewsSection.tsx`, `index.ts`).
- [x] **Theme Components**: Colocate review components in `resources/js/themes/default/plugins/product-reviews/components/` (`ReviewForm.tsx`, `RatingSummary.tsx`, `ReviewList.tsx`, `ReviewsSection.tsx`, `ThemedReviews.tsx`). Purge `@inertiajs/react` `useForm`.
- [x] **Purge Old Files & Decouple**: Delete `resources/js/themes/default/components/ReviewForm.tsx` and remove review subcomponents from `resources/js/themes/default/plugins/catalog-products/components/`.
- [x] **Slots Registry**: Add `'product.reviews'` to `slot-registry.ts` and mount in `ProductShow.tsx` / `ThemedProductDetails.tsx`.

---

## Phase 3: CouponsPromotions (`coupons-promotions`)
- [x] **Backend Controller**: Create `app/Plugins/CouponsPromotions/src/Http/Controllers/Api/CouponApiController.php` supporting both authenticated users and guest checkout validations.
- [x] **Tenant Routes**: In `app/Plugins/CouponsPromotions/routes/tenant.php`, move `/coupons/validate` to `/api/coupons/validate` without mandatory `auth` middleware.
- [x] **Frontend Domain Module**: Scaffold `resources/js/plugins/coupons-promotions/` (`api/useValidateCoupon.ts`, `types/index.ts`, `components/DefaultCouponForm.tsx`, `index.ts`).
- [x] **Theme Components**: Create `resources/js/themes/default/plugins/coupons-promotions/` with `ThemedCouponForm.tsx`.
- [x] **Order Management Integration**: Update `CouponCodeSection.tsx` in `order-management` to call `/api/coupons/validate`.
- [x] **Slots Registry**: Add `'checkout.coupon.form'` to `slot-registry.ts`.

---

## Phase 4: PushNotifications (`push-notifications`)
- [x] **Backend Controller**: Move/refactor to `app/Plugins/PushNotifications/src/Http/Controllers/Api/NotificationController.php`.
- [x] **Tenant Routes**: Ensure `/api/notifications` routes are cleanly defined in `routes/tenant.php`.
- [x] **Frontend Domain Module**: Scaffold `resources/js/plugins/push-notifications/` (`api/useNotifications.ts`, `api/useNotificationMutations.ts`, `types/index.ts`, `components/DefaultNotificationBell.tsx`, `index.ts`).
- [x] **Theme Components**: Colocate in `resources/js/themes/default/plugins/push-notifications/components/NotificationDropdown.tsx` and `ThemedNotificationBell.tsx` using TanStack Query polling.
- [x] **Purge Old File**: Delete `resources/js/themes/default/components/navigation/NotificationDropdown.tsx`.
- [x] **Mount in Navigation**: Mount `<PluginSlot name="header.actions.notifications" />` in `Navigation.tsx`. Add slot to `slot-registry.ts`.

---

## Phase 5: ComboPackages (`combo-packages`)
- [x] **Backend Controller**: Refactor to `app/Plugins/ComboPackages/src/Http/Controllers/Api/ComboPackagesApiController.php`.
- [x] **Tenant Routes**: Ensure routes point to `/api/packages` in `routes/tenant.php`.
- [x] **Frontend Domain Module**: Scaffold `resources/js/plugins/combo-packages/` (`api/usePackages.ts`, `api/usePackage.ts`, `types/index.ts`, `components/DefaultPackageCard.tsx`, `index.ts`).
- [x] **Theme Components**: Create `resources/js/themes/default/plugins/combo-packages/` with `ThemedPackagesSection.tsx` and `ThemedPackageCard.tsx`.
- [x] **Slots Registry**: Add `'home.sections.combos'` to `slot-registry.ts`.

---

## Phase 6: Core Registration & Full Verification
- [x] Register all 5 plugins in `resources/js/plugins/registry.ts`.
- [x] Verify TypeScript: `npx tsc --noEmit`.
- [x] Verify Backend Tests: `php artisan test`.
