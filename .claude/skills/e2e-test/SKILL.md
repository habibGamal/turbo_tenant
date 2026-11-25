---
name: e2e-test
description: Comprehensive Live-Only E2E testing framework for Turbo Tenant plugins. Universal discovery methodology, theme-decoupled contracts, Playwright automation patterns, and multi-scenario matrices.
allowed-tools: Read, Write, Edit, Glob, Grep, RunCommand
---

# Turbo Tenant E2E Testing Skill: Plugin Testing Framework

> Universal methodology for architecting, implementing, and verifying exhaustive, live-only, theme-decoupled End-to-End (E2E) test suites for any Turbo Tenant plugin.

---

## 1. Universal Invariants (Non-Negotiable)

1. **Strict Live-Mode Execution**:
   - Tests execute against the live multi-tenant environment (`http://kofe.localhost:8001`) with real database persistence.
   - **No mock handlers, no synthetic state injection**: All state is produced through actual user actions in the browser UI (navigating, typing, clicking, selecting).

2. **Decoupled Theme Architecture**:
   - Test suites NEVER query raw CSS classes, Tailwind utility classes, or DOM tag hierarchies directly.
   - All interactions must go through the typed **Theme Adapter Contract** (`tests/e2e/themes/theme.contract.ts`), implemented via standardized `data-testid` attributes documented in `docs/themes.md`.
   - Guarantees that any future theme (`minimal`, `luxury`, `modern`) passes the same test suite automatically if it implements the specification.

3. **Single-Threaded Server Concurrency Rule**:
   - Because local PHP CLI development servers (`php artisan serve`) are single-threaded:
     - Local Playwright tests MUST run with `workers: 1`.
     - Page navigations MUST use `{ waitUntil: 'domcontentloaded' }` to avoid deadlocks on open asset streams.
     - Third-party tracking scripts (`connect.facebook.net`, `google-analytics.com`) MUST be aborted in test fixtures.

4. **Strict-Mode Selector Scoping**:
   - Avoid ambiguous `.or(...)` chains that resolve to multiple DOM nodes. Always scope locators to the container or use `.first()` when coexisting elements (e.g. multiple `<main>` tags or global forms) could collide.

---

## 2. Universal Plugin Archetypes & Testing Aspects

Every plugin in Turbo Tenant falls into one or more of the following archetypes. Use these archetypes to derive the specific aspects to test:

| Archetype | Examples | Key Testing Aspects to Examine |
|:---|:---|:---|
| **Stateful Engine** | `cart-engine`, `order-management` | State aggregation, quantity mutations, dynamic updates, subtotal math, clear/reset lifecycle, session persistence. |
| **Catalog & Browsing** | `catalog-products`, `combo-packages`, `cms-pages` | Data listings, filter/category tab switching, sorting, detail page routing, dynamic slugs, 404 fallbacks. |
| **Data Intake & Workflow** | `guest-checkout`, `auth`, `customer-profile` | Form field validation, input masking, phone/address entry, step transitions, submission responses, error banners. |
| **Calculation & Promotion** | `coupons-promotions`, `locations-delivery` | Valid/invalid codes, dynamic price recalculation, zone-based fees, threshold rules, removal/expiry alerts. |
| **Feedback & Engagement** | `product-reviews`, `customer-favorites`, `hero-banners` | Toggle states, rating inputs, review lists, interactive carousels, optimistic UI updates vs. persisted state. |
| **Asynchronous & Notifications** | `push-notifications`, `payment-processing` | Counter badges, read/unread states, polling/event triggers, third-party redirect handoffs, callback verification. |

---

## 3. The 5-Phase Plugin Testing Protocol

### Phase 1: Deep Discovery & Matrix Derivation
Before writing a single test line, examine the plugin across its full stack:
1. **Backend Discovery**: Inspect routes (`routes/tenant.php`), API controllers, database migrations, and models to understand:
   - What data entities are exposed?
   - What business rules, calculations, or constraints exist?
   - What validation rules trigger `422` or error messages?
2. **Frontend Discovery**: Inspect fallback components (`resources/js/plugins/{slug}/`) and theme overrides (`resources/js/themes/default/plugins/{slug}/`):
   - What interactive elements exist (buttons, inputs, dropdowns, toggles)?
   - What client-side state hooks (`useQuery`, `useMutation`) are triggered?
3. **Derive the Universal Test Matrix**: Break tests into modular numbered specs:
   - `01-primary-flow.live.spec.ts` (Core happy-path journey)
   - `02-configurations-and-options.live.spec.ts` (Variants, types, filters, options)
   - `03-domain-computations.live.spec.ts` (Calculations, multipliers, fee logic)
   - `04-state-mutations.live.spec.ts` (Updates, edits, toggles, badge sync)
   - `05-boundary-conditions.live.spec.ts` (Limits, lower/upper bounds, disabled states)
   - `06-item-or-record-removal.live.spec.ts` (Deletions, un-favoriting, clearing)
   - `07-empty-and-error-states.live.spec.ts` (Zero items, missing data, validation errors)
   - `08-persistence-and-routing.live.spec.ts` (Browser refresh, SPA client navigation)
   - `09-cross-plugin-integrations.live.spec.ts` (Handoffs, slot interactions, downstream guards)

### Phase 2: Theme Test ID Specification & Contract Alignment
1. **Spec Check**: Check `docs/themes.md` (`data-testid` contract section). If the plugin introduces new user interaction points:
   - Document standard `data-testid` names in the specification table.
2. **Component Instrumentation**:
   - Add `data-testid="{identifier}"` to the plugin's React components.
   - Run `npm run build` to compile the identifiers into the frontend bundle.
3. **Contract Extension**:
   - Add typed getter methods to `tests/e2e/themes/theme.contract.ts` (under `I{Plugin}Theme`).
   - Implement the getters in `tests/e2e/themes/base/BaseThemeAdapter.ts` using the standard `data-testid` attributes.

### Phase 3: Writing Decoupled Live Playwright Tests
1. **Setup**: Use `live-fixture` to automatically receive `page` and `theme`:
   ```typescript
   import { test, expect } from '../../helpers/live-fixture';
   ```
2. **Deterministic Waiting**:
   - Never use arbitrary `sleep` or `waitForTimeout`.
   - Always await API network responses when an action mutates backend data:
     ```typescript
     await Promise.all([
       page.waitForResponse(r => r.url().includes('/api/{plugin}') && r.status() === 200),
       actionButton.click(),
     ]);
     ```
3. **Verify Both UI & State**:
   - Verify the UI reflects the change (text, counters, badges, disabled states).
   - Ensure errors display user-friendly feedback rather than unhandled rejections.

### Phase 4: Execution & Debugging
```powershell
# Run the plugin's complete suite
npx playwright test tests/e2e/plugins/{plugin-slug}

# Run with headed browser and slow-motion for visual inspection
npx playwright test tests/e2e/plugins/{plugin-slug}/01-primary-flow.live.spec.ts --headed --slowmo=400

# Inspect trace logs if any test fails
npx playwright show-trace test-results/.../trace.zip
```

### Phase 5: Verification & Regression Checks
1. Execute the entire plugin suite to guarantee all scenarios pass sequentially (`workers: 1`).
2. Run regression checks on dependent or downstream plugins.
3. Document results and scenario counts in the project walkthrough and plan artifacts.
