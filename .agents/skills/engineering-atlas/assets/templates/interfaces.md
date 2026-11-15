# Interfaces

<!-- Every way something outside the codebase talks to it: HTTP/GraphQL/RPC APIs, web pages, CLI commands,
     webhooks in and out, message consumers/producers, scheduled tasks, third-party integrations.
     If an OpenAPI/GraphQL/proto schema exists, link it as canonical and summarize here. Split into an api/ folder when large. -->

## Overview

| Interface | Consumers | Auth | Canonical definition |
| --- | --- | --- | --- |
| {{REST API /api/v1}} | {{mobile app}} | {{bearer token}} | [{{routes file / OpenAPI}}]({{../path}}) |

## Conventions

{{Base URLs, versioning, auth header, content types, pagination, error envelope, idempotency keys, rate limits.}}

## Endpoints by area

### {{Area}}

| Method | Path | Purpose | Auth / permission | Handler | Feature |
| --- | --- | --- | --- | --- | --- |
| {{GET}} | `{{/path}}` | {{purpose}} | {{rule}} | [`{{Handler}}`]({{../path}}) | [{{feature}}](features/{{slug}}.md) |

<!-- For important endpoints add a request/response example with synthetic data and the error cases. -->

## Command-line interface

| Command | Purpose | Mutates data? | Source |
| --- | --- | --- | --- |
| `{{command}}` | {{purpose}} | {{yes/no, what}} | [{{file}}]({{../path}}) |

## Events, webhooks and messages

| Name | Direction | Payload | Delivery guarantees | Source |
| --- | --- | --- | --- | --- |
| {{event}} | {{in/out}} | {{fields}} | {{retries, signature, ordering}} | [{{file}}]({{../path}}) |

## Third-party integrations

| Service | Used for | Credentials (setting names only) | Failure behavior | Feature |
| --- | --- | --- | --- | --- |
| {{Service}} | {{purpose}} | `{{ENV_NAME}}` | {{timeout/retry/degrade}} | [{{feature}}](features/{{slug}}.md) |
