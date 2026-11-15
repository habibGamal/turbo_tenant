# Default Theme — UI/UX Review & Enhancement Plan

Scope: everything under `resources/js/themes/default` (layout, navigation, 8 core pages, 13 plugin overrides) plus the shared pieces they lean on (`AppRouter`, `PageTransition`, `app.css`, `components/ui`).
Method: source review of every page and component. Nothing was run in a browser yet; items marked **(verify)** need a quick visual check before fixing.

Constraints to keep while implementing:
- Keep every `data-testid` — the Playwright e2e suites (`tests/e2e`) depend on them.
- Keep RTL/Arabic parity: logical properties (`ms-/me-/start-/end-`), `rtl:` variants, `i18n.dir()`.
- Moving layout into a route-level layout changes routes/pages → refresh the engineering atlas per `AGENTS.md`.

---

## 1. Bugs found during review (fix first)

| # | Where | Problem |
|---|-------|---------|
| B1 | [HeroSlider.tsx:45](resources/js/themes/default/plugins/hero-banners/components/HeroSlider.tsx:45) | Returns `null` early, *before* `useRef`/`useEffect` → violates the Rules of Hooks; crashes if slides go from 0 → n. Also renders **only the CTA** — `title`, `subtitle`, `badge`, `secondary_cta` from the API are never shown. |
| B2 | [ThemedPackageCard.tsx:75](resources/js/themes/default/plugins/combo-packages/components/ThemedPackageCard.tsx:75) | "Select Package" button has **no `onClick`** — it does nothing. Description isn't localized either. |
| B3 | [ProductsSection.tsx:122](resources/js/themes/default/plugins/catalog-products/components/ProductsSection.tsx:122), [ThemedProductsSection.tsx:149](resources/js/themes/default/plugins/catalog-products/ThemedProductsSection.tsx:149) | "View All" links to `/sections/:id`, which **has no route** → the `*` catch-all silently sends the user back to `/`. |
| B4 | [ProductCard.tsx:239](resources/js/themes/default/plugins/catalog-products/components/ProductCard.tsx:239) | `{product.rating && …}` / `{product.reviewsCount && …}` render a literal **"0"** when the value is 0. |
| B5 | [OrderSummary.tsx:55](resources/js/themes/default/plugins/order-management/components/OrderSummary.tsx:55), [CartSummary.tsx:31](resources/js/themes/default/plugins/cart-engine/components/CartSummary.tsx:31) | `sticky top-4` under a 64px sticky header (`z-50`) → the top of the summary card slides **behind the header** on desktop. Should be `top-20`/`top-24` (the menu sidebar already uses `top-24`). |
| B6 | [ThemedProductsSection.tsx:99](resources/js/themes/default/plugins/catalog-products/ThemedProductsSection.tsx:99) | Home category tabs are `sticky top-0 z-20` → stick **underneath** the header and disappear. |
| B7 | [ThemedMenuPage.tsx:846](resources/js/themes/default/plugins/catalog-products/ThemedMenuPage.tsx:846) | Mobile category bar is `sticky top-16`, but on `/menu` the header is taller (search row auto-expands) → the bar is partly hidden under the header **(verify)**. |
| B8 | [ProductOptionsModal.tsx:61](resources/js/themes/default/plugins/catalog-products/components/ProductOptionsModal.tsx:61) | Heart button in the options modal is a mocked no-op (`isFavorite = false`, empty handler) — shown to the user but does nothing. |
| B9 | [PageTransition.tsx:25](resources/js/components/PageTransition.tsx:25) | Always scrolls to the top on pathname change, including **Back** → Menu → Product → Back drops the user at the top of the menu. Needs scroll restoration (`ScrollRestoration` / POP-aware). |
| B10 | [ThemedKashierPayment.tsx:79](resources/js/themes/default/plugins/payment-processing/ThemedKashierPayment.tsx:79) | Every string on the payment page is hard-coded English (no `t()`), breaking the Arabic flow at the most sensitive step. |
| B11 | [Footer.tsx:55](resources/js/themes/default/components/Footer.tsx:55) (and the copy in `Navigation.tsx`) | "Track order" points to `/orders` (auth-only); guests should go to `/track-order`. |
| B12 | [ProductImage.tsx:46](resources/js/themes/default/plugins/catalog-products/components/ProductImage.tsx:46) | Share button is `opacity-0 group-hover:opacity-100` → **invisible on touch devices**; no fallback when `navigator.share` is missing (should copy the link + toast). |
| B13 | [ProductInfo.tsx:121](resources/js/themes/default/plugins/catalog-products/components/ProductInfo.tsx:121), [ThemedProductDetails.tsx:47](resources/js/themes/default/plugins/catalog-products/ThemedProductDetails.tsx:47) | Fallback feature cards claim "Fresh ingredients / 30 min or less", and the details card always says "In Stock" — invented claims shown to every tenant. Hide when not configured. |
| B14 | [AppRouter.tsx:220](resources/js/core/router/AppRouter.tsx:220) | Unknown URLs redirect to `/` silently; the theme defines `NotFound?` but no route uses it. CMS page with a bad slug renders **nothing** ([ThemedCmsPages.tsx:75](resources/js/themes/default/plugins/cms-pages/ThemedCmsPages.tsx:75)). |

---

## 2. Cross-cutting foundations

These change how every page feels, so they come before page polish.

### F1. Persistent app shell (layout route)
Today every page renders its own `<MainLayout>`, and pages are `lazy()` behind a Suspense fallback that is an empty `min-h-screen` div ([AppRouter.tsx:116](resources/js/core/router/AppRouter.tsx:116)). On every navigation the header, bottom nav and footer **unmount and blink**, header state (search text, open menu) resets, and the first visit to a page shows a blank screen.
- Move `MainLayout` into a layout route (`<Route element={<ThemeShell/>}>` + `<Outlet/>`), put Suspense *inside* the shell with the existing page skeletons as fallback.
- Pages stop importing `MainLayout`; they pass page-specific background or "focused mode" (checkout/payment hide the footer and bottom nav) through a small context or route `handle`.
- Add one `<main id="main">` landmark in the shell + a "Skip to content" link; pages currently mix `<main>`, `<div>`, and sometimes nest `<main>` (Product page).
- Atlas refresh needed for this change.

### F2. Brand identity actually reaching the theme
The palette in [config.ts](resources/js/themes/default/config.ts) is unused (nothing imports it); colors come from `app.css` neutral oklch values overridden server-side by `ThemeCssBuilder`. With no brand color set, the store is near-black/grey everywhere.
- Delete or wire `config.ts` (it's dead code that misleads theme authors — `THEME_GUIDE.md` documents it as live).
- Define a proper token set for the theme: `--brand`, `--brand-foreground`, surface tiers (`--surface-1/2/3`), semantic `--success/--warning/--info` (pages hard-code `emerald-600`, `amber-600`, etc. in ~15 places), elevation shadows, and a type scale.
- Make sure a tenant's single brand hex produces good hover/soft/ring variants (`color-mix` in CSS) so `bg-primary/10`-style tints look intentional.

### F3. Shared building blocks (removes most duplication)
| Component | Replaces |
|-----------|----------|
| `AuthShell` (brand mark, title, card, footer link) | Copy-pasted shells in Login, Register, ForgotPassword, ResetPassword (each has the icon tile **and** a duplicated CardTitle/description). |
| `PasswordInput` (toggle with `aria-label`, `aria-pressed`) | 5 copies, none with accessible labels. |
| `FormField` (label, hint, error with `aria-describedby`/`aria-invalid`) | ~30 hand-rolled field+error blocks. |
| `SelectableTile` (radio/checkbox card, large tap target) | Order type, payment method, address, variants, extras, weight options — all styled differently. |
| `QuantityStepper` (sizes sm/md/lg, ≥40px targets) | 4 implementations; cart's is 24px (`h-6 w-6`, [CartItem.tsx:190](resources/js/themes/default/plugins/cart-engine/components/CartItem.tsx:190)). |
| `ResponsiveModal` (Dialog ≥ md, bottom sheet < md, sticky footer) | Product options, add address, write review, filters. |
| `StatusScreen` (icon, title, message, actions) | Payment callback (4 copy-pasted branches), not-found, errors, empty states. |
| `OrderDetailView` | `OrderShow` and `ThemedGuestOrderTracking` render the same order in two different layouts. |
| `SectionHeader` (title, subtitle, "view all") | 6 variants with sizes from `text-2xl` to `text-5xl`. |
| `getText()` hook (`useLocalized`) | Re-declared in ~25 files; several places forget `_ar` fields (product description, combo description, CMS links). |
| `footerSections` data | Defined twice (Footer + Navigation). |

### F4. Interaction & accessibility patterns (site-wide)
- **Nested interactive elements**: `<Link><Button>` and `<a><Button>` are used for nav links, Google sign-in, "View all", "Back", cart "Continue shopping", etc. Use `<Button asChild><Link/></Button>` everywhere.
- Desktop nav has **no active state**; mobile nav does.
- Clickable `div`s with `tabIndex={-1}` checkboxes in menu filters ([ThemedMenuPage.tsx:526](resources/js/themes/default/plugins/catalog-products/ThemedMenuPage.tsx:526)) and `X` icons as the only way to remove filter chips → not keyboard reachable.
- Native `<input type="checkbox">` in Login and product extras next to shadcn `Checkbox` elsewhere → inconsistent look and focus rings.
- Mobile hamburger sheet always opens from the right ([MobileMenu.tsx:57](resources/js/themes/default/components/navigation/MobileMenu.tsx:57)); the filter sheet already flips for RTL.
- Respect `prefers-reduced-motion` (hero autoplay, hover lifts, page-enter animation, cart bump).
- Minimum 44px touch targets (hero dots are 8px, cart steppers 24px, menu "+" 28px).

### F5. Feedback & motion language
- One "added to cart" pattern: animated cart badge + toast with **View cart** action; after the first add, the card's button turns into an inline stepper (cart-aware), like delivery apps.
- Optimistic cart updates for quantity changes (currently every tap waits for the server and disables the row).
- Loading for "open options" on menu rows (`handleOpenOptions` fetches with no visible feedback).
- Standard skeleton → content crossfade; no layout shift on images (fixed aspect ratios exist on most, verify hero).

### F6. Header & mobile chrome
- Mobile header currently carries: logo, search toggle, language switcher, notifications, login/avatar, hamburger **plus** a 5-item bottom nav with Home/Menu/Cart/Track/Login. Login/profile appears twice.
  → Mobile header: logo + search + cart-less actions (language moves into the hamburger/profile sheet); bottom nav stays as the primary nav.
- Search suggestions ([SearchBar.tsx](resources/js/themes/default/components/navigation/SearchBar.tsx)): add debounce + request cancellation (every keystroke fires a request and responses can arrive out of order), keyboard navigation (↑/↓/Enter/Esc, `role="listbox"`), product thumbnails, "no results" and "search all for 'x'" rows, recent searches. Replace the `onBlur` 300ms timeout with proper popover focus handling.
- Cart badge counts line items (`items.length`) — decide whether it should be total quantity, and use the same source in header and bottom nav.
- Optional "store status" strip (open/closed, delivery vs pickup, selected branch, ETA) — the data exists in locations-delivery; this is the most useful info for a restaurant visitor and is shown nowhere before checkout.

### F7. Footer
- Hidden entirely below `md` — mobile users only reach legal/contact pages via the hamburger. Add a compact mobile footer (contact, legal links, socials) above the bottom-nav spacer.
- "Legal" heading renders even with zero CMS pages; dead `showNewsletter` prop and empty newsletter block.
- `mt-20` gap is applied on every page, including checkout.

---

## 3. Page-by-page review

### Home (`pages/HomePage.tsx`)
Current order: Hero → huge category grid → combos → `<Separator>` → sticky category tabs → product sections.
- **Two category navigations** (grid + sticky tabs) and the tabs are broken (B6). Keep one: a horizontal, snap-scrolling category rail (image + name) on mobile, grid on desktop; tabs removed or turned into in-page anchors.
- Category grid on mobile is 2-col `aspect-square` cards — 10 categories push products ~5 screens down. Use a rail.
- Hero (B1): render title/subtitle/badge/secondary CTA with a readable overlay; mobile aspect `4/5` or `3/2` instead of `16/9` (very short on phones); dots ≥ 44px hit area; pause autoplay on focus and for reduced motion; respect RTL slide direction instead of forcing `dir="ltr"`.
- Product sections: on mobile, horizontal carousels instead of single-column stacks of full cards; fix "View all" (B3) → `/menu?section=` or add a section route.
- Combos: fix the dead CTA (B2); show included items and savings vs. buying separately.
- Add an optional "Reorder your last order" strip for logged-in users and a store-status/ordering-mode block near the top (F6).
- No `<Head>` title on the home page.

### Menu (`plugins/catalog-products/ThemedMenuPage.tsx`, 958 lines)
- **Two different experiences**: mobile = grouped by category with sticky scroll-spy bar (good); desktop/tablet = flat ungrouped grid where categories are only filter checkboxes. Restaurant menus are browsed by category — use the grouped + sticky category nav layout on all breakpoints (desktop: left category list with scroll-spy, or top sticky bar), keep filters secondary.
- Grouping is computed from the infinite-loaded pages (`per_page: 50`) → categories appear and reorder as more pages load, and scroll-spy jumps. Either load the whole menu (menus are small) or group server-side.
- Filter UX is inconsistent: category and sort apply instantly, price waits for "Apply". Pick one (instant, with debounced price).
- Make filter rows and chip remove buttons real buttons (F4). Use category **slug/id** in the URL consistently (menu uses name, home/product use `category_ids[]`).
- Hard-coded "Other / أخرى" group label → translation key.
- Split the file: `useMenuFilters` (URL state), `useMenuGrouping`, `MenuFilters`, `MenuGroupedList`, `MenuGrid`. It will make the above changes safe.
- Fix B7 by measuring the header height into a CSS variable (`--header-h`) and using `top-[var(--header-h)]` for every sticky element (also fixes B5/B6 uniformly).

### Product page (`ThemedProductShow.tsx`, `ProductInfo`, `ProductImage`, `ProductOptions`)
- No sticky add-to-cart on mobile: the CTA sits below all options. Add a sticky bottom bar (price + quantity + Add) above the bottom nav.
- `h1` is `text-4xl md:text-5xl` — too large on phones; scale down.
- Rating row shows five empty stars and "0 (0 reviews)" for unrated products — hide or show "No reviews yet".
- Description ignores `description_ar`.
- Information about category/price/availability is repeated in three places (ProductInfo, "Details" tab, `ThemedProductDetails` slot card). Keep one compact details list; make Reviews a section, not a tab with a near-empty sibling.
- Breadcrumb: wrap in `<nav aria-label>`, use an RTL-aware chevron, truncate long names on mobile.
- Variant tiles show `+price` but the value is the *absolute* price → show the price (or the delta) correctly.
- Extras: only one option group is supported by the UI; required groups need a clear "Required" pill, and the add button should say *why* it's disabled ("Choose 1 more").
- Related / special-offers grids → horizontal carousels on mobile.
- Share: B12.

### Product options modal
- On mobile it's a centered dialog with a full-width square image, so the options and CTA are far below the fold. Use `ResponsiveModal` (bottom sheet, small header image, scrollable body, **sticky footer with total + Add**).
- Remove or implement the fake heart (B8).

### Cart (`ThemedCart.tsx`)
- Mobile: summary/checkout button sits below the item list — add the same sticky checkout bar the checkout page uses.
- Steppers are 24px (F3/F4); remove "X" should have an accessible label (it has sr-only, ok) and a slightly larger target.
- "Continue shopping" goes to `/` — should go to `/menu`.
- Show the delivery-fee hint *with* the selected area once known; consider a free-delivery progress bar if the tenant has a threshold.
- Add an upsell row ("Goes well with…") from related products.
- Optimistic quantity updates (F5).

### Checkout (`pages/Checkout.tsx`)
The page is one long column of 6–7 equal-weight cards.
- Group into 3 numbered sections with clear headers: **1. How & where** (order type + branch + address), **2. Contact** (guest info), **3. Payment** (method + coupon + note). Collapse completed sections on mobile into one-line summaries with "Change".
- Order type and payment method as large `SelectableTile`s with icons (currently tiny radio rows).
- Branch: hide the select when there is only one branch; show address/hours when several.
- Guest area select: flat list of every area across governorates — make it searchable (Command/Combobox) and grouped by governorate.
- Guests: offer "Have an account? Log in" at the top; validation only runs for delivery — confirm name/phone requirements for takeaway.
- Order notes and coupon: collapsed by default ("Add a note", "Have a coupon?"). The coupon "Remove" button is a red destructive button — use a subtle link. Coupons aren't re-validated when the address changes even though the API takes `address_id`.
- Summary: fix sticky offset (B5); item list uses `text-[10px]` — too small.
- Inline validation on blur, not only on submit.
- `calculateTax` is hard-wired to 0 with a "14% VAT" comment — confirm intent; the order page always shows a Tax row even at 0.
- Background gradient is applied only on checkout/order/payment pages — make it part of the "focused mode" shell (F1).

### Order details (`pages/OrderShow.tsx`) + Guest tracking (`ThemedGuestOrderTracking.tsx`)
- Merge into one `OrderDetailView` (F3). Guest tracking keeps the search form **above** an already-loaded order; collapse it once a result is shown.
- Status first: big status headline with time/ETA ("Preparing — usually 20–30 min"), then the timeline, then items. Timeline steps have no timestamps; unknown statuses map silently to step 0.
- Add **Reorder** and **Get help / Call branch** actions near the top.
- Address formatting prints "Floor undefined, Apt undefined" when parts are missing — build it from present parts only (guest tracking already does this).
- Item rows: add thumbnails (checkout summary has them).
- Back button is an icon-only `Link>Button` with no label.

### Orders list (`ThemedOrdersList.tsx`)
- Whole card should be the link; today only the "View details" button is.
- "Active" vs "Past" grouping (or tabs), with the active order pinned and live-updating.
- Pagination renders every page number — switch to prev/next + "Load more" or compact pagination.
- "Try again" calls `setPage(page)`, which doesn't refetch — call `refetch()`.
- Reorder button per order.

### Payment callback & Kashier
- `StatusScreen` refactor (4 duplicated branches).
- Failed payment "Try again" sends the user to `/checkout`, where the cart may be empty — offer "Retry payment for this order" + "Pay cash instead" if supported.
- Kashier: translate everything (B10), keep the header minimal but present (brand + back to order), explain what happens after paying.

### Auth pages (Login, Register, ForgotPassword, ResetPassword)
- `AuthShell` (F3): one title (drop the duplicated icon-tile heading + CardTitle), tenant logo instead of a generic icon.
- Google button: `asChild` link (F4) and only show it when Google sign-in is configured for the tenant **(verify setting)**.
- Password toggles need `aria-label`; errors need `aria-describedby`; show password requirements on Register; add a password-strength hint.
- Forgot password: after success, replace the form with a confirmation state (resend timer) instead of keeping the form visible.
- Register: guest-checkout nudge ("You can also order without an account") when the user came from checkout.

### Profile (`pages/Profile.tsx` + partials)
- Renders `null` while auth loads ([Profile.tsx:33](resources/js/themes/default/pages/Profile.tsx:33)) → blank page flash; use a skeleton.
- Tabs show icons only on mobile — labels disappear; use a vertical list on mobile (Account hub) or scrollable tabs with labels.
- Tab state isn't in the URL (`?tab=addresses` can't be linked to from checkout).
- The bottom-nav "Profile" destination has no links to Orders, Favorites, Language, or Logout — make Profile an account hub (header card with name/email, then rows: Orders, Favorites, Addresses, Password, Language, Logout).
- Address cards: edit/delete are icon-only buttons; add "Set as default".

### Favorites (`ThemedCustomerFavorites.tsx`)
- Single-column full cards on mobile → compact rows (reuse `MenuItemRow`) or a 2-col grid.
- Unfavoriting should animate out with an Undo toast.

### CMS pages (`ThemedCmsPages.tsx`)
- Not-found renders nothing (B14) → `StatusScreen`.
- Content is injected with `dangerouslySetInnerHTML` — make sure it's sanitized server-side (or with DOMPurify) since it is admin-authored HTML.
- Hero band is very tall (`py-24`) for short policy pages; add "last updated" and a table of contents for long pages.

### Notifications dropdown
- Items aren't clickable — link order notifications to the order.
- On mobile the 320px dropdown is cramped; use a sheet.

### Missing pages
- **404 / NotFound** themed page (B14).
- **Section / collection page** for "View all" (B3) — or route to a filtered menu.
- **Offline / error boundary** screen for chunk-load failures (lazy pages + flaky mobile networks).

---

## 4. Roadmap

| Phase | Content | Size |
|-------|---------|------|
| **0 — Bug fixes** | B1–B14 | S–M, ~1–2 days, low risk |
| **1 — Foundations** | F1 app shell + Suspense skeletons + scroll restoration, `--header-h` sticky offsets, F2 tokens/semantic colors, F3 shared components (start with `ResponsiveModal`, `SelectableTile`, `QuantityStepper`, `FormField`, `StatusScreen`, `useLocalized`) | L, ~1 week; touches routing → atlas refresh |
| **2 — Conversion path** | Product page sticky CTA, options bottom sheet, cart-aware add buttons, cart sticky bar + optimistic updates, checkout regrouping | L |
| **3 — Discovery** | Unified grouped menu (all breakpoints), menu file split, home restructure (category rail, carousels, hero content), search upgrade | L |
| **4 — Post-purchase & account** | `OrderDetailView`, orders list, reorder, payment screens, profile hub, favorites, auth shell | M |
| **5 — Polish** | Motion language + reduced motion, a11y audit (WCAG AA contrast, focus order, screen reader pass in AR and EN), mobile footer, 404/offline pages | M |

## 5. How each phase gets verified
- Existing Playwright suites (`playwright.config.ts`, `tests/e2e/themes`) must stay green — selectors are `data-testid`s, which this plan keeps.
- Visual pass per phase at 375px, 768px, 1280px in **both** EN (LTR) and AR (RTL), light and dark.
- Lighthouse / axe pass on Home, Menu, Product, Checkout before and after phases 1 and 5 (CLS and tap-target size in particular).
- Phase 1 only: `python .agents/skills/engineering-atlas/scripts/test_atlas.py` and `atlas.py check .`.
