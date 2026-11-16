---
layout: home

hero:
  name: Turbo Tenant
  text: Online ordering for restaurants, one app for many stores
  tagline: Technical handbook for developers and operators, and an Arabic guide for store admins.
  image:
    src: /logo.svg
    alt: Turbo Tenant
  actions:
    - theme: brand
      text: Technical handbook
      link: /handbook
    - theme: alt
      text: دليل المستخدم (عربي)
      link: /ar/
    - theme: alt
      text: Connect a POS
      link: /pos-website-link

features:
  - icon: 🏬
    title: Multi-tenant by domain
    details: Each store has its own domain and database (stancl/tenancy). The host picks the store; central and tenant data never mix.
    link: /architecture
    linkText: Architecture
  - icon: 🧩
    title: Plugin kernel
    details: Cart, checkout, payments, coupons, POS, Meta and more are per-store plugins with dependencies, migrations and contribution points.
    link: /features/plugin-marketplace
    linkText: Plugin marketplace
  - icon: 🔌
    title: Linked to the POS
    details: Orders go to the branch POS with an API key and an idempotency key; the POS sends status back with the integration token.
    link: /pos-website-link
    linkText: POS ↔ website
  - icon: ⚙️
    title: Queue workers
    details: POS delivery, images, push and Meta events run on the central database queue. One misconfigured variable silently breaks it.
    link: /queue-and-workers
    linkText: Queue & workers
  - icon: 💳
    title: Payments
    details: Paymob and Kashier with fail-closed, idempotent webhooks and an explicit payment state machine.
    link: /features/payments
    linkText: Online payments
  - icon: 📣
    title: Meta Pixel, CAPI & catalog
    details: Browser and server purchase events with shared ids, and a CSV/XML catalog feed with an Arabic override.
    link: /features/meta-marketing
    linkText: Meta marketing
---
