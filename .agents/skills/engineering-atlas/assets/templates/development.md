# Development guide

## Repository layout

| Path | Contents |
| --- | --- |
| [`{{dir}}`]({{../dir}}) | {{what lives here}} |

Generated / vendored paths that must not be edited by hand: {{list}}.

## Day-to-day commands

| Task | Command | Notes |
| --- | --- | --- |
| Run the app | `{{cmd}}` | {{}} |
| Run all tests | `{{cmd}}` | {{}} |
| Run one test | `{{cmd}}` | {{}} |
| Lint / format / static analysis | `{{cmd}}` | {{}} |
| Build frontend assets | `{{cmd}}` | {{}} |
| Create a migration | `{{cmd}}` | {{}} |

## Conventions

{{Naming, folder placement, error handling, logging, translation, code style, commit message format, branching. Only what the repo evidences (configs, CI, existing code, CONTRIBUTING).}}

## Adding a feature

<!-- A concrete walkthrough using the project's real extension points, ideally following an existing feature as the example. -->

1. {{Where the new code goes and which base class / contract / registry to use}}
2. {{Data: migration, model}}
3. {{Interface: route/handler/command/page}}
4. {{Access control}}
5. {{Tests to add and where}}
6. {{Docs to update: feature page, catalog, configuration, changelog}}

Worked example: [{{existing feature}}](features/{{slug}}.md).

## Testing

| Suite | Location | Command | Needs | Runs in CI? |
| --- | --- | --- | --- | --- |
| {{unit}} | [`{{dir}}`]({{../dir}}) | `{{cmd}}` | {{database? network? browser?}} | {{yes/no}} |

{{Fixtures/factories, test data isolation, how to run against external sandboxes safely, flaky areas.}}

## Debugging

{{Logs location, debug toolbar/inspector, how to replay a job, how to inspect queue/cache state locally.}}

## Continuous integration

| Workflow | Trigger | What it checks |
| --- | --- | --- |
| [{{workflow}}]({{../.github/workflows/file.yml}}) | {{push / PR}} | {{checks}} |
