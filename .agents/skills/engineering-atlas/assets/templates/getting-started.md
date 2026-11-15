# Getting started

<!-- Goal: a new developer goes from clone to a working, signed-in local app and a green test run.
     Use the repository's real commands. Mark each step you actually ran while writing this page. -->

## Prerequisites

| Tool | Version | Source of truth |
| --- | --- | --- |
| {{Runtime}} | {{version}} | [{{.tool-versions / manifest / CI image}}]({{../path}}) |
| {{Database}} | {{version}} | {{compose file / CI service}} |

## 1. Install

```bash
{{clone and dependency install commands}}
```

## 2. Configure

```bash
{{copy example env, generate keys}}
```

Minimum settings to change for local use: {{list, linking to configuration.md entries}}. See [Configuration](configuration.md) for everything else.

## 3. Prepare data

```bash
{{create database, run migrations, load safe sample data}}
```

{{What sample data this creates, including any local-only test account and where its credentials are defined (link the seed file; never paste real credentials).}}

## 4. Run

```bash
{{start every process the app needs locally: web, workers, frontend dev server, scheduler}}
```

Open {{local URL}}. You should see {{expected first screen}}.

## 5. Verify

```bash
{{fast test command}}
```

Expected: {{what success looks like}}.

## Common setup problems

| Symptom | Cause | Fix |
| --- | --- | --- |
| {{error text}} | {{cause}} | {{fix}} |

## Next steps

Read [Architecture](architecture.md), then pick a feature from the [catalog](features/README.md) and follow its "How it works" section through the code.
