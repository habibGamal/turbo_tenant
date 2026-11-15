# Authentication E2E verification

Verified on 2026-11-05 against the current built storefront and the dedicated local `e2e` tenant. GPT-6.1 Sol prepared the scenario plan and reviewed coverage; GPT-6.1 Terra implemented the suite. The parent independently reviewed the implementation and ran the final verification.

## Final result

- 57 tests executed with one worker and zero retries.
- 56 normal passes and one expected failure: `profile phone persists after saving`.
- Zero unexpected failures, skipped tests, or flaky tests in the final full run.
- Seven representative cases also passed in a separate independent verification run.

Run the suite with `npm run test:e2e:auth`. The final machine-readable evidence is in `test-results-auth-final/report.json`; these generated artifacts are ignored by Git. The durable scenario inventory and coverage mapping are in `.plan/testing/auth-e2e-scenarios.md`.

The suite covers login, registration, validation and correction, safe redirects, remember-me restoration, password recovery through captured real notifications, token expiry/reuse/replacement/ownership, profile and password changes, address management/ownership, guest protection, logout/history/two-tab behavior, and Arabic/English mobile journeys. Each test has its own local PHP server, cache namespace, disposable customers, and private mail capture. Auth responses and notifications are not mocked.

## Other verification

- `npm run build` passed.
- Strict TypeScript checking of the auth fixture and all four specs passed; both PHP support files passed syntax checks.
- Fixture guards refused production execution and attempts to mutate the shared customer.
- All 17 atlas fixture tests passed. The atlas was rebuilt, all 40 diagrams rendered, and freshness/integrity checks passed for 964 source files and 85 artifacts. Desktop/mobile viewer validation passed with no errors or external requests. Existing matching CLI route observations were reused; generating the atlas ran no migrations.
- Final cleanup audit found zero auth test customers, reset rows, test address records, private mail directories, or test server processes. The shared customer's configured test credentials remained valid.

## Known limits

The profile screen submits a phone number, but the existing profile request/controller do not persist it. The regression test marks only that final persistence assertion as an expected failure, after successful login, save, and reload. Application code was not changed to implement this missing capability.

Google coverage checks the rendered local handoff link, not a real external account login. Verification, password-confirmation, and account-deletion journeys lack supported React screens. Profile logout can land on home or the login redirect because of the existing guard/navigation race; the tests verify successful logout, guest identity, and protected-resource denial. Smaller assertion variations remain listed explicitly in the scenario coverage mapping.

Early smoke runs regenerated the existing generated Playwright report and default test-results artifacts. Later auth verification uses separate output directories; the new npm command also isolates its output. Existing application source edits and the existing auth shortcut helper were preserved.
