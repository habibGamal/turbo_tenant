# {{Project name}} handbook

<!-- Index page. Keep it short: what the system is, and where each reader should go next.
     Every page in docs/ must be reachable from here. Delete these comments when done. -->

{{One paragraph: what the application does, who uses it, and the main technology choices.}}

## Start here

| I want to... | Read |
| --- | --- |
| Understand what the product does and its vocabulary | [Overview](overview.md) |
| Run it on my machine | [Getting started](getting-started.md) |
| Understand how it is built | [Architecture](architecture.md), [Data model](data-model.md) |
| Find or change a feature | [Feature catalog](features/README.md) |
| Call or integrate with it | [Interfaces](interfaces.md), [Configuration](configuration.md) |
| Add code, run tests, follow conventions | [Development guide](development.md) |
| Release it | [Deployment](deployment.md) |
| Keep it running / fix incidents | [Operations and troubleshooting](operations.md) |
| See what changed | [Changelog](../CHANGELOG.md) |
| Know what is not yet documented or verified | [Coverage](coverage.md) |

## Reading paths

- **New developer (day 1):** Overview -> Getting started -> Architecture -> one feature page end to end -> Development guide.
- **Operator / on-call:** Deployment -> Operations -> Configuration.
- **Product / support:** Overview -> Feature catalog -> the "How to use" section of each feature.

## System at a glance

```mermaid
flowchart LR
  user([{{Primary actor}}]) --> app[{{Main application}}]
  app --> db[({{Primary data store}})]
```

{{Two or three sentences on the diagram: the main runtime pieces and how a request travels through them.}}

---
Documentation reviewed against `{{git revision}}` on {{YYYY-MM-DD}}. See [coverage](coverage.md) for gaps.
