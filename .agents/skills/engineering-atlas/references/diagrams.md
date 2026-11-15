# Diagrams

Use Mermaid fenced blocks inside the Markdown pages: GitHub, GitLab, most docs engines and IDEs render them,
they diff cleanly, and they need no build step. `docs_check.py` verifies every block starts with a known
diagram type and has no leftover template placeholders.

## Which diagram answers which question

| Question | Diagram | Page |
| --- | --- | --- |
| What does the system talk to? | `flowchart LR` context (actors, system, external services) | README, architecture |
| What runs, and where does data live? | `flowchart TB` with subgraphs per process/store | architecture, deployment |
| How is the code organized? | `flowchart` of modules with labelled dependency edges | architecture |
| What happens when X is requested? | `sequenceDiagram` with real component names | architecture, feature pages |
| How do entities relate? | `erDiagram` per domain area | data-model |
| What statuses can a record have? | `stateDiagram-v2` with triggers on edges | data-model, feature pages |
| How do features chain into a journey? | `flowchart LR` | features/README |
| How does a release flow? | `flowchart` or `sequenceDiagram` of pipeline steps | deployment |

## Rules

- One question per diagram; title it in the preceding heading and explain it in prose underneath.
- Use the names that appear in code (class, service, table, queue names) so readers can search for them.
- Label edges with what flows (HTTP, SQL, job, event name) and keep arrows pointing in the direction of the call.
- Keep each diagram readable: about 15 nodes. Split by area or zoom level instead of one huge graph.
- Show only what exists. Planned components go in a separate, clearly titled diagram or not at all.
- Quote labels containing punctuation: `A["POST /orders"]`. Avoid raw `<`, `>`, `{`, `}` and `;` in labels.
- Check that diagrams render: preview in the docs engine, GitHub, or the Mermaid live editor. When Node is
  available, `npx -y @mermaid-js/mermaid-cli -i page.md -o /tmp/out.md` renders every block in a file and fails
  on syntax errors (it downloads the CLI; ask before running if network use is not already authorized).
