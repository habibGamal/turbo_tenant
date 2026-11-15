# Engineering atlas pilot

Apply the reusable engineering-atlas contract to the current working tree without changing application behavior.

Acceptance: a navigable offline atlas; architecture and plugin maps; central/tenant schema declarations; configured route and expanded middleware inventory; browser routes and request call sites; authorization evidence; reviewed checkout/payment flows; source references and coverage limits; reproducible scripts, tests, and a CI freshness gate; a self-contained discoverable skill.

Implementation: static PHP and TypeScript AST extraction; optional explicit application boot for registered routes; normalized JSON; Mermaid sources rendered to SVG; static HTML explorer. Preserve all pre-existing changes. Do not execute migrations, activate tenants/plugins, submit orders, or call payment services.

Validation: extractor fixture tests, source and artifact hash validation, parse/render every diagram, desktop and narrow browser inspection, search/navigation/source-link checks, skill validation. Runtime registration is not an end-to-end request test. Migration declarations are not a live schema snapshot.
