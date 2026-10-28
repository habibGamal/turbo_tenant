# Task Plan: Page Transition Animation

## Objective
Implement high-performance, accessible, lightweight page transition animations between route changes in the Turbo Tenant React storefront SPA using Tailwind CSS animations and React Router.

## Architectural Decisions (Confirmed with User)
- **Engine:** Pure Tailwind CSS keyframes & transitions (0 additional dependencies, 0 runtime overhead, optimal Core Web Vitals).
- **Style:** Subtle Fade & Slight Upward Lift (`opacity: 0 -> 1`, `translateY: 6px -> 0`, duration ~220ms with custom cubic-bezier ease-out).
- **Scope:** Main content wrapper (`<main>`) within `MainLayout.tsx` ensuring header navigation and footer remain stable and persistent.
- **Accessibility:** Full `prefers-reduced-motion` support disabling translations and transitions for sensitive users.
- **Scroll Behavior:** Instant scroll restoration to top on page change.

---

## Action Tasks

- [x] Task 1: Add `@keyframes page-enter` and `.animate-page-enter` utility in `resources/css/app.css` with `prefers-reduced-motion` compliance.
- [x] Task 2: Create a reusable `PageTransition` component (`resources/js/components/PageTransition.tsx`) utilizing `useLocation().pathname` as the transition trigger with scroll-to-top on route changes.
- [x] Task 3: Integrate `PageTransition` into `MainLayout.tsx` to wrap `{children}` seamlessly.
- [x] Task 4: Verify compatibility across all routes in `AppRouter.tsx` (Home, Menu, Products, Pages, Cart, Checkout, Orders, Tracking).
- [x] Task 5: Run TypeScript compilation (`npm run build`) and verification checks to ensure zero regressions.
