# AGENTS.md

## Project Overview

**Turbo Tenant** is a multi-tenant e-commerce platform built with a Laravel 12 plugin microkernel and a headless React storefront:

- **Backend:** PHP `^8.3`, Laravel `^12.0`, FilamentPHP `^4.0` (Arabic `/admin` store panel and `/central` platform panel), Sanctum `^4.0`, Socialite `^5.23`, database-per-tenant multi-tenancy via `stancl/tenancy`.
- **Storefront SPA:** React 18 (`^18.2.0`), TypeScript (`^5.0.2`, strict mode), Vite 6, Tailwind CSS v4 (`@tailwindcss/vite`), React Query v5, React Router v6, Radix UI / shadcn primitives, `i18next`.
- **Plugin kernel:** Host platform under `app/Core/Plugins` plus 18 feature plugins under `app/Plugins/*` (plus matching frontend plugins under `resources/js/plugins/*` and theme overrides under `resources/js/themes/*`).
- **Arabic default UI** (`ar` default and fallback in `resources/js/i18n.ts`; Arabic Filament panels). **Default currency:** `EGP` (`resources/js/lib/money.ts`).

## Commands

| Task | Command |
|---|---|
| Quality gate (Pint -> Rector -> PHPStan -> Pest) | `composer review` |
| Fix code style (Pint) | `composer pint` (or `./vendor/bin/pint`) |
| Automated refactoring (Rector) | `composer rector` (or `./vendor/bin/rector process --ansi`) |
| Static analysis (PHPStan / Larastan level 5) | `composer phpstan` (or `./vendor/bin/phpstan analyse`) |
| Run PHP tests (Pest 4, parallel) | `composer pest` (or `php artisan test --parallel`) |
| Run security tests only | `php artisan test --group=security` |
| Type check + build frontend SPA | `npm run build` (`tsc && vite build`) |
| Dev (server, queue, logs, Vite) | `composer dev` |
| Fast local server with OPcache | `php artisan serve:fast` |
| Live E2E suite (Playwright, `e2e.localhost`) | `npm run test:e2e` |
| Auth E2E suite (Playwright) | `npm run test:e2e:auth` |
| Refresh plugin manifest cache | `php artisan plugin:discover` |
| Run plugin tenant migrations | `php artisan tenants:plugin:migrate --plugin=<id>` |
| Validate docs & links | `bash scripts/docs-check.sh` |
| Validate docs + build VitePress site | `bash scripts/docs-check.sh --build` |

## Architecture & Conventions

- PRs target `refactoring` (never `master`).
- **PHP rules:** `declare(strict_types=1);`, Pint rules in `pint.json`, Rector sets in `rector.php`, PHPStan level 5 on `app/` (`phpstan.neon`). `env()` is restricted to `config/`; `die`/`dd`/`dump`/`ray` are banned in `app/` (`tests/Feature/ArchTest.php`). Migration stubs omit `down()`.
- **Plugin boundaries (`tests/Feature/Architecture/PluginBoundariesArchTest.php`):** Keep controllers thin (FormRequests + `ApiResponse`), gate tenant routes with `PluginRoutes::tenant('<id>')`, depend only on host/core and `plugin.json` `dependencies`, collaborate through contracts/tags and `OrderPipelineRegistrar` / domain events, and never write another plugin's tables directly. Do not expand `tests/Feature/Architecture/boundary-allowlist.php`. Plugin PHP namespaces use lower-case `src/` (`App\Plugins\<Name>\src\...`).
- **Frontend & UI conventions:**
  - Keep shared TypeScript interfaces in `resources/js/types/` (`tsconfig.json` `@/*` -> `resources/js/*`).
  - Storefront strings go through `useTranslation()` and keys in both `resources/js/locales/ar/translation.json` and `resources/js/locales/en/translation.json` (never inline bilingual ternaries).
  - Support RTL/LTR via Tailwind `rtl:`/`ltr:` and logical spacing (`ms-`, `me-`, `ps-`, `pe-`), include `dark:` variants, design mobile-first, and format prices via `useFormatMoney()` / `formatMoney()` (`resources/js/lib/money.ts`).
  - Add `data-testid` attributes (see `docs/dev/themes.md`) and update `tests/e2e/themes/` contracts when adding storefront interaction points.
- **Testing:** Pest uses SQLite in-memory/per-process tenant files (`phpunit.xml`, `tests/TestCase.php`). Never mock HTTP/UI state inside `tests/e2e/`; follow `.claude/skills/e2e-test/SKILL.md`.
- **Do not hand-edit:** `bootstrap/cache/*.php`, `public/build`, `vendor/`, `node_modules/`.

## Documentation

- `docs/` (VitePress) is the single source of truth: Arabic store admin guide at `/` (`start/`, `guide/`, `admin/`, `integrations/`) and English developer handbook under `docs/dev/` (with `docs/dev/ops/`). Follow `docs/dev/style-guide.md`.
- Any PR that changes behaviour, settings, an API, or deployment must update the matching `docs/` page in the same PR and add a line to `CHANGELOG.md`.
- Only these `.md` locations are allowed: `docs/**` (excluding `docs/node_modules`), `.workspace/**`, `README.md`, `CHANGELOG.md`, `AGENTS.md`, `CLAUDE.md`, and `.claude/**`.
- Run `bash scripts/docs-check.sh` (or `bash scripts/docs-check.sh --build` for the full VitePress build) before pushing.
- The docs theme in `docs/.vitepress/theme/` is shared by the POS, website and platform docs and is edited only in the POS repo. See `docs/dev/style-guide.md` ("Shared theme") and `bash scripts/docs-theme.sh`.

## Workspace

All drafts, plans, specs, tickets, ADRs, research, and handoffs go in `.workspace/` (see `.workspace/index.md`), never in the repo root or `docs/`.

## Agent skills

- Issue tracker -> `.workspace/agents/issue-tracker.md`
- Domain docs (glossary, ADRs) -> `.workspace/agents/domain.md`
- Plugin scaffolding -> `.claude/skills/plugin-builder/SKILL.md`
- Inertia-to-API plugin migration -> `.claude/skills/plugin-inertia-to-api/SKILL.md`
- Live Playwright E2E tests -> `.claude/skills/e2e-test/SKILL.md`
