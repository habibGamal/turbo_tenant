# Media optimizer

## Overview

Converts images uploaded in the admin panel into WebP variants (1920 px, 480 px and a 20 px blur placeholder, quality 80) so the storefront loads small images on phones.

**Status:** live (core plugin, always active) · **Plugin:** `media-optimizer` ([plugin.json](../../app/Plugins/MediaOptimizer/plugin.json)) · **Related:** [Catalog](catalog.md), [Hero banners](hero-banners.md), [Combo packages](combo-packages.md). Performance notes: [storefront-performance.md](../storefront-performance.md)

## How to use

### Store admin

Upload images as usual in categories, products, packages and hero banners. Variants are produced in the background a few seconds later.

### Operator: convert existing images

```bash
php artisan media:migrate-variants --dry-run          # preview
php artisan media:migrate-variants --tenant=kofe       # one store
php artisan media:migrate-variants --cleanup-old       # also delete originals after successful conversion (irreversible)
```

## How it works

1. File upload fields call `->optimizeForWeb('<directory>')` (e.g. [ProductForm](../../app/Plugins/CatalogProducts/src/Filament/Resources/Products/Schemas/ProductForm.php)).
2. [`ImageOptimizerService::processUpload`](../../app/Plugins/MediaOptimizer/src/Services/ImageOptimizerService.php) stores a master and dispatches [`GenerateImageVariantsJob`](../../app/Plugins/MediaOptimizer/src/Jobs/GenerateImageVariantsJob.php) (3 tries) to write the variants on the tenant's public disk.
3. Sizes and quality come from [`MediaOptimizerConfig`](../../app/Plugins/MediaOptimizer/src/DTOs/MediaOptimizerConfig.php) (1920 / 480 / 20, quality 80).
4. [`MigrateMediaVariantsCommand`](../../app/Plugins/MediaOptimizer/src/Console/Commands/MigrateMediaVariantsCommand.php) walks existing tenant storage, generates variants synchronously and updates database records.

## Configuration

No settings. Requires a queue worker and a PHP image extension that can encode WebP (GD with WebP or Imagick). Core plugin: activated for every new store and cannot be deactivated.

## Testing

- Automated: [MediaOptimizerTest](../../tests/Feature/MediaOptimizerTest.php).
- Manual: upload a product image, wait for the worker, confirm three WebP files next to the master in `storage/tenant<id>/app/public/products/`.

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| No variants created | Worker not running or jobs in the tenant DB (see `DB_QUEUE_CONNECTION`) | Run worker; set queue connection |
| Job fails with an encoder error | No WebP support in GD/Imagick | Install a WebP-capable extension |
| Images missing after `--cleanup-old` | Originals deleted by design | Restore from backup |

## History

- 2026-10: media optimizer extracted as a core plugin with the migration command.
