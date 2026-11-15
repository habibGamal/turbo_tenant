# Laravel / React atlas adapter (optional)

Use only when the project is Laravel + React/TypeScript and either already has `docs/engineering-atlas/` or the user asks for the generated architecture explorer. It produces an inventory (classes, migrations-declared schema, registered routes, frontend routes and request call sites, access-control call sites) plus Mermaid diagrams in an offline HTML viewer, sealed with source hashes. It complements the handbook; it never replaces `architecture.md`, `data-model.md`, `interfaces.md` or the feature pages. Contract: [laravel-react-contract.md](laravel-react-contract.md).

```sh
python <skill>/scripts/test_atlas.py
python <skill>/scripts/test_extractors.py <project>
node <skill>/scripts/test_render.cjs <renderer-directory>
python <skill>/scripts/atlas.py build <project> --render-deps <renderer-directory> [--observe]
python <skill>/scripts/atlas.py check <project>
node <skill>/scripts/validate_viewer.cjs <exported-html> <renderer-directory> <evidence-directory>
```

Inputs it expects: `resources/js`, optional `app/Plugins/*/plugin.json`, Composer dependencies including `nikic/php-parser`, the project's TypeScript compiler, and a reviewed `docs/engineering-atlas/definition.json`. `--observe` boots the configured application to read route registration: inspect service-provider side effects first; it does not dispatch requests, initialize tenants or run migrations, but custom boot code can have side effects.

## Details

Requirements: Python 3.10+, PHP compatible with the application, installed Composer dependencies including `nikic/php-parser`, Node, and an installed TypeScript compiler in the project's `node_modules`. Rendering uses the pinned optional tooling dependencies.

The PHP extractor parses classes, qualified source references, method locations, and supported `up()` migration operations without loading application classes. It preserves Schema::create/table declaration histories. It does not execute/replay migrations, interpret raw SQL, prove conditional execution, evaluate macros, or resolve every dynamic/connection-qualified schema call. Column shorthands remain labeled. Database scope is inferred from tenant migration paths and plugin conventions; review those conventions per project.

Foreign-key targets explicitly named by `constrained` or `on` are declarations. Default naming is inferred; the adapter records that distinction. Cardinality reflects an FK's maximum parent count and nullable declaration when available; uniqueness and polymorphic constraints require review before claiming one-to-one relations.

The optional route observer boots Laravel's configured console kernel. It gathers registered routes, handler reflection locations, declared/expanded middleware, exclusions, and whether domain binding exists. It omits actual domain values. Configuration and environment-dependent registration can differ from a deployed request. The snapshot fingerprint covers PHP source, Composer files and plugin manifests that determine provider registration, not secret environment values. Both build and check reject a stale snapshot. Always identify this environment scope in the report.

TypeScript AST extraction inventories literal JSX Route paths, their visible RequirePlugin/CheckoutGuard wrappers, imports, and axios-style named client get/post/put/patch/delete calls. Template interpolations become `{dynamic}`. Custom wrapper APIs, fetch, generated routes, path constants, nested path composition, and dynamic theme/page reachability may need extra adapters or reviewed flows. Method/path matches are candidates; they do not prove execution. Do not link every theme import to every page as if all calls execute.

The access-control inventory locates authorization methods, policy methods, panel access, and gate registration/call sites. It is an inspection index, not a security audit or a complete permission matrix. Review handler queries, FormRequests, plugin contributors, and tenant scope for critical flows.

## Tooling setup

Copy `tooling/package.json` to a separate task-owned tooling directory. Run `npm install --prefix <directory> --no-audit --no-fund`, then `<directory>/node_modules/.bin/playwright install chromium` if needed. Keep downloaded tools outside application dependency files. The renderer loads Mermaid locally and embeds rendered SVGs into the output HTML. No external service or MCP is required.

## Rebuild rules

1. Inspect provider boot safety and refresh the route snapshot with `build --observe` when PHP inputs change.
2. Review `definition.json` diagrams and anchored observations against changed source.
3. Build with a renderer dependency directory, then run fixture tests and `check`.
4. Run `node <skill>/scripts/test_render.cjs <renderer-directory>` for the multiline HTML-label export regression.
5. Run `node <skill>/scripts/validate_viewer.cjs <html-file> <renderer-directory> <evidence-directory>` against the repository explorer and final delivered copy. Inspect its desktop, mobile, system context, database and workflow screenshots.

CI `check` requires Python only. It verifies input fingerprints, generated artifact hashes, evidence anchors, stable IDs, internal route references, and well-formed SVG roots without embedded parser errors. It does not boot the application or query a database. An unrendered/failed build is not a valid atlas. A build rejects source changes detected between extraction and sealing; refreshing hashes without extraction is not a supported operation.
