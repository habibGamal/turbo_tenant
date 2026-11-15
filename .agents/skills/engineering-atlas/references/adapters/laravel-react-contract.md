# Engineering atlas contract 1.0 (Laravel/React adapter)

Optional. This contract applies only to projects that adopted the Laravel/React atlas generator ([laravel-react.md](laravel-react.md)). It defines the generated architecture inventory and offline viewer. It is not the handbook standard: the handbook follows [handbook-structure.md](../handbook-structure.md), and the atlas viewer is linked from `architecture.md` as a supplementary generated reference. Its sections (routes, pages, tenant boundaries, plugins) reflect that adapter's stack and must not be used as the outline for other projects.

Every adopting project supplies these sections in this order. A genuinely inapplicable section records a reason. An unimplemented extractor records an explicit coverage gap; it is not equivalent to an empty system.

1. Architecture: system context, logical applications and stores, important component boundaries. Separate logical structure from verified deployment.
2. Modules: responsibilities, declared dependencies, source dependencies, extension points and integrations. State whether dependency edges are imports, registration, runtime calls, or an intended rule.
3. Database: database/tenant boundaries, tables, column declarations, keys, indexes and relationships. Identify constraint, ORM, inferred or live-schema evidence. Preserve migration conflicts and provenance; do not silently assume migration order or successful application.
4. Routes: method, URI, name, handler, registration scope, middleware and exclusions, source, and known access checks. Expanded route middleware does not include every global framework behavior.
5. Pages and requests: frontend routes, page/guard declarations, layouts/theme resolution, request call sites, and traceable server candidates. A matching URL is not proof of page reachability or runtime invocation.
6. Access controls: authentication, authorization, resource ownership, tenant boundaries, plugin/feature gating, UI visibility, and failure outcomes. Record decision locations and conditions.
7. Workflows: representative critical requests with real sequence and branches, validation/denial outcomes, persistence, external calls, events/jobs, and transaction boundaries where evidenced.
8. Evidence: source revision and working-tree fingerprint, extraction method, analyzed sources, coverage limits, unresolved questions, and verification performed.

## Entity identity and evidence

Use deterministic IDs based on qualified symbols or scoped natural keys. Database identities include connection/tenant scope. Method/URI/handler/domain-binding distinguish routes. Human labels must not become identity keys across unrelated entity types.

Evidence records have a repository-relative `file`, 1-based `line`, and optional `contains` anchor. Anchors are checked near the referenced line. Never point outside the project. Use labels `declared`, `observed-registration`, `source-reviewed`, `inferred`, and `unknown` as appropriate. Route registrations are observations of configured application boot, not observed request execution.

The normalized JSON has `contract_version`, `project`, `revision`, `scope`, `modules`, `classes`, `tables`, `schema_operations`, `routes`, `pages`, `requests`, `frontend_imports`, `controls`, and `coverage`. Entities use `id` and source evidence; tables additionally retain definitions, changes, foreign-key declarations, indexes and scope. Request `route_candidates` reference route IDs. Unresolved relationships remain explicit.

## Visual conventions

Use C4 levels for architectural zoom and Mermaid ER/flow/sequence diagrams as appropriate. Every diagram declares purpose, scope, relationship meaning, and evidence. Group large projects by module. Use text labels in addition to color. Keep static sources in Git and render them to an offline viewer. Make the overview useful without opening every detailed diagram.

## Acceptance and enforcement

All required sections exist; inputs and generated artifact hashes match; entity IDs and internal references are valid; source evidence exists; every diagram parses and renders; the viewer works on desktop and narrow screens. Semantic accuracy of reviewed diagrams requires source review. Runtime controls require request testing before claiming observed behavior.

Use `AGENTS.md` for repository expectations and CI for deterministic freshness/integrity checks. Keep project-specific architecture rules explicit and separate. Never claim that this contract forces every project to use the same software architecture.
