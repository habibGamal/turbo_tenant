# Plugin E2E Test Matrix Guide

This guide provides the discovery questions and structural checklist to derive an exhaustive E2E test matrix for any arbitrary plugin archetype.

---

## 1. Discovery Checklist: Uncovering All Aspects

When analyzing a plugin's domain:

### A. Data & Entity Variants
- Does the plugin support different entities, categories, or subtypes?
  - *Example (Catalog/Cart)*: Standard product vs. weighted product vs. variant option.
  - *Example (Delivery/Locations)*: Branch pickup vs. home delivery; different governorates/zones.
  - *Example (Payments)*: Cash on Delivery (COD) vs. online card gateway vs. wallet.
- How do identical vs. distinct configurations behave when interacted with repeatedly?
  - Do they aggregate?
  - Do they create separate line records?
  - Do they override previous values?

### B. Business Logic & Dynamic Computations
- What computational rules or formulas does the plugin execute?
  - *Example*: `(base_price + addon_price) * quantity`, or `delivery_fee * zone_rate`, or `percentage_discount`.
- Does the calculation update reactively in the UI when inputs change without a full page reload?
- Are currency symbols, tax breakdowns, or formatted units displayed correctly according to locale?

### C. State Mutations & Input Bounds
- What interactive controls modify state? (buttons, increment/decrement steppers, checkboxes, radio groups, selects, textareas).
- What are the boundary constraints?
  - **Lower Bounds**: Decrement buttons disabled at `1` or `0`; empty inputs disallow submission.
  - **Upper Bounds**: Max stock limits; max allowed length; maximum coupon threshold.
  - **Type Validations**: Invalid phone formats, non-numeric values, expired dates.

### D. Empty, Zero, and Error States
- What is displayed when the entity list is empty (0 items, 0 orders, 0 reviews, 0 notifications)?
  - Is there an empty state illustration?
  - Is there descriptive, user-friendly copy?
  - Is there a clear Call-to-Action (CTA) returning the user to a productive route (e.g. "Start Shopping")?
- How does the system handle backend validation or network failures?
  - Does it display user-friendly inline error alerts or toast messages instead of unhandled exceptions?

### E. Continuity & Persistence
- Does state survive a hard browser refresh (`F5` / `page.reload()`)?
  - Is it persisted in session cookies, local storage, or server-side DB?
- Does client-side Single Page Application (SPA) routing (e.g. navigating `/` -> `/details` -> `/cart` -> `/`) preserve uninterrupted continuity?

### F. Downstream & Cross-Plugin Contracts
- How does this plugin connect with the rest of the application ecosystem?
  - *Catalog* feeds into *Cart*.
  - *Cart* feeds into *Coupons* and *Checkout*.
  - *Checkout* triggers *PaymentProcessing* and creates *OrderManagement* records.
  - *Products* receive *ProductReviews* and *CustomerFavorites*.
- Are there route guards protecting downstream pages when prerequisites are missing? (e.g., navigating directly to `/checkout` with an empty cart should guard and redirect or display an empty state).

---

## 2. Standard Modular Spec Structure

For any plugin `{plugin-slug}`, organize specs into clean, isolated scenario files inside `tests/e2e/plugins/{plugin-slug}/`:

```
tests/e2e/plugins/{plugin-slug}/
├── 01-primary-flow.live.spec.ts                 # Main user journey
├── 02-configurations-and-options.live.spec.ts   # Subtypes, variants, filters
├── 03-domain-computations.live.spec.ts          # Calculations, math formulas, dynamic fees
├── 04-state-mutations.live.spec.ts              # Increments, updates, inline edits
├── 05-boundary-conditions.live.spec.ts          # Min/max bounds, disabled buttons, validations
├── 06-item-or-record-removal.live.spec.ts       # Deletions, unlinking, item purging
├── 07-empty-and-error-states.live.spec.ts       # Zero state rendering, CTA buttons, alerts
├── 08-persistence-and-routing.live.spec.ts      # Page reloads and SPA client transitions
└── 09-cross-plugin-integrations.live.spec.ts    # Downstream handoffs and route guards
```
