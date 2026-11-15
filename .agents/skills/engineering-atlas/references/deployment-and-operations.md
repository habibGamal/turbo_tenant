# Deployment and operations

## Two kinds of deployment page

**Confirmed procedure:** the repository defines how it ships (CI deploy job, Dockerfile + compose/k8s/Helm,
Terraform, Envoy/Deployer/Capistrano, platform config such as `fly.toml`, `vercel.json`, `render.yaml`, a
deploy script). Document exactly what those files do, step by step, linking each file.

**Recommended procedure:** nothing in the repository says how production runs. Still write a complete,
followable procedure, derived from what the application requires, and label it once at the top as
"Recommended, derived from repository requirements". List the facts only operators can supply under
"Open decisions". Do not answer missing facts with a page of disclaimers: the developer needs a procedure.

Either way, never publish real hostnames, credentials or customer data that you find in local files.

## Derive the requirements from code

| Requirement | Where to find it |
| --- | --- |
| Runtime and versions | Manifests (`engines`, `require.php`, `python_requires`), version files, CI images, Dockerfile base |
| Extensions / OS packages | Manifest `ext-*`, image install lines, libraries that need native deps (image, PDF, DB drivers) |
| Build steps | Package scripts (`build`), asset bundler config, compile steps in CI |
| Processes | Web server; queue workers (queue config, job classes); scheduler (cron definitions); websocket servers; consumers |
| Stores | Database/cache/queue/search/object-storage config and the drivers actually referenced |
| Persistent paths | Upload/storage directories, SQLite files, generated keys |
| Network | Inbound domains (multi-domain or wildcard setups), callback URLs for webhooks/payments/OAuth, outbound APIs |
| Release-time commands | Migrations (per database if several), cache/config/route build commands, asset publishing, search reindex, restarting workers |
| Health | Health-check routes, readiness logic |

## Deployment page content

Follow `assets/templates/deployment.md`:

- **Environments** with URL, branch/artifact, method, and data persistence, as far as known.
- **Runtime topology** diagram and **required processes** table with the exact start command for each.
- **Server requirements.**
- **First-time setup**: provisioning, environment, initial migrations, first admin/user creation, any per-tenant
  or per-customer onboarding command.
- **Release procedure**: preflight -> build -> release -> verify -> finish, with real commands in order. Call out
  steps that must happen in a particular order (migrate before/after switching code; restart workers after).
- **Rollback**: return to the previous artifact; which migrations reverse cleanly and which do not; what to do
  when data already changed; recovery verification.
- **Database migrations in production**: where they run, locking/long migrations, backfills, multi-database scope.
- **CI/CD**: what happens from push to production, manual gates.
- **Open decisions** (recommended procedures only).

## Operations page content

Follow `assets/templates/operations.md`:

- Health and monitoring signals that exist (health routes, logs, queue dashboards, error trackers configured in code).
- Background work: every job and schedule, its trigger, retries, whether re-running is safe, and where failures show up.
- Routine tasks: cache clears, secret rotation, backups/restore, reindexing, cleanup commands.
- Runbooks: one per realistic failure (found in code error paths, retry logic, tests, existing incident notes):
  confirm -> diagnose -> fix -> verify -> escalate.
- Troubleshooting index pointing into feature pages' troubleshooting sections.
- Data management: backups, retention, growth.

## Safety

Writing these pages never requires running them. Do not deploy, migrate shared databases, seed live data,
change DNS, rotate credentials, or call production services to "verify" documentation. Safe local commands
(install, build, test, migrate a disposable local database) may be run when the user allows, and the page
can then say what was run.
