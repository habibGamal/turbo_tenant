# Custom Pages Guide

This document explains how custom pages are implemented in this project and how the page seeder works.

## Overview

Custom pages are stored in the `pages` table and managed from Filament. Public users can open each page by visiting a URL that includes the page `slug`.

Flow summary:

1. Admin creates/updates a page in Filament.
2. Data is saved in the `pages` table.
3. Public route `/pages/{slug}` loads the page.
4. `PageController` fetches an active record and returns an Inertia page.
5. The Inertia `Page` component renders the HTML content.

## Core Files

- `app/Models/Page.php`
- `app/Filament/Resources/Pages/PageResource.php`
- `app/Filament/Resources/Pages/Schemas/PageForm.php`
- `app/Filament/Resources/Pages/Tables/PagesTable.php`
- `app/Http/Controllers/PageController.php`
- `routes/tenant.php`
- `resources/js/themes/default/pages/Page.tsx`
- `database/seeders/PageSeeder.php`
- `database/migrations/tenant/2025_11_27_000000_create_pages_table.php`
- `database/migrations/tenant/2025_11_27_000001_add_arabic_columns_to_pages_table.php`

## Database Structure

The `pages` table contains:

- `id`
- `title`
- `title_ar` (nullable)
- `slug` (unique)
- `content` (long text)
- `content_ar` (nullable, long text)
- `is_active` (boolean, default `true`)
- `created_at`, `updated_at`

## Filament Resource Behavior

`PageResource` provides standard CRUD pages in Filament:

- index (list)
- create
- view
- edit

Form behavior (`PageForm`):

- Uses tabs for content fields.
- `title` is required.
- `slug` is required and unique.
- While creating, `slug` is auto-generated from `title` on title blur.
- `content` is required and edited with `RichEditor`.
- `is_active` defaults to `true`.

Table behavior (`PagesTable`):

- Search by `title` and `slug`.
- Show `is_active` as a boolean icon.
- Includes view/edit row actions and bulk delete.

## Public Page Rendering

Route definition:

```php
Route::get('/pages/{slug}', [App\Http\Controllers\PageController::class, 'show'])
    ->name('pages.show');
```

Controller behavior (`PageController@show`):

- Finds page by `slug`.
- Requires `is_active = true`.
- Returns `firstOrFail()` (404 when not found/inactive).
- Renders Inertia page `Page` and passes the `page` record.

Inertia page resolution:

- In `resources/js/app.tsx`, Inertia resolves pages from:
  `./themes/default/pages/${name}.tsx`
- So `Inertia::render('Page', ...)` resolves to:
  `resources/js/themes/default/pages/Page.tsx`

## Seeder: PageSeeder

`database/seeders/PageSeeder.php` seeds a predefined set of standard static pages such as:

- About Us
- Privacy Policy
- Return Policy
- Replacement Policy
- Delivery Policy
- Shipping Policy
- Terms of Service
- Contact Us

Seeding logic:

- Iterates over a pages array.
- Uses `Page::firstOrCreate(['slug' => $page['slug']], $page)`.
- This makes the seeder idempotent by `slug` (re-running will not duplicate rows with the same slug).

## Running the Seeder

You can seed page records directly with Artisan:

```bash
php artisan db:seed --class=PageSeeder
```

For tenant databases:

```bash
php artisan tenants:seed --class=PageSeeder
```

## Important Note

`DatabaseSeeder.php` currently does **not** call `PageSeeder` by default, so run `PageSeeder` explicitly or add it to your seeding flow.