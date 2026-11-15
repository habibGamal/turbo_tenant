# Discovery

Goal: build an accurate mental model of *this* repository before writing anything. Nothing about the
architecture is assumed; every component in the docs must trace back to something found here.

## 1. Run the inventory

```bash
python <skill>/scripts/discover.py <repo> --out <scratch>/discovery.md
python <skill>/scripts/discover.py <repo> --json --out <scratch>/discovery.json   # full lists
```

It reports languages, manifests and detected frameworks, runnable scripts and task-runner targets,
entry-point candidates, schema candidates, deployment assets, CI workflows, test configs, environment
variable names (declared in example env files, and read in code but undeclared), existing docs and the
git history shape (tags, conventional-commit usage, uncommitted paths). Keep scratch output outside the
repository or delete it afterwards.

The inventory is a reading list. Detection is by file convention, so verify each item and look for what it
misses (custom layouts, monorepo packages, generated code).

## 2. Read in this order

1. **Existing docs and instructions:** README, CONTRIBUTING, AGENTS/CLAUDE files, docs/, ADRs, runbooks, API
   specs. Note which are current and which describe plans or old implementations; check against code.
2. **Manifests and lockfiles:** runtime versions, frameworks, notable libraries (auth, queue, payments,
   search, storage). Each notable library usually implies a section in architecture or operations.
3. **Composition root / bootstrap:** where the app is assembled (service providers, DI container setup,
   `main`, app factory, module registry, router setup, middleware stack). This reveals the real structure.
4. **Entry points:** routes/URL maps, controllers/handlers, CLI commands, pages/screens, consumers,
   scheduled tasks, webhooks. These become the feature catalog and interfaces page.
5. **Domain and data:** models/entities, migrations/schema files, enums/state fields, repositories.
6. **Cross-cutting code:** auth, authorization policies, validation, error handling, caching, jobs, events,
   notifications, i18n, logging.
7. **Frontend (if any):** routing, state, API client, layouts/themes, build config.
8. **Tests:** what is tested shows intended behavior and edge cases; test names are good feature hints.
9. **Delivery:** CI workflows, Dockerfiles/compose, IaC, deploy scripts, Procfiles, supervisor/systemd units,
   web server config, `.env.example`.
10. **History:** `git log`, tags, merged branches, existing changelog: what changed recently and why.

## 3. Identify features

A feature is an outcome an actor can achieve, not a directory. Build the list from several angles and merge:

- user-facing screens and navigation menus (web, admin panel, mobile)
- route groups and API resources
- CLI commands and scheduled jobs (operator and automated features)
- modules/plugins/packages and their manifests
- event listeners and integrations (outbound effects are often features: "send order to POS")
- test files and test names
- existing docs and commit subjects (`feat(...)` scopes)

Name each feature in domain language, group by area, and note actors, entry points, status (live, behind a
flag, experimental, deprecated), and the main source files. Write this list into `features/README.md` before
writing any feature page so coverage is visible from the start.

## 4. Decide the documentation shape

- Reuse the existing docs engine (MkDocs, Docusaurus, VitePress, Sphinx, mdBook, wiki folder) and its nav
  config. Otherwise use plain Markdown under `docs/` with `docs/README.md` as the index.
- Start from the standard page set ([handbook-structure.md](handbook-structure.md)); merge pages for small
  projects, split them (e.g. `api/`, `features/<area>/`) for large ones.
- Fold valuable existing docs in by linking or moving them; mark superseded plans as historical rather than
  silently deleting them.
- Topics that do not apply (no database, no UI, library with no deployment) are declared not-applicable in
  `coverage.md` and `docs/handbook.json`, with a reason.

## Conditional topics

Look for these and document them only when present: multi-tenancy, plugin/module systems, feature flags,
queues and workers, schedulers, websockets/realtime, search indexes, file/media pipelines, payments,
notifications (email/SMS/push), i18n/RTL, offline/mobile clients, multi-region, data retention/GDPR,
rate limiting, background imports/exports, AI/LLM calls, analytics/tracking pixels.
