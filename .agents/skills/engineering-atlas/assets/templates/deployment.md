# Deployment

<!-- A procedure someone can follow. If the repo defines the deployment (CI deploy job, Dockerfile, IaC, deploy script),
     document that as the confirmed procedure. If it does not, write a complete recommended procedure derived from what
     the code requires, label it as such once at the top, and list the decisions operators must confirm. -->

**Procedure status:** {{Confirmed from <file> | Recommended, derived from repository requirements; confirm items in "Open decisions"}}

## Environments

| Environment | URL | Branch / artifact | How it is deployed | Data |
| --- | --- | --- | --- | --- |
| {{production}} | {{url}} | {{main}} | {{pipeline / manual}} | {{persistent}} |

## Runtime topology

```mermaid
flowchart LR
  lb[{{Load balancer / reverse proxy}}] --> web[{{Web processes}}]
  web --> db[({{Database}})]
  web --> q[[{{Queue}}]]
  q --> worker[{{Workers}}]
  cron[{{Scheduler}}] --> web
```

### Required processes

| Process | Command | Count / supervision | Notes |
| --- | --- | --- | --- |
| {{web}} | `{{command}}` | {{}} | {{}} |
| {{worker}} | `{{command}}` | {{}} | {{restart on every release}} |
| {{scheduler}} | `{{command}}` | {{}} | {{}} |

### Server requirements

{{Runtime versions and extensions, OS packages, database/cache/queue services, disk paths that must persist, outbound network access, TLS, cron.}}

## First-time setup

```bash
{{provision infrastructure, create databases, set environment, run initial migrations, create first admin}}
```

## Release procedure

1. **Preflight:** {{CI green, changelog updated, backup taken, migrations reviewed for locking/destructive changes}}.
2. **Build:**
   ```bash
   {{install production dependencies, build assets, build image}}
   ```
3. **Release:**
   ```bash
   {{maintenance mode if needed, upload/switch artifact, run migrations, clear/warm caches, restart workers}}
   ```
4. **Verify:** {{health endpoint, smoke journey, logs, queue drains}}.
5. **Finish:** {{leave maintenance mode, announce, tag release}}.

## Rollback

{{Exact steps to return to the previous artifact; which migrations are reversible; what to do when data has already changed; how to verify recovery.}}

## Database migrations in production

{{How and where migrations run, ordering relative to code, long-running/locking changes, backfills, multi-database or per-tenant concerns if any.}}

## CI/CD

{{What the pipeline does from push to production, linking workflow files. Manual gates.}}

## Open decisions

<!-- Only for recommended procedures: facts the repository cannot tell us. -->

- {{e.g. hosting provider, process supervisor, backup schedule}}
