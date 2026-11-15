# Changelog

`CHANGELOG.md` at the repository root (or the project's existing changelog location) records what changed for
users, developers and operators, newest first, in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) form.
Template: `assets/templates/CHANGELOG.md`.

## Building it from history

A new changelog covers the project's real history, not just "documentation was added".

```bash
python <skill>/scripts/changelog_draft.py <repo>                        # by tag, or by month if untagged
python <skill>/scripts/changelog_draft.py <repo> --group week --since 2026-01-01
python <skill>/scripts/changelog_draft.py <repo> --include-maintenance  # show chore/test/docs too
```

The draft groups commits by release tag (falling back to calendar months), classifies them by Conventional
Commit type or subject keywords, and hides maintenance commits by default. Then curate:

1. **Merge** commits that make up one change into one entry (a feature and its follow-up fixes).
2. **Rewrite** each entry as an effect: "Customers can save delivery addresses" rather than
   "feat(address): add AddressController". Say who is affected when it is not obvious.
3. **Drop** noise: formatting, refactors without behavior change, test-only work, reverted-and-reapplied pairs.
   Keep refactors that change how developers work (new extension point, removed framework).
4. **Classify** into Added / Changed / Deprecated / Removed / Fixed / Security. Put breaking changes first in a
   section, prefixed **Breaking:**.
5. **Upgrade notes:** for each period, list required actions: new migrations, new env vars, processes to restart,
   API changes clients must adopt, data backfills.
6. **Link** the feature page for each Added/Changed entry, and the commit/PR when helpful.
7. **Unclassified** draft entries need reading the diff (`git show <sha> --stat`) before classifying.

When merge commits carry the information (e.g. "merge feature/x"), read the merged branch's commits; use
`--include-merges` to see them.

## Versions and dates

- Use release tags as version headings with the tag date: `## [1.4.0] - 2026-09-14`.
- Without tags, use dated periods: `## 2026-10 (untagged)`. Do not invent version numbers.
- Work after the latest release goes under `## [Unreleased]`.
- Uncommitted changes: include only what you verified belongs to the work being documented; never attribute
  every dirty file in the tree to the current task.

## Keeping it current

Every change that alters behavior, interfaces, configuration, data or deployment adds an `Unreleased` entry in
the same change. At release time the `Unreleased` heading becomes the version heading.
