# Feature documentation

Every capability in `features/README.md` gets its own page from `assets/templates/features/feature.md`.
Small related capabilities may share a page (e.g. "Favorites" with add/remove/sync) if each one has its own
"How to use" subsection. A catalog row is not a feature doc.

## The two halves

**For people who use it** (end users, admins, API consumers, operators):

- *Overview:* outcome, actors, availability conditions (role, plan, flag, enabled module, setting).
- *How to use:* numbered steps per actor using the real UI labels, menu paths, URLs, API calls or commands;
  the visible result after each important step; what error messages mean and what to do next.
- *Rules users notice:* validation, limits, pricing/eligibility, states that block an action.

Write this half in plain language with no class names. Get labels from the actual UI source (component text,
translation files, admin panel resource definitions), not from guesses. Check them in a running app when that
is available and authorized.

**For people who change or operate it:**

- *How it works:* a sequence diagram plus a numbered walk-through from entry point to result, naming and linking
  every hop: route/page/command -> validation -> authorization -> service -> persistence -> side effects
  (events, jobs, notifications, webhooks, cache) -> response.
- *Asynchronous and failure paths:* what runs later, retries, idempotency, duplicate submissions, partial
  failure, external outages, and how the user eventually sees the outcome.
- *States:* a state diagram when the feature moves an entity through statuses, with the trigger for each transition.
- *Data:* entities and fields read/written, linking `data-model.md`.
- *Interfaces:* endpoints/events involved, linking `interfaces.md`.
- *Configuration:* settings, flags, env vars; enablement and rollout steps (migrations, seeds, processes that
  must be running, ordering), and how to disable or roll back.
- *Testing:* automated tests that cover it (linked) and the command to run them, a manual smoke test with
  expected result, and what is not covered.
- *Troubleshooting:* symptom -> likely cause -> where to look -> fix.
- *History:* notable changes with commit/PR/changelog references.

Keep headings even when a section does not apply and write "Not applicable: <reason>"; `docs_check.py`
requires the section groups (How to use, How it works, Configuration, Testing, Troubleshooting).

## Tracing a feature

1. Start at the entry point (route, page component, command, listener). Follow calls, not imports.
2. Note every branch that changes the outcome: validation failure, permission denial, not-found, conflict,
   external error. Each becomes a row in "How to use > if something goes wrong" or "Troubleshooting".
3. Find where state changes and whether it is transactional.
4. Find side effects: dispatched events and their listeners, queued jobs, mail/notifications, outbound HTTP.
5. Read the tests for the feature to confirm the intended behavior and find edge cases.
6. Check the frontend for the same flow: which component calls which endpoint, what it shows on each response.

## Parallelizing

Feature pages are independent once the catalog, architecture and data-model pages exist. On large projects,
split features into batches and hand each batch to a subagent (if the environment supports them) with: the
template, this file, the catalog entry, the relevant source paths, and the instruction to link shared pages
instead of repeating them. Review every returned page against the code before accepting it.
