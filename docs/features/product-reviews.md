# Product reviews

## Overview

Customers (signed in or not) rate products 1–5 with a comment and optional photos; admins approve and publish them; product pages show approved reviews and the rating.

**Status:** live · **Plugin:** `product-reviews` ([plugin.json](../../app/Plugins/ProductReviews/plugin.json)) · **Depends on:** catalog-products · **Related:** [Catalog](catalog.md)

## How to use

### Customer

1. On a product page choose **Write a Review**, pick a star rating, write a comment (≤ 2000 characters), optionally attach images (each ≤ 2 MB). Visitors also enter a name (email optional).
2. You see "Review submitted successfully and is awaiting approval." unless the store auto-approves reviews.

### Store admin

**العملاء والتقييمات › التقييمات والآراء** (Customers & reviews › Reviews): filter by approval, approve/unapprove with the row actions, toggle **published** in the table. A dashboard stat shows pending reviews.

## How it works

1. `GET /api/products/{product}/reviews`: approved **and** published reviews, newest first, 10 per page ([`ProductReviewsApiController`](../../app/Plugins/ProductReviews/src/Http/Controllers/Api/ProductReviewsApiController.php)).
2. `POST` same path: [`StoreReviewRequest`](../../app/Plugins/ProductReviews/src/Http/Requests/StoreReviewRequest.php) validates; images saved by [`ReviewImageStorageService`](../../app/Plugins/ProductReviews/src/Services/ReviewImageStorageService.php); `is_approved`/`is_published` both set from the `product-reviews.auto_approve` setting.
3. [`ProductRatingContributor`](../../app/Plugins/ProductReviews/src/Contributors/ProductRatingContributor.php) (tag `catalog.product.details`) adds rating data to product details.
4. Admin: [`ReviewResource`](../../app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php).

Data: `reviews` (`product_id`, `user_id` or `guest_name`/`guest_email`, `rating`, `comment`, `images`, `is_approved`, `is_published`).

## Configuration

| Setting | Effect | Default |
| --- | --- | --- |
| `product-reviews.auto_approve` | Publish new reviews immediately | false |

There is no admin field for this setting; set it with `SettingService::set('product-reviews.auto_approve', true)` in tinker for the tenant.

## Testing

- Automated: [ProductReviewsTest](../../tests/Feature/ProductReviewsTest.php), [ReviewUxTest](../../tests/Feature/Filament/Resources/ReviewUxTest.php).
- Browser: [product-reviews.live.spec.ts](../../tests/e2e/plugins/product-reviews.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Review not visible | Not approved or not published | Approve and publish in admin |
| Upload rejected | File > 2 MB or not an image | Smaller image |
| Spam reviews | Endpoint is public without rate limit or captcha | Moderate; consider adding throttling |

## History

- 2025-11: reviews with moderation.
