# Task Plan: Product Page Skeleton Loading

## Objective
Replace the generic spinner loading screen on the product details page in the default theme with a full-page matching skeleton loader using `@/components/ui/skeleton`, adhering to layout dimensions, design tokens, and accessibility standards.

## Architectural Decisions (Confirmed with User)
- **Scope:** Full-page matching skeleton layout (Breadcrumb, Image gallery/aspect ratio, Info & Option selectors, Add to Cart action, Tab headers, and Related products grid).
- **Animation:** Standard `animate-pulse` via Shadcn UI `Skeleton` with theme-aware muted colors.
- **Organization:** Dedicated standalone component `ProductPageSkeleton.tsx` colocated within `resources/js/themes/default/plugins/catalog-products/components/ProductPageSkeleton.tsx`.
- **Accessibility:** `aria-busy="true"` and `role="status"` on the container with reduced layout shift (CLS).

---

## Action Tasks

- [x] Task 1: Create `ProductPageSkeleton.tsx` in `resources/js/themes/default/plugins/catalog-products/components/`.
- [x] Task 2: Export `ProductPageSkeleton` and wire it up in `ThemedProductShow.tsx` to replace `Loader2`.
- [x] Task 3: Verify TypeScript compilation and production build (`npm run build`).
