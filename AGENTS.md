# Documentation

The developer handbook lives in `docs/` (index `docs/README.md`) and is maintained with the `engineering-atlas` skill (`.agents/skills/engineering-atlas/SKILL.md`). Changes to behavior, interfaces, configuration, data, or deployment update the affected handbook pages (see the change-to-page table in `.agents/skills/engineering-atlas/references/maintenance-and-validation.md`) and add an `Unreleased` entry to `CHANGELOG.md`. Run `python .agents/skills/engineering-atlas/scripts/docs_check.py .` before declaring the docs current.

# Engineering atlas (generated architecture explorer)

This project also uses the optional Laravel/React atlas adapter, contract 1.0 in `.agents/skills/engineering-atlas/references/adapters/laravel-react-contract.md`. It supplements the handbook; it does not replace it.

For changes to architecture, plugin contracts, data declarations, routes, pages, request calls, or access controls:

- Review the relevant atlas views and source evidence in `docs/engineering-atlas`.
- Refresh inventories and review affected diagrams/observations against the actual changed code. Preserve unrelated working-tree changes.
- Run `python .agents/skills/engineering-atlas/scripts/test_atlas.py` and `python .agents/skills/engineering-atlas/scripts/atlas.py check .` before declaring the atlas current.
- Rebuild using the skill's documented command. The `--observe` option boots the configured application to read route registration; inspect provider side effects first. Do not run tenant migrations or mutate application data to generate diagrams.
- Keep source declarations, CLI registration observations, inference, and unverified runtime behavior distinct. Do not claim the migration maps equal a live tenant schema.

Read `.agents/skills/engineering-atlas/references/adapters/laravel-react.md` for generation details. The atlas documents the current architecture; it does not authorize application refactors or impose new layering rules.
