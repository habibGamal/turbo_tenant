# Hero banners

## Overview

The rotating promotional carousel at the top of the storefront home page, with bilingual titles, badges and up to two call-to-action buttons.

**Status:** live · **Plugin:** `hero-banners` ([plugin.json](../../app/Plugins/HeroBanners/plugin.json)) · **Depends on:** none · **Related:** [Media optimizer](media-optimizer.md), [CMS pages](cms-pages.md)

## How to use

### Store admin

**المحتوى والتسويق › سلايدر** (Content & marketing › Slider): create a slide with title, subtitle and badge (EN/AR), primary CTA text/link, optional secondary CTA, image (optimized automatically), gradient, icon, sort order and active flag. Links can point to storefront paths such as `/products/12` or `/menu`, or to external URLs.

### Customer

Slides rotate on the home page (slot `home.hero`); buttons follow their links.

## How it works

1. `GET /api/hero-banners` (alias `/api/hero-sliders`) → [`HeroBannersApiController`](../../app/Plugins/HeroBanners/src/Http/Controllers/Api/HeroBannersApiController.php) → [`HeroBannerService::getActiveSliders`](../../app/Plugins/HeroBanners/src/Services/HeroBannerService.php): active slides ordered by `sort_order`, empty when the plugin config is disabled.
2. [`HeroBannersBootstrapContributor`](../../app/Plugins/HeroBanners/src/Bootstrap/HeroBannersBootstrapContributor.php) prefetches slides into the bootstrap response, so the home page renders without a second request.
3. Admin: [`HeroSliderResource`](../../app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php); data in `hero_sliders` ([`HeroSlider`](../../app/Plugins/HeroBanners/src/Models/HeroSlider.php)).

There is no scheduling (start/end dates); use the active flag.

## Configuration

No store settings. The plugin config ([`HeroBannersConfig`](../../app/Plugins/HeroBanners/src/DTOs/HeroBannersConfig.php)) has an `enabled` flag.

## Testing

- Automated: [HeroBannersTest](../../tests/Feature/HeroBannersTest.php), [HeroBannersUxTest](../../tests/Feature/HeroBannersUxTest.php).
- Browser: [hero-banners.live.spec.ts](../../tests/e2e/plugins/hero-banners.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Carousel empty | No active slides or plugin inactive | Add/activate slides; activate plugin |
| Slide image heavy | Variants not generated | See [Media optimizer](media-optimizer.md#troubleshooting) |

## History

- 2025-11: hero slider.
