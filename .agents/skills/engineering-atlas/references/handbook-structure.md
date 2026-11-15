# Handbook structure

Default page set. Templates for each are in `assets/templates/`. Adapt names and splits to the project, then
record the final set in `docs/handbook.json` so `docs_check.py` enforces it.

| Page | Reader question it answers | Must contain |
| --- | --- | --- |
| `docs/README.md` | Where do I start? | One-paragraph summary, "I want to..." table linking every page, reading paths per role, a system-at-a-glance diagram, review revision/date |
| `overview.md` | What does this product do and for whom? | Actors and roles, capability map linking feature pages, key business rules, glossary mapped to code symbols, scope boundaries |
| `getting-started.md` | How do I run it locally? | Prerequisites with versions and their source of truth, install, configure, prepare data (migrations, seeds, sample accounts by reference), run every needed process, verify with a test command, common setup errors |
| `architecture.md` | How is it built? | Context diagram, runtime building blocks (processes, stores, queues), code organization as it actually exists, one request lifecycle traced through real symbols, cross-cutting concerns table, extension points, links to recorded decisions |
| `data-model.md` | What data exists and how does it change? | Stores, ER diagram(s) per area, entity table linked to models and owning features, state machines for status fields, data rules, how to change the schema |
| `interfaces.md` (or `api/`) | How do outside things talk to it? | Conventions (auth, errors, pagination, versioning), endpoints grouped by area with handler and feature links, CLI commands and whether they mutate data, events/webhooks/messages, third-party integrations and failure behavior |
| `configuration.md` | What can be configured and how? | Loading and precedence, every environment variable (name, required, default, purpose, secret/build-time), runtime/admin settings, feature flags, secret handling, per-environment differences |
| `development.md` | How do I change it safely? | Repository layout, day-to-day commands, conventions evidenced by config/CI/code, a worked "adding a feature" walkthrough using real extension points, test suites table, debugging, CI |
| `features/README.md` | What can it do? | Catalog table of every capability (actors, entry points, status, docs status), grouping by area, cross-feature journeys |
| `features/<slug>.md` | How do I use this feature, and how does it work? | See [feature-docs.md](feature-docs.md) |
| `deployment.md` | How do I release it? | See [deployment-and-operations.md](deployment-and-operations.md) |
| `operations.md` | How do I keep it running and fix it? | Health signals, background work table, routine tasks, runbooks per symptom, troubleshooting index linking feature pages, data management |
| `decisions/NNNN-*.md` | Why is it like this? | Only decisions with a recorded source or maintainer confirmation |
| `coverage.md` | What is missing or unverified? | Page and feature status, how each was verified, open questions with what they block and who can answer |
| `CHANGELOG.md` (repo root) | What changed? | See [changelog.md](changelog.md) |

Also add a short "Documentation" link from the repository README to `docs/README.md`.

## Depth bar

A page is complete when a reader can act on it without opening the code first, and can find the code in one
click when they need to. Concretely:

- Every claim about behavior names the implementing symbol and links to it (`[OrderService::place](../src/Orders/OrderService.php#L42)`).
  Use repository-relative links; add `#Lnn` line anchors for specific methods.
- Every table row is filled with project facts. A heading followed only by a template placeholder is not done.
- Procedures are copy-pasteable commands from the repository's real scripts, with expected output.
- Each diagram has one question it answers, real names in it, and prose under it.
- Size follows the system: a 10-endpoint service gets a short interfaces page; a 300-endpoint platform gets
  an `api/` folder by area. Do not pad small projects or truncate large ones to a sample.

## Organizing large projects

- Split `features/` into area subfolders when there are more than ~25 features; keep one catalog at the top.
- Split `architecture.md` into `architecture/` (overview, per-subsystem pages) when one page passes ~400 lines.
- For monorepos, give each deployable app or package its own section with its own getting-started,
  architecture and deployment notes, plus a top-level page that shows how they fit together.
- Generated references (OpenAPI renders, typedoc, atlas viewers) live in their own folder and are linked, never
  hand-edited.
