# Coding Standards

Real conventions observed in `AGENTS.md`, `pint.json`, `phpstan.neon`, `rector.php`, `composer.json`, `package.json`, `tsconfig.json`, and architecture tests:

## PHP and Laravel

- **Quality gate:** `composer review` runs Pint, Rector, PHPStan, and Pest (`./vendor/bin/pint`, `./vendor/bin/rector process --ansi`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pest --parallel`).
- **Strict typing and style (`pint.json`):** `declare(strict_types=1);`, `final` classes where enforced, strict comparisons (`strict_comparison`), `DateTimeImmutable` (`date_time_immutable`), `mb_*` string functions, global namespace imports for classes/constants/functions, and ordered class elements. `tests/TestCase.php` is excluded in `pint.json`.
- **Rector (`rector.php`):** PHP sets plus `deadCode`, `codeQuality`, `typeDeclarations`, `privatization`, `earlyReturn`, and `strictBooleans` across `app/`, `bootstrap/app.php`, `database/`, and `public/`.
- **Static analysis (`phpstan.neon`):** Larastan level 5 on `app/`.
- **Architecture rules (`tests/Feature/ArchTest.php`):**
  - `App\Casts\*Cast` extend `CastsAttributes`, `App\Observers\*Observer`, `App\Policies\*Policy`, `App\Services\*Service`.
  - `env()` is allowed only in `config/` files.
  - `die`, `dd`, `dump`, and `ray` are forbidden in `App`.
- **Migrations and helpers:** custom migration stubs omit `down()`; global helpers live in `app/Helpers.php` (composer `files` autoload).

## Plugin Microkernel Boundaries

Enforced by `tests/Feature/Architecture/PluginBoundariesArchTest.php`:

- Controllers stay thin; request validation belongs in `FormRequest` classes and responses use `App\Http\Responses\ApiResponse`.
- A plugin in `app/Plugins/<Name>` may depend only on host/core code and plugins listed in its own `plugin.json` `dependencies`.
- Core namespaces (`App\Models`, `App\Services`, `App\Core`, `App\Http`, `App\Filament`, etc.) must not reference `App\Plugins` except entries explicitly listed in `tests/Feature/Architecture/boundary-allowlist.php` (which may only shrink).
- Cross-plugin collaboration uses contracts/tags resolved via `PluginContributions::active(...)` and `OrderPipelineRegistrar` in `bootForTenant()`; never write another plugin's tables directly.
- Tenant routes use `PluginRoutes::tenant('<plugin-id>')` (or `PluginRoutes::tenantUngated()` only for webhooks/POS callbacks that must settle after deactivation).
- Plugin namespaces under `app/Plugins/<Name>/` use lower-case `src/` (`App\Plugins\<Name>\src\...`).

## React, TypeScript, and Styling

- **Type checking and build (`package.json`, `tsconfig.json`):** `npm run build` runs `tsc && vite build`. TypeScript runs with `"strict": true` over `resources/js/**/*.{ts,tsx,d.ts}`, with `@/*` mapped to `./resources/js/*`. Shared frontend types live under `resources/js/types/`.
- **Theme decoupling:** plugin slots register default components in `resources/js/plugins/<id>/`; themes override slots in `resources/js/themes/<theme>/plugins/<id>/`. Avoid hardcoding UI selectors in Playwright tests; expose `data-testid` attributes documented in `docs/themes.md` and read them via `tests/e2e/themes/`.
- **Localization (`resources/js/i18n.ts`):** `ar` is default/fallback (`en` secondary); HTML `dir` and `lang` switch dynamically. Store all storefront copy in both `resources/js/locales/ar/translation.json` and `resources/js/locales/en/translation.json`, read with `useTranslation()` (`t('key')`), and never write hardcoded language ternaries (`lang === 'ar' ? ... : ...`) inside components.
- **Formatting and styling:** use Tailwind `rtl:`/`ltr:` variants and logical spacing (`ms-`, `me-`, `ps-`, `pe-`), include `dark:` variants, build mobile-first layouts, and format money through `useFormatMoney()` / `formatMoney()` in `resources/js/lib/money.ts` (`EGP` default when store currency is unset).

## Testing

- **Unit, Feature, Browser (`phpunit.xml`):** Pest 4 runs with SQLite `:memory:` config (`DB_CONNECTION=sqlite`); `tests/TestCase.php` isolates per-process tenant SQLite files under `database/testing/`.
- **Live Playwright E2E (`tests/e2e/`):** `npm run test:e2e` and `npm run test:e2e:auth` run against a live local `e2e.localhost` tenant with `workers: 1`.
