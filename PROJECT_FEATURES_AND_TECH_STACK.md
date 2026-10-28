# Turbo Tenant — Project Features & Technology Stack

> **Current State Overview**  
> **Turbo Tenant** is an enterprise-grade, multi-tenant e-commerce platform designed primarily for restaurant chains, ghost kitchens, and retail brands. It is engineered with a **Microkernel Host Architecture**, **database-per-tenant isolation**, a decoupled **Headless React Storefront**, and a modern **FilamentPHP 4.x Backoffice**.

---

## 🛠️ Technology Stack

### 1. Backend Core & Infrastructure
- **PHP**: `^8.3` (Strict Types, Constructor Property Promotion, Enums, Readonly Classes)
- **Framework**: **Laravel 12.x** (`laravel/framework: ^12.0`)
- **Multi-Tenancy Engine**: **Stancl Tenancy** (`stancl/tenancy`)
  - Automated tenant database creation, migration isolation, and domain/subdomain tenant identification
  - Tenant filesystem separation & tenant-aware configuration
- **Admin Panel & Backoffice**: **FilamentPHP 4.x** (`filament/filament: ^4.0`)
  - Single Page Application (SPA) mode
  - Two-Factor / Multi-Factor Authentication (MFA)
  - Custom branded panels for Central Platform Admin and Tenant Backoffices
- **Authentication & Security**:
  - **Laravel Sanctum 4.x** (`laravel/sanctum: ^4.0`) for SPA and API token authentication
  - **Laravel Socialite 5.x** (`laravel/socialite: ^5.23`) for third-party OAuth (Google Sign-In)
  - Role-based permissions and isolated tenant user tables
- **Media & Image Processing**:
  - **Spatie Image 3.x** (`spatie/image: ^3.8`) for automated WebP conversion and responsive thumbnail generation
- **Push Notification Infrastructure**:
  - **Laravel Notification Channels - Expo** (`laravel-notification-channels/expo: ^2.1`) for push notifications to mobile/PWA users
- **Database & Queue**:
  - **MySQL / PostgreSQL / SQLite** support with dynamic tenant database switching
  - Laravel Queue workers (`database`/`redis`) for asynchronous jobs (POS order dispatch, bulk push notifications)

### 2. Frontend & Storefront Architecture
- **Framework & Runtime**: **React 18.2** (`react`, `react-dom`) with **TypeScript 5.x** (`typescript: ^5.0.2`)
- **Build System & Bundler**: **Vite 6.x** (`vite: ^6.3.6`) with `@vitejs/plugin-react` and `laravel-vite-plugin`
- **Routing**: **React Router DOM 6.x** (`react-router-dom: ^6.30.6`)
- **Server State & Data Fetching**: **TanStack React Query v5** (`@tanstack/react-query: ^5.104.3`) for caching, optimistic updates, and background re-validation
- **Styling & Design System**:
  - **Tailwind CSS v4** (`tailwindcss: ^4.1.17`, `@tailwindcss/vite: ^4.1.10`)
  - **Radix UI Primitives** (Dialog, Dropdown Menu, Popover, Select, Tabs, Scroll Area, Avatar, Slider, etc.)
  - **Shadcn UI** component design architecture
  - Dynamic CSS variables system driven by tenant theme settings (`theme.css`)
- **Icons & UI Utilities**:
  - **Lucide React** (`lucide-react: ^0.553.0`)
  - **Embla Carousel** (`embla-carousel-react: ^8.6.0`, `embla-carousel-autoplay: ^8.6.0`)
  - **clsx** & **tailwind-merge** (`class-variance-authority: ^0.7.1`)
  - **React Phone Number Input** (`react-phone-number-input: ^3.4.14`)
- **Internationalization (i18n)**:
  - **i18next** (`i18next: ^25.6.3`, `react-i18next: ^16.3.4`, `i18next-browser-languagedetector: ^8.2.0`)
  - Full bilingual support (**Arabic & English**) with dynamic RTL/LTR document direction switching
- **Marketing & Tracking**:
  - **React Facebook Pixel** (`react-facebook-pixel: ^1.0.4`)

### 3. Testing & Code Quality Suite
- **Testing**: **PEST 4.x** (`pestphp/pest: ^4.1`) with browser testing (`pest-plugin-browser`), Livewire testing, and Laravel test helpers
- **End-to-End (E2E) Testing**: **Playwright** (`playwright: ^1.55.0`)
- **Static Analysis**: **Larastan / PHPStan Level 5+** (`larastan/larastan: ^3.0`)
- **Code Style & Formatting**: **Laravel Pint** (`laravel/pint: ^1.13`)
- **Automated Refactoring**: **Rector** (`rector/rector: ^2.0`)
- **Profiling & Debugging**: **Laravel Debugbar** (`barryvdh/laravel-debugbar: ^3.15`) and **Laravel Pail** (`laravel/pail: ^1.2.2`)

---

## 🏛️ Architecture & Platform Design

### 1. Multi-Tenant Microkernel Architecture
```
                         ┌────────────────────────┐
                         │   Central Host App     │
                         │ (Tenancy & Onboarding) │
                         └───────────┬────────────┘
                                     │
           ┌─────────────────────────┴─────────────────────────┐
           ▼                                                   ▼
┌──────────────────────┐                           ┌──────────────────────┐
│   Tenant: Store A    │                           │   Tenant: Store B    │
│  (Database: tenant_a)│                           │  (Database: tenant_b)│
├──────────────────────┤                           ├──────────────────────┤
│  Active Plugins:     │                           │  Active Plugins:     │
│  - CatalogProducts   │                           │  - CatalogProducts   │
│  - CartEngine        │                           │  - CartEngine        │
│  - CouponsPromotions │                           │  - PosIntegration    │
│  - PosIntegration    │                           │  - HeroBanners       │
└──────────────────────┘                           └──────────────────────┘
```

- **Database-Per-Tenant Isolation**: Complete database and file storage segregation.
- **Dynamic Plugin Booting**: The host kernel loads only the plugins that the active tenant has subscribed to or enabled.
- **Tenant Lifecycle**: Central admin can provision new tenants, assign custom domains/subdomains, and control active tenant states.
- **Microkernel Extension Slot Registry**: The React frontend features a plug-and-play slot registry (`PluginSlot.tsx` and `slot-registry.ts`) allowing plugins to inject React widgets into storefront pages without hardcoded dependencies.
- **Multi-Theme System**: Multi-theme structure under `resources/js/themes/` allowing per-tenant UI layouts, color configurations, and custom component overrides while falling back to default components.

---

## 📦 Installed Modular Plugins (Current State)

The platform is structured into **17 modular plugins** located in `app/Plugins/`, each containing its own database migrations, Filament admin resources, domain models, services, API controllers, and React UI components:

### 1. Catalog, Products & Taxonomy (`catalog-products`)
- **Hierarchical Taxonomy**: Categories, Subcategories, and Showcase Sections.
- **Product Variants**: Size, preparation style, and variant pricing.
- **Extra Options & Addons**: Option groups with custom selection rules (e.g., minimum required selections, maximum allowed selections).
- **Weight / Unit Pricing**: Weight options and granular unit price adjustments.
- **Bilingual Content**: Native Arabic & English fields for titles, descriptions, and option labels.
- **Search & Filtering API**: Full-text catalog search and category filtering endpoints.

### 2. Cart & Calculations Engine (`cart-engine`)
- **Dual-State Cart**: Seamless database persistence for authenticated users and cookie/session synchronization for guests.
- **Dynamic Pricing Calculator**: Live calculation of base prices, variant differences, extra option additions, taxes, and discounts.
- **Cart Sync Pipeline**: Merges guest cart items with user cart upon login.

### 3. Order Management & Checkout Core (`order-management`)
- **Order Placement Pipeline**: Validates inventory, prices, addresses, delivery fees, and coupons before order creation.
- **State Machine Workflow**: Managed status transitions (`pending` → `confirmed` → `preparing` → `out_for_delivery` → `delivered` → `cancelled`).
- **Customer Order Tracking**: Real-time order progress monitoring for customers.
- **Filament Admin Order Management**: Order status updates, customer details view, and receipt printing.

### 4. Guest Checkout & Order Tracking (`guest-checkout`)
- **Account-Free Ordering**: Allows customers to place orders with just their phone number and name.
- **Session-Based Guest Profiles**: Tracks guest order history via secure session keys and guest tokens.
- **Guest Admin Management**: Dedicated Filament resource for viewing guest users and their order activities.

### 5. Coupons & Promotional Discounts (`coupons-promotions`)
- **Discount Types**: Percentage-based or fixed monetary discounts.
- **Advanced Conditions Engine**:
  - Minimum and maximum order subtotal thresholds.
  - Category-specific and product-specific restrictions.
  - Delivery location filters (specific governorates or areas).
  - First-order-only validation.
  - User-specific exclusivity.
  - Time-based constraints (valid days of the week and active hour windows).
- **Usage Limits**: Max global usage, per-user usage limits, and real-time consumption tracking.

### 6. Locations, Branches & Delivery Areas (`locations-delivery`)
- **Geographic Hierarchy**: Governorates → Areas → Branches.
- **Dynamic Delivery Fees**: Per-area delivery charges and minimum order amounts.
- **Fulfillment Modes**: Support for home delivery and in-store branch pickup.
- **Customer Address Book**: Saved addresses with area mapping and delivery notes.

### 7. Payment Processing & Gateways (`payment-processing`)
- **Cash on Delivery (COD)**: Configurable cash-on-delivery handling with optional COD service fee pipeline.
- **Online Payment Gateways**:
  - **Paymob** integration (card payments, mobile wallets).
  - **Kashier** payment gateway integration.
- **Webhook Processing**: Secure webhook endpoints for asynchronous payment status verification.

### 8. Point of Sale (POS) Integration (`pos-integration`)
- **POS API Client**: Abstracted client for external POS systems (Loyverse, Foodics, etc.).
- **Bi-Directional Sync**:
  - Product POS mapping with barcode/SKU resolution.
  - POS catalog and price importer.
- **Background Dispatch**: Asynchronous dispatch of confirmed online orders directly to the kitchen/POS terminal via Laravel queued jobs.

### 9. Combo Meals & Packages (`combo-packages`)
- **Meal Bundles**: Create packages with multiple dishes at special bundle prices.
- **Conditional Group Selection**:
  - `all`: Fixed included items.
  - `choose_one`: Customer picks 1 choice among options.
  - `choose_multiple`: Customer selects up to a specified minimum/maximum quantity.
- **Price Adjustments**: Add extra charges for premium variant selections inside packages.

### 10. Push & Bulk Notifications (`push-notifications`)
- **Expo Push Notifications**: Delivers push notifications to mobile and PWA clients via Expo service.
- **Automated Order Alerts**: Automatic notification triggers upon order status changes.
- **Bulk Notification Campaign Manager**: Filament admin tool to schedule or send targeted promotional announcements to registered users.

### 11. Customer Reviews & Ratings (`product-reviews`)
- **Rating System**: 1-5 star ratings with written customer feedback.
- **Moderation Workflow**: Filament admin interface to approve, reject, or hide customer reviews before publishing.

### 12. Hero Banners & Promotional Carousel (`hero-banners`)
- **Storefront Carousel**: High-impact promotional sliders with auto-play capabilities.
- **Click Actions**: Deep links to specific products, categories, or external promotional pages.

### 13. Customer Favorites & Wishlist (`customer-favorites`)
- **Wishlist Management**: Instant one-click toggle to save favorite products.
- **Personalized List**: Dedicated favorites view in storefront for quick re-ordering.

### 14. CMS Static Pages (`cms-pages`)
- **Custom Content Pages**: Visual page builder in Filament for static pages (About Us, Terms of Service, Privacy Policy, Delivery FAQs).
- **SEO Ready**: Page slugs and meta content.

### 15. Store Settings & Configuration (`store-settings`)
- **Branding Controls**: Custom site logos, SVG logos, favicons, and placeholder images.
- **Business Details**: Multi-lingual store names (EN/AR), descriptions, contact email, phone, and physical address.
- **Social Media Links**: Facebook, Instagram, Twitter/X, and custom links.
- **Financial Controls**: Currency selection (e.g., EGP, SAR, USD), COD fees, and toggleable online payments.

### 16. Media Optimizer (`media-optimizer`) *(Core Plugin)*
- **Automated WebP Conversion**: Converts all uploaded catalog and banner imagery into optimized WebP formats.
- **Responsive Resolutions**: Generates high-res (`1920w`), mobile (`480w`), and low-quality blur placeholders (`20w`).

### 17. Sample Greeting Feature (`sample-greeting`)
- **Microkernel Reference Plugin**: Serves as a reference implementation demonstrating plugin contracts, hooks, and lifecycle events.

---

## 🚀 Storefront & Tenant API Highlights

- **`/api/tenant/bootstrap`**: Single-call bootstrapping endpoint returning active tenant metadata, enabled plugins, current user state, cart item count, theme configurations, and store branding.
- **`/api/tenant/auth/*`**: Complete Sanctum authentication flow (Register, Login, Logout, Fetch User).
- **Dynamic CSS (`/theme.css`)**: Injects tenant-specific color palette and font declarations dynamically at runtime.
- **PWA Ready (`/manifest.json`)**: Dynamic tenant-specific Progressive Web App web manifest.
