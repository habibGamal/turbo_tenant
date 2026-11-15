# Configuration

<!-- Names, meaning and safe defaults only. Never copy real secret values. Start from discover.py's env list:
     every variable declared in example env files or read in code appears here or is grouped under "rarely changed". -->

## How configuration is loaded

{{Sources and precedence: environment variables, config files, database/admin settings, per-tenant or per-user overrides, feature flags. When does a change take effect (immediately, after cache clear, after restart, after rebuild)?}}

## Environment variables

### {{Group, e.g. Application}}

| Name | Required | Default | Purpose | Notes |
| --- | --- | --- | --- | --- |
| `{{NAME}}` | {{yes/no}} | `{{default}}` | {{what it controls}} | {{secret? build-time? per-environment value?}} |

## Runtime / admin settings

<!-- Settings stored in the database or edited in an admin UI. Link the feature page that uses each. -->

| Setting | Where it is edited | Effect | Feature |
| --- | --- | --- | --- |
| {{setting}} | {{admin screen}} | {{effect}} | [{{feature}}](features/{{slug}}.md) |

## Feature flags / toggles

| Flag | Default | Scope | Effect |
| --- | --- | --- | --- |
| {{flag}} | {{off}} | {{global / per tenant / per user}} | {{effect}} |

## Secrets

{{Which values are secrets, how each environment is expected to provide them, and how to rotate them.}}

## Per-environment differences

| Setting | Local | Staging | Production |
| --- | --- | --- | --- |
| {{NAME}} | {{value}} | {{value}} | {{value}} |
