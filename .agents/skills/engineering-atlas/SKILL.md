---
name: engineering-atlas
description: Write and maintain a complete, source-linked developer handbook for any codebase (any language or framework) - product overview, local setup, architecture and request flows, data model, APIs/interfaces, configuration, one page per feature covering both how users use it and how it works in code, deployment and rollback, operations runbooks and troubleshooting, and a changelog built from git history. Use when asked to document a project or feature, write onboarding/architecture/deployment docs or a changelog, update docs after a change, or review documentation coverage.
---

# Engineering Atlas: project handbook

Produce documentation that lets a developer who has never seen the repository **understand** what the
application does and how it is built, **run** it locally, **use** every feature, **change** it safely,
**deploy** and roll it back, and **support** it in production. Every statement is grounded in this
repository's code, config and history, and links to it.

The skill is stack-agnostic. Nothing about the target's architecture is assumed: layers, modules, tenancy,
plugins, queues or frontends appear in the docs only if discovery finds them.

## Deliverable

Plain Markdown under `docs/` (or the project's existing docs engine and nav), index at `docs/README.md`,
linked from the repository README. Templates for every page are in `assets/templates/`.

```text
docs/
  README.md            index, "I want to..." table, reading paths, system-at-a-glance diagram
  overview.md          product, actors/roles, capability map, business rules, glossary
  getting-started.md   clone -> configured -> seeded -> running -> tests green
  architecture.md      context, runtime building blocks, code organization, request lifecycle, cross-cutting concerns
  data-model.md        stores, ER diagrams, state machines, data rules, schema changes
  interfaces.md        HTTP/GraphQL/RPC APIs, CLI commands, events/webhooks, third-party integrations
  configuration.md     every env var and runtime setting, precedence, secrets, flags
  development.md       repo layout, commands, conventions, adding a feature, testing, debugging, CI
  features/README.md   catalog of every capability
  features/<slug>.md   one per feature: "How to use" (users) + "How it works" (code), config, tests, troubleshooting
  deployment.md        environments, topology, processes, first setup, release steps, rollback, migrations, CI/CD
  operations.md        monitoring, background jobs, routine tasks, runbooks, troubleshooting index, backups
  decisions/           ADRs, only for recorded decisions
  coverage.md          status and verification per page and feature, open questions
  handbook.json        page set enforced by docs_check.py
CHANGELOG.md           Keep a Changelog, reconstructed from git history, then kept current
```

Merge pages for small projects, split them into folders for large ones, and mark genuinely inapplicable
pages in `handbook.json` and `coverage.md`. See [handbook-structure.md](references/handbook-structure.md)
for what each page must contain and the depth bar.

## Workflow

Choose the scope first: **full handbook**, **update for a change** (go to step 7), or **single feature**
(steps 1, 5, 7, 8 for that feature).

1. **Discover.** Read repository instructions, then run
   `python <skill>/scripts/discover.py <repo>` and read the sources it points to in the order given in
   [discovery.md](references/discovery.md). Preserve unrelated working-tree changes; keep scratch files out
   of the repository.
2. **Catalog features.** Build the full capability list (screens, route groups, commands, jobs, integrations,
   tests, commit scopes) and write `docs/features/README.md` first, so coverage is visible from the start.
3. **Foundation pages.** Write `overview`, `getting-started`, `architecture`, `data-model`, `interfaces`,
   `configuration` and `development` from the templates. Diagrams follow [diagrams.md](references/diagrams.md).
4. **Deployment and operations.** Follow [deployment-and-operations.md](references/deployment-and-operations.md).
   If the repository does not define production, write a complete *recommended* procedure derived from the
   code's requirements and list the operator decisions; do not stop at "unknown".
5. **Feature pages.** One page per catalog entry following [feature-docs.md](references/feature-docs.md):
   user steps with real labels and outcomes, then the traced implementation flow with links, failure and
   async paths, configuration/rollout, tests and troubleshooting. Large catalogs may be split across
   subagents in batches; review what comes back against the code.
6. **Changelog.** Run `python <skill>/scripts/changelog_draft.py <repo>` and curate the draft into
   `CHANGELOG.md` per [changelog.md](references/changelog.md): real history by version or month, written as
   effects, with upgrade notes.
7. **Updating for a change.** Use the change-to-page table in
   [maintenance-and-validation.md](references/maintenance-and-validation.md); update every affected page and add
   an `Unreleased` changelog entry.
8. **Validate.** Copy `assets/templates/handbook.json` to `docs/`, adjust it, run
   `python <skill>/scripts/docs_check.py <repo>`, fix every error, then do the content checks listed in
   maintenance-and-validation.md. Fill `coverage.md` and the index's revision/date stamp.
9. **Report** the pages written, features covered vs. catalogued, what was run to verify, and the open questions.

## Writing rules

- **Ground and link.** Every behavioral claim names the implementing symbol and links to it with a
  repository-relative path and, for specific code, a `#Lnn` anchor. Read the code before describing it.
- **Write with confidence about what you read.** State verified behavior plainly ("Checkout rejects carts
  with unavailable items"). Do not attach "appears to", "may", "unverified" to things the code shows. Put
  real uncertainty in one place: the page's relevant section as a short note, and `coverage.md` open
  questions. A page of disclaimers is a failed page.
- **Keep the kinds of truth distinct when they differ:** what the code does (source-reviewed), what you ran
  (exercised, with the command and result), what the repository does not say (open question), and what you
  recommend (labelled recommendation). Never present a recommendation or a guess as current fact, and never
  invent history, rationale, topology, release versions or dates.
- **Users and engineers both.** "How to use" sections use UI labels, URLs and commands in plain language;
  "How it works" sections use code names. Link between them instead of mixing.
- **Real commands only.** Use the repository's scripts and tool versions; say where to run them and what
  success looks like. Mark commands that mutate data.
- **No secrets.** Document variable names and how to provide them; never values from `.env` files, configs,
  logs or databases. Use synthetic example data.
- **Fill or remove.** No template placeholders, empty tables or heading-only sections in delivered pages;
  `docs_check.py` fails on `{{...}}`.
- **Follow the project, not a previous project.** Organize around this application's domain and readers.
  Reuse existing docs and terminology; mark superseded plans as historical.

## Safety

Documentation work authorizes reading code and writing docs. It does not authorize deploying, running
migrations or seeds against shared/live databases, calling production or paid external services, or changing
application code. Run local, safe commands (install, build, tests on disposable data) only within what the
user allows, and record what ran. Do not commit unless asked.

## Tools

| Script | Purpose |
| --- | --- |
| `scripts/discover.py <repo> [--json] [--out F]` | Stack, scripts, entry points, schema, deploy/CI assets, env var names, tests, docs, git shape |
| `scripts/changelog_draft.py <repo> [--group tag\|month\|week] [--since D]` | Changelog draft from git, classified, for curation |
| `scripts/docs_check.py <repo> [--strict]` | Required pages, links/anchors, reachability, feature sections, placeholders, Mermaid, changelog |
| `scripts/test_docs_tools.py` | Tests for the three scripts above |

All are Python 3.8+ standard library only.

### Optional: Laravel/React atlas adapter

For Laravel + React/TypeScript projects that have adopted it (`docs/engineering-atlas/` exists) or ask for it,
`scripts/atlas.py` generates a sealed architecture inventory and offline diagram viewer. It is a supplement
linked from `architecture.md`, not the handbook. Read [adapters/laravel-react.md](references/adapters/laravel-react.md)
before using it, and never run it on other stacks. Other stacks can use their own generators (OpenAPI
renderers, typedoc, ER diagram tools) the same way: generate into a separate folder and link it.
