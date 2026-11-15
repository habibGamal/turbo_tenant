# Operations and troubleshooting

## Health and monitoring

| Signal | Where | Healthy looks like |
| --- | --- | --- |
| {{health endpoint}} | `{{/up}}` | {{200}} |
| {{application logs}} | {{path / service}} | {{}} |
| {{queue backlog}} | {{command / dashboard}} | {{}} |

## Background work

| Job / schedule | Trigger | Retries | Safe to re-run? | Failure visible where |
| --- | --- | --- | --- | --- |
| [`{{Job}}`]({{../path}}) | {{event / cron expression}} | {{n, backoff}} | {{yes/no, why}} | {{}} |

## Routine tasks

| Task | When | Command / steps |
| --- | --- | --- |
| {{Clear caches}} | {{after config change}} | `{{command}}` |
| {{Rotate a secret}} | {{}} | {{steps}} |
| {{Back up / restore}} | {{}} | {{steps}} |

## Runbooks

### {{Symptom, e.g. "Orders stuck in pending"}}

1. **Confirm:** {{what to look at}}
2. **Diagnose:** {{likely causes in order, with the check for each}}
3. **Fix:** {{supported action}}
4. **Verify:** {{how to know it is resolved}}
5. **Escalate:** {{when and to whom}}

## Troubleshooting index

| Symptom | Area | Go to |
| --- | --- | --- |
| {{symptom}} | {{feature}} | [{{feature}} troubleshooting](features/{{slug}}.md#troubleshooting) |

## Data management

{{Backups (what, how often, where), restore drill, retention and cleanup jobs, storage growth.}}
