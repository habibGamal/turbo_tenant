# CMS pages

## Overview

Static content pages such as About, Terms, Privacy or Delivery FAQ, written by the store admin in a rich-text editor in English and Arabic and shown at `/pages/<slug>`.

**Status:** live · **Plugin:** `cms-pages` ([plugin.json](../../app/Plugins/CmsPages/plugin.json)) · **Depends on:** store-settings · **Related:** [Store settings](store-settings.md). Older guide: [CUSTOM_PAGES_GUIDE.md](../CUSTOM_PAGES_GUIDE.md) (describes an earlier implementation)

## How to use

### Store admin

**المحتوى والتسويق › صفحات CMS** (Content & marketing › CMS pages): create a page with title/title_ar, slug (letters, digits, `-`, `_`), content and content_ar in the rich editor, and an active flag. Link to it from banners or social/footer links as `/pages/<slug>`.

### Customer

Open `/pages/<slug>`; the page shows the content in the current language.

## How it works

1. `GET /api/cms-pages` lists active pages; `GET /api/cms-pages/{slug}` returns one active page ([`CmsPagesApiController`](../../app/Plugins/CmsPages/src/Http/Controllers/Api/CmsPagesApiController.php), [`Page`](../../app/Plugins/CmsPages/src/Models/Page.php)).
2. The storefront renders the HTML with `dangerouslySetInnerHTML` ([DefaultCmsPages.tsx](../../resources/js/plugins/cms-pages/components/DefaultCmsPages.tsx), themed override [ThemedCmsPages.tsx](../../resources/js/themes/default/plugins/cms-pages/ThemedCmsPages.tsx)). The content is trusted admin input and is not sanitized on the client.
3. Admin: [`PageResource`](../../app/Plugins/CmsPages/src/Filament/Resources/Pages/PageResource.php) with [`PageForm`](../../app/Plugins/CmsPages/src/Filament/Resources/Pages/Schemas/PageForm.php) (Filament `RichEditor`).

## Configuration

No settings. [`PageSeeder`](../../database/seeders/PageSeeder.php) has sample pages.

## Testing

- Automated: [CmsPagesTest](../../tests/Feature/CmsPagesTest.php), [CmsPagesUxTest](../../tests/Feature/CmsPagesUxTest.php).
- Browser: [cms-pages.live.spec.ts](../../tests/e2e/plugins/cms-pages.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Page 404 | Inactive, slug mismatch, or plugin inactive | Check slug and active flag |
| Formatting lost | Pasted markup not supported by the editor | Re-format in the editor |

## History

- 2026-06: custom pages guide.
- 2025-11: CMS pages.
