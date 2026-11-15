# Product overview

<!-- Audience: everyone. Plain language first; link engineering detail instead of repeating it. -->

## What it is

{{The problem the application solves, for whom, and the main outcome a user gets.}}

## Actors and roles

| Actor | What they do | How they access it | Permission source |
| --- | --- | --- | --- |
| {{Customer}} | {{browse, order}} | {{web storefront / mobile app / API}} | [{{policy or guard}}]({{../path/to/source}}) |

## Capability map

<!-- One line per capability, grouped by area. Each links to its feature page. The full table lives in features/README.md. -->

- **{{Area}}:** [{{Feature}}](features/{{feature-slug}}.md), ...

## Key business rules

{{The handful of rules that shape everything else (pricing, ownership, isolation, lifecycle). Link to the feature page that owns each rule.}}

## Glossary

| Term | Meaning | Where it lives in code |
| --- | --- | --- |
| {{Term}} | {{Definition in domain language}} | [`{{Symbol}}`]({{../path/to/source}}) |

## Scope boundaries

{{What the application deliberately does not do, and which external systems do it instead.}}
