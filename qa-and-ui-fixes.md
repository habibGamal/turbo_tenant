# QA, UI/UX & RTL Defect Fixes

> Task: Implement all fixes from the QA audit and UI/UX design reports, including checkout duplicate buttons.

## Task Breakdown

### Phase 1: Checkout & Interaction Ergonomics
- [x] Task 1.1: Fix duplicate "Place Order" button on `< lg` viewports in `OrderSummary.tsx`
- [x] Task 1.2: Reposition "Add to Cart" Toaster to `bottom-right` / `bottom-center` with `closeButton` in `resources/js/app.tsx` and fix CTA touch target (min 40-44px)

### Phase 2: RTL & Form UX Fixes
- [x] Task 2.1: Fix RTL Filter Drawer clipping in `ThemedMenuPage.tsx` and `sheet.tsx`
- [x] Task 2.2: Fix inverted "Back to Login" arrow icon in `ForgotPassword.tsx`
- [x] Task 2.3: Decouple Name and Phone validation error strings in `GuestInformationForm.tsx` & `CheckoutAddressForm.tsx`
- [x] Task 2.4: Enforce `dir="ltr"` container for phone input in checkout forms

### Phase 3: Translations & Typography
- [x] Task 3.1: Localize auth validation error messages into Arabic in `Login.tsx`, `Register.tsx`, `ForgotPassword.tsx`
- [x] Task 3.2: Remove duplicate English headline in Arabic mode on `OrderShow.tsx`
- [x] Task 3.3: Localize static CMS pages (`/pages/*`) and enforce `dir="rtl"` on Arabic fallback content
- [x] Task 3.4: Fix Arabic grammatical pluralization in menu, product details, and return policy
- [x] Task 3.5: Translate header cart title, default menu sort label, and remove double period in footer

### Phase 4: UI, Visual Depth & Touch Targets
- [x] Task 4.1: Hide native `-webkit-search-cancel-button` in `resources/css/app.css` to fix double search clear icon
- [x] Task 4.2: Add subtle border (`border-slate-100`) and refined ambient depth shadow to `ProductCard.tsx`
- [x] Task 4.3: Increase card quick-add button & wishlist button touch targets to 44×44px
- [x] Task 4.4: Update product card title clamping to 2 lines (`line-clamp-2 min-h-[2.5rem]`) on mobile

### Phase 5: Verification & Testing
- [x] Task 5.1: Run lint and typecheck (`npm run build` or `npx tsc`)
- [x] Task 5.2: Live browser visual verification across Desktop and Mobile viewports
