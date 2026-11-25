# Favorites

## Overview

Customers mark products as favorites with a heart button and see them on a favorites page. Favorites are stored in the browser, not on the server.

**Status:** live · **Plugin:** `customer-favorites` ([plugin.json](../../../app/Plugins/CustomerFavorites/plugin.json)) · **Depends on:** catalog-products · **Related:** [Catalog](catalog.md)

## How to use

### Customer

1. Select **Add to favorites** (heart) on a product card or page; select again to **Remove from favorites**.
2. Open **My Favorites** (`/favorites`) to see them; "No favorites yet" when empty.

### Rules customers notice

- Favorites live in this browser only (`localStorage` key `favorites`): they are not shared across devices or accounts, and clearing site data removes them.
- Deactivated products disappear from the list.

## How it works

1. The SPA keeps an array of product ids in `localStorage` ([useFavoritesStorage.ts](../../../resources/js/plugins/customer-favorites/api/useFavoritesStorage.ts)).
2. The favorites page calls `GET /api/customer-favorites?ids=1,2,3`; [`CustomerFavoritesApiController::index`](../../../app/Plugins/CustomerFavorites/src/Http/Controllers/Api/CustomerFavoritesApiController.php) returns active products with prices and category. The `rating` field in this response is a fixed `4.5`, not the product's review average.
3. `POST /api/customer-favorites/sync` validates an id list and echoes it back; nothing is stored server-side.

## Configuration

No settings. The plugin config has an `enabled` flag (default true); when false the endpoint returns `enabled: false`.

## Testing

- Automated: [CustomerFavoritesTest](../../../tests/Feature/CustomerFavoritesTest.php).
- Browser: [customer-favorites.live.spec.ts](../../../tests/e2e/plugins/customer-favorites.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Favorites gone on another phone | Browser-only storage | Expected; server-side storage would be a new feature |
| All favorites show 4.5 stars | Hard-coded rating in the API | Use the reviews rating (code change) |

## History

- 2026-02: favorites page refinements.
- 2025-11: initial favorites.
