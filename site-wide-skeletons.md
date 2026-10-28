# Task Plan: Site-Wide Skeleton Loading Implementation

## Objective
Replace all generic loading spinners and text placeholders across the Turbo Tenant storefront with layout-matched, responsive skeleton screens built using Shadcn UI's `Skeleton` component in a centralized file.

## Architectural Decisions (Confirmed with User)
- **Scope:** Comprehensive coverage across all storefront pages and plugin components (Checkout, Order Details, Cart, Orders List, Favorites, Catalog Sections, Hero Banners, CMS Pages, Payment Gateway, and Reviews).
- **Structure:** Centralized component collection at `resources/js/components/ui/page-skeletons.tsx` exporting dedicated, accessible skeleton layouts.
- **Home Page CLS Elimination:** Structural skeleton placeholders for Hero Banners and Catalog Sections to ensure zero layout shift.
- **Accessibility:** `role="status"` and `aria-busy="true"` on all loading containers.

---

## Action Tasks

- [x] Task 1: Create `resources/js/components/ui/page-skeletons.tsx` containing all layout-matched skeleton components.
- [x] Task 2: Integrate `CheckoutSkeleton` in `resources/js/themes/default/pages/Checkout.tsx`.
- [x] Task 3: Integrate `OrderShowSkeleton` in `resources/js/themes/default/pages/OrderShow.tsx`.
- [x] Task 4: Integrate `CartSkeleton` in `resources/js/themes/default/plugins/cart-engine/ThemedCart.tsx`.
- [x] Task 5: Integrate `OrdersListSkeleton` in `resources/js/themes/default/plugins/order-management/ThemedOrdersList.tsx`.
- [x] Task 6: Integrate `FavoritesSkeleton` in `resources/js/themes/default/plugins/customer-favorites/ThemedCustomerFavorites.tsx`.
- [x] Task 7: Integrate `CatalogSectionsSkeleton` in `resources/js/themes/default/plugins/catalog-products/ThemedProductsSection.tsx`.
- [x] Task 8: Integrate `HeroBannerSkeleton` in `resources/js/themes/default/plugins/hero-banners/ThemedHeroBanners.tsx`.
- [x] Task 9: Integrate `CmsPageSkeleton` in `resources/js/themes/default/plugins/cms-pages/ThemedCmsPages.tsx`.
- [x] Task 10: Integrate `PaymentGatewaySkeleton` in `resources/js/themes/default/plugins/payment-processing/ThemedKashierPayment.tsx` and `ReviewsSkeleton` in `resources/js/themes/default/plugins/product-reviews/ThemedReviews.tsx`.
- [x] Task 11: Run TypeScript compilation and production build (`npm run build`) to ensure zero errors.
