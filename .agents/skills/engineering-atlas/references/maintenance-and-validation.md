# Maintenance and validation

## Validate before delivering

```bash
python <skill>/scripts/docs_check.py <repo>            # errors fail; warnings listed
python <skill>/scripts/docs_check.py <repo> --strict   # warnings fail too (use for CI once the handbook is complete)
```

It checks: required pages exist (or are declared not-applicable); every relative link, heading anchor and
`#Lnn` line anchor resolves; every page is reachable from the index; every feature page is linked from the
catalog, has the required section groups and links to source; no `{{placeholder}}` is left; Mermaid blocks
declare a diagram type; the changelog has sections with entries.

It does **not** check that the content is true. Before delivering, also:

- Re-read each feature page's "How it works" against the code it links.
- Run the getting-started and test commands when the user allows it; record what ran in `coverage.md`.
- Spot-check that UI labels and URLs in "How to use" match the source (or the running app).
- Confirm every env var from `discover.py` appears in `configuration.md`.
- Confirm every catalog row has a page, and every route group / command / job belongs to some feature.

## `docs/handbook.json`

Copy `assets/templates/handbook.json` into `docs/` and adjust it to the project's actual page set:

| Key | Meaning |
| --- | --- |
| `index` | Entry page; reachability is measured from here |
| `required` | Pages that must exist; `a|b` means either name satisfies it |
| `not_applicable` | Required entries that do not apply (explain why in `coverage.md`) |
| `feature_dir`, `feature_catalog` | Where feature pages and the catalog live |
| `feature_sections` | Optional override: list of heading-alias groups each feature page must contain |
| `scan`, `exclude` | Files/folders to check; exclude generated output |
| `changelog` | Changelog location(s) |

## Keeping docs current

Map each kind of code change to the pages it affects, and update them in the same change:

| Change | Update |
| --- | --- |
| New or changed feature | Its feature page (both halves), catalog row, changelog entry; interfaces/config/data pages if touched |
| New endpoint, command, event, webhook | `interfaces.md` and the owning feature page |
| New env var or setting | `configuration.md`, feature page's configuration section, changelog upgrade note |
| Migration / schema change | `data-model.md`, feature page data section, deployment notes if it needs special handling |
| New process, job or schedule | `architecture.md` building blocks, `deployment.md` processes, `operations.md` background work |
| Dependency/runtime upgrade | `getting-started.md` prerequisites, `deployment.md` requirements, changelog |
| Build or CI change | `development.md`, `deployment.md` CI/CD |
| Incident or bug with a new failure mode | Feature troubleshooting and an `operations.md` runbook |

Suggest (do not impose) adding to the repository's agent/contributor instructions: "Changes to behavior,
interfaces, configuration, data or deployment update the affected docs pages and `CHANGELOG.md`; run
`docs_check.py`." Add a CI job running `docs_check.py` only when the maintainers want it.

## Updating for one change

1. Identify the affected pages with the table above.
2. Read the diff and the surrounding code; update the pages and their diagrams.
3. Add the changelog entry under `Unreleased`.
4. Update `coverage.md` rows and the revision/date stamp of the pages you touched.
5. Run `docs_check.py`.
