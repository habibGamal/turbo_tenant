# Phase 2: Catalog & Browse Experience

> **Focus:** Migrating catalog taxonomy, product details, homepage carousel, CMS pages, and favorites to decoupled REST APIs, headless hooks, fallback UI, and theme overrides.  
> **Target Path:** `.plan/replace_inertia_with_api/02_phase2_catalog_and_browse_experience.md`  

---

## 1. Objectives & Overview

Phase 2 replaces the server-rendered Inertia browsing pages (`HomePage.tsx`, `ProductShow.tsx`, `MenuPage.tsx`, `Page.tsx`, `Favorites.tsx`) with pure React client pages powered by TanStack Query and **Multi-Theme Inversion of Control**.

Key Plugins Migrated in this Phase:
- `catalog-products` (Core catalog taxonomy, products, variants, weights, extras, sections)
- `hero-banners` (Storefront visual banner/carousel)
- `cms-pages` (Static policy & content pages)
- `customer-favorites` (Customer wishlist)

---

## 2. Backend API Implementation Tasks

### 2.1 Complete CatalogProducts REST API (`app/Plugins/CatalogProducts/`)
Ensure complete JSON API coverage in `src/Http/Controllers/Api/`:
- `GET /api/categories` → Returns active categories with child counts and images.
- `GET /api/sections` → Returns active homepage sections with preloaded active products.
- `GET /api/products` → Paginated, filterable product list (by category, price, search term).
- `GET /api/products/{product}` → Detailed product payload (including variants, extra options, weight options, related products, and promotional products).
- `GET /api/menu` → Full menu categorization grouped for fast display.
- `GET /api/search` → Autocomplete search suggestions and full text search.

### 2.2 HeroBanners REST API (`app/Plugins/HeroBanners/`)
- **Route:** `GET /api/hero-banners`
- **Controller:** `App\Plugins\HeroBanners\src\Http\Controllers\Api\HeroBannerApiController`
- Returns sorted active slides (`title`, `image`, `link`, `sort_order`).

### 2.3 CmsPages REST API (`app/Plugins/CmsPages/`)
- **Route:** `GET /api/pages/{slug}`
- **Controller:** `App\Plugins\CmsPages\src\Http\Controllers\Api\PageApiController`
- Returns published CMS page data (`title`, `title_ar`, `slug`, `content`, `content_ar`).

### 2.4 CustomerFavorites REST API (`app/Plugins/CustomerFavorites/`)
- **Routes:**
  - `GET /api/favorites` (List customer favorite product IDs / objects)
  - `POST /api/favorites/{product}/toggle` (Add or remove favorite for authenticated user)
- **Controller:** `App\Plugins\CustomerFavorites\src\Http\Controllers\Api\FavoriteApiController`

---

## 3. Frontend Plugin Directory: Headless First & Fallbacks

Plugins live in `resources/js/plugins/` and supply **Headless Hooks** + neutral **Default Fallback Components**:

### 3.1 `resources/js/plugins/hero-banners/`
```
resources/js/plugins/hero-banners/
├── api/
│   └── useHeroSlides.ts              # Headless TanStack query for /api/hero-banners
├── components/
│   └── DefaultHeroSlider.tsx         # Clean, unstyled/minimal fallback component
├── types/
│   └── index.ts                      # HeroSlide interface
└── index.ts                          # Registers DefaultHeroSlider via registerPluginDefault('home.hero', DefaultHeroSlider)
```

### 3.2 `resources/js/plugins/catalog-products/`
```
resources/js/plugins/catalog-products/
├── api/
│   ├── useCategories.ts              # TanStack query for /api/categories
│   ├── useSections.ts                # TanStack query for /api/sections
│   ├── useProducts.ts                # TanStack query for /api/products
│   ├── useProduct.ts                 # TanStack query for /api/products/:id
│   └── useProductSearch.ts           # Query hook for search suggestions
├── components/
│   ├── DefaultProductCard.tsx        # Fallback card
│   └── DefaultProductOptions.tsx     # Fallback options selector
└── types/
    └── index.ts                      # Product, Category, Variant, Option interfaces
```

### 3.3 `resources/js/plugins/customer-favorites/`
```
resources/js/plugins/customer-favorites/
├── api/
│   ├── useFavorites.ts               # TanStack query for customer favorites
│   └── useToggleFavorite.ts          # TanStack mutation for toggling favorites
├── components/
│   └── DefaultFavoriteButton.tsx     # Minimal heart toggle button
└── types/
    └── index.ts
```

### 3.4 `resources/js/plugins/cms-pages/`
```
resources/js/plugins/cms-pages/
├── api/
│   └── useCmsPage.ts                 # TanStack query for /api/pages/:slug
├── components/
│   └── DefaultCmsPageView.tsx        # Sanitized HTML renderer
└── types/
    └── index.ts
```

---

## 4. Multi-Theme Custom Overrides (`resources/js/themes/`)

Themes are free to create custom presentations for any plugin without modifying the plugin itself:

```
resources/js/themes/
├── default/
│   ├── layouts/MainLayout.tsx
│   ├── pages/HomePage.tsx
│   ├── pages/ProductShowPage.tsx
│   └── plugins/
│       └── hero-banners/
│           ├── HeroSlider.tsx        # Default theme's custom Embla carousel with dots & badges
│           └── index.ts              # Calls registerThemeOverride('default', 'home.hero', HeroSlider)
│
└── modern/                           # Future theme example
    ├── layouts/MainLayout.tsx
    ├── pages/HomePage.tsx
    └── plugins/
        └── hero-banners/
            ├── HeroSplitGrid.tsx     # Modern theme's static split-grid hero (No carousel!)
            └── index.ts              # Calls registerThemeOverride('modern', 'home.hero', HeroSplitGrid)
```

### Theme `HomePage.tsx`
```tsx
export default function HomePage() {
  const { data: sections, isLoading } = useSections();

  return (
    <MainLayout>
      {/* 
        Resolves:
        - If theme is 'default' -> HeroSlider.tsx (Embla Carousel)
        - If theme is 'modern'  -> HeroSplitGrid.tsx (Grid Layout)
        - If uncustomized theme -> DefaultHeroSlider.tsx (Plugin Fallback)
        - If plugin disabled    -> null
      */}
      <PluginSlot name="home.hero" pluginId="hero-banners" />
      
      {isLoading ? <SectionSkeleton /> : <SectionList sections={sections} />}

      <PluginSlot name="home.sections.after" />
    </MainLayout>
  );
}
```

---

## 5. Verification & Testing

### 5.1 Automated Backend Tests
- `tests/Feature/Api/CatalogApiTest.php`: Test `/api/products/{id}`, `/api/categories`, `/api/sections`.
- `tests/Feature/Api/HeroBannersApiTest.php`: Test `/api/hero-banners`.
- `tests/Feature/Api/FavoritesApiTest.php`: Test favorite toggle and list.

### 5.2 Multi-Theme Verification
1. **Default Theme Check:** With `tenant.theme = 'default'`, open Homepage → Confirm Embla `HeroSlider` renders.
2. **Fallback Check:** Temporarily change theme to an uncustomized slug → Open Homepage → Confirm `DefaultHeroSlider` renders gracefully without layout breaks.
3. **Plugin Toggle Check:** Disable `hero-banners` for tenant → Confirm `<PluginSlot name="home.hero" />` renders `null`.
