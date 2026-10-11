# Phase 5 Migration Plan: Storefront & Engagement Plugins

> **Milestone:** M5  
> **Plugins:** `product-reviews`, `hero-banners`, `cms-pages`, `customer-favorites`  
> **Scope:** Social proof, homepage banners, CMS informational pages, and customer wishlists  
> **Target Path:** `.plan/migration_to_plugins/05_phase5_storefront_and_engagement_plugins.md`  

---

## 1. Objectives & Overview

Phase 5 decouples customer engagement and visual presentation layers. Each feature mounts onto the storefront via `<PluginSlot />` wrappers or dedicated page components:
1. **`product-reviews`**: Customer star ratings, text reviews, and admin approval moderation.
2. **`hero-banners`**: Visual promotional carousel on the homepage.
3. **`cms-pages`**: Custom informational content (About Us, Terms, Privacy Policy).
4. **`customer-favorites`**: Customer wishlists and saved meals.

---

## 2. Plugin 12: `product-reviews`

### 2.1 Manifest Specification (`app/Plugins/ProductReviews/plugin.json`)
```json
{
  "id": "product-reviews",
  "name": "Customer Reviews & Ratings",
  "version": "1.0.0",
  "category": "addon",
  "description": "Star ratings, text reviews, and admin moderation for menu items.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "catalog-products"
  ],
  "providers": [
    "App\\Plugins\\ProductReviews\\ProductReviewsServiceProvider"
  ],
  "filament": "App\\Plugins\\ProductReviews\\src\\Filament\\ProductReviewsFilamentPlugin"
}
```

### 2.2 Relocated Files & Slot
- **Model:** `Review.php` $\to$ `app/Plugins/ProductReviews/src/Models/Review.php`.
- **Controller:** `ReviewController.php` $\to$ `app/Plugins/ProductReviews/src/Http/Controllers/ReviewController.php`.
- **Migrations:** `2025_11_26_204750_create_reviews_table.php`.
- **Frontend Widget:** `ReviewForm.tsx` mounted via `<PluginSlot name="product-reviews" props={{ product }} />`.

---

## 3. Plugin 13: `hero-banners`

### 3.1 Manifest Specification (`app/Plugins/HeroBanners/plugin.json`)
```json
{
  "id": "hero-banners",
  "name": "Hero Banners & Carousel",
  "version": "1.0.0",
  "category": "storefront",
  "description": "Homepage visual promotional sliders and clickable call-to-actions.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [],
  "providers": [
    "App\\Plugins\\HeroBanners\\HeroBannersServiceProvider"
  ],
  "filament": "App\\Plugins\\HeroBanners\\src\\Filament\\HeroBannersFilamentPlugin"
}
```

### 3.2 Relocated Files & Slot
- **Model:** `HeroSlider.php` $\to$ `app/Plugins/HeroBanners/src/Models/HeroSlider.php`.
- **Filament Resource:** `HeroSliderResource.php` $\to$ `app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php`.
- **Migrations:** `2025_11_27_125210_create_hero_sliders_table.php`.
- **Frontend Widget:** `HeroSlider.tsx` mounted via `<PluginSlot name="homepage-hero-slider" />`.

---

## 4. Plugin 14: `cms-pages`

### 4.1 Manifest Specification (`app/Plugins/CmsPages/plugin.json`)
```json
{
  "id": "cms-pages",
  "name": "CMS Static Pages",
  "version": "1.0.0",
  "category": "storefront",
  "description": "Custom informational pages (About Us, Terms of Service, Privacy Policy) with Arabic & English content.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [],
  "providers": [
    "App\\Plugins\\CmsPages\\CmsPagesServiceProvider"
  ],
  "filament": "App\\Plugins\\CmsPages\\src\\Filament\\CmsPagesFilamentPlugin"
}
```

### 4.2 Relocated Files & Routes
- **Model:** `Page.php` $\to$ `app/Plugins/CmsPages/src/Models/Page.php`.
- **Filament Resource:** `Pages/` $\to$ `app/Plugins/CmsPages/src/Filament/Resources/Pages/`.
- **Controller & Route:** `PageController.php`, route `/pages/{slug}`.
- **Migrations:** `2025_11_27_000000_create_pages_table.php`, `2025_11_27_000001_add_arabic_columns_to_pages_table.php`.
- **Frontend View:** `Page.tsx` and dynamic footer links in `Footer.tsx` via `<PluginSlot name="footer-cms-links" />`.

---

## 5. Plugin 15: `customer-favorites`

### 5.1 Manifest Specification (`app/Plugins/CustomerFavorites/plugin.json`)
```json
{
  "id": "customer-favorites",
  "name": "Customer Favorites & Wishlist",
  "version": "1.0.0",
  "category": "storefront",
  "description": "Save favorite meals and quick-reorder bookmarked dishes.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "catalog-products"
  ],
  "providers": [
    "App\\Plugins\\CustomerFavorites\\CustomerFavoritesServiceProvider"
  ]
}
```

### 5.2 Relocated Files & Routes
- **Routes & Views:** Route `/favorites` loaded by `CustomerFavoritesServiceProvider`, rendering `Favorites.tsx`.
- **Slot Hook:** Heart icon on `ProductCard.tsx` mounts via `<PluginSlot name="product-favorite-button" props={{ product }} />`.

---

## 6. Phase 5 Verification Checklist
1. **Banner Mount:** Activate `hero-banners` $\to$ homepage displays promotional slider. Deactivate $\to$ slider disappears without console warnings.
2. **CMS Pages:** Create "About Us" page in Filament $\to$ navigate to `/pages/about-us` $\to$ page renders with localized Arabic and English content.
3. **Product Reviews:** Submit review $\to$ verify stored in `reviews` table and displayed on `ProductShow.tsx`.
4. **Favorites:** Click favorite icon $\to$ visit `/favorites` $\to$ saved dish is visible.
