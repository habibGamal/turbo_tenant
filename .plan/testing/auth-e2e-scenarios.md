# Storefront account and authentication E2E scenarios

Prepared 2026-11-04 from the current working tree. Planner: GPT-6.1 Sol. Implementation handoff: GPT-6.1 Terra.

## Acceptance boundary

Implement real browser journeys against Laravel/MySQL on the dedicated local `e2e` tenant. Account creation, sign-in, profile changes, password changes/resets, address writes, and logout must originate from rendered UI. Use local tenant fixtures for arranging unique customers and cleanup; use real browser-origin requests only for security assertions which have no legitimate UI control. Do not replace auth responses, fake notifications, seed React Query auth state, or use `loginAsCustomer` as the action in a login test.

Target 47 meaningful tests below, avoiding a Cartesian product of locales, viewports, and field values. Core tests use English desktop for deterministic semantics; selected complete journeys cover Arabic RTL and mobile. A failure must report an actual failed requirement, never disappear behind `if (isVisible())` or broad exception handling.

Preserve the heavily dirty working tree. In particular, existing `tests/e2e/helpers/auth.ts`, `playwright.config.ts`, translations, account address UI, plugin code, and reports may contain unrelated work. Prefer new account specs/helpers and a test-only mail-server bootstrap. No production changes are authorized by this plan merely to turn a failure green.

## Source and atlas evidence

Reviewed atlas: `docs/engineering-atlas/README.md` browser-route/declared-guard, tenancy/request-control, Login event/cart, and schema sections; `definition.json` request middleware, address ownership, startup/bootstrap observations. These distinguish wrapper declarations from page/backend guards, and schema declarations from live schema. Parent found atlas already stale on unrelated `resources/js/themes/default/pages/Checkout.tsx`; do not call the atlas current without rebuilding/checking.

| Behavior | Current source of truth |
| --- | --- |
| Tenant host, stateful web sessions, CSRF, API routing and throttles | `routes/tenant.php`, `routes/auth.php`, `bootstrap/app.php`, `resources/js/core/api/client.ts` |
| JSON login/register/logout/current user/forgot/reset/profile/password | `app/Http/Controllers/Api/AuthController.php` |
| Login rules and per-email/IP lockout | `app/Http/Requests/Auth/LoginRequest.php` (5 credential failures; following attempt rejected; successful auth clears key) |
| Registration/profile rules | `AuthController::register`, `app/Http/Requests/ProfileUpdateRequest.php` (lowercase email, unique email, required name; password default minimum 8) |
| Auth cache and bootstrap/cart invalidations | `resources/js/core/context/AuthContext.tsx`, `resources/js/core/context/TenantContext.tsx`, app startup/bootstrap code |
| Login/register UI, links, processing, API errors and redirects | `resources/js/themes/default/pages/{Login,Register}.tsx`, `resources/js/lib/safeRedirect.ts` |
| Forgot/reset UI and returned status | `resources/js/themes/default/pages/{ForgotPassword,ResetPassword}.tsx`; reset success goes to `/login` with React location-state message |
| Profile page guard/tabs | `resources/js/themes/default/pages/Profile.tsx` |
| Profile/password form submission and errors | `resources/js/themes/default/pages/Profile/Partials/{UpdateProfileInformationForm,UpdatePasswordForm}.tsx` |
| Account menu, logout, mobile links | `resources/js/themes/default/components/navigation/{UserMenu,MobileBottomNav}.tsx` |
| Active address UI, query invalidation and API contract | `Profile.tsx` slot `profile.addresses` resolves through `resources/js/themes/default/plugins/locations-delivery/index.ts` to `ThemedLocationsDelivery.tsx`, which renders `resources/js/plugins/locations-delivery/components/DefaultLocationsDelivery.tsx`; data hooks live in `resources/js/plugins/locations-delivery/api/useLocationsDelivery.ts`. `Profile/Partials/AddressManager.tsx` is the slot fallback, not the active default-theme component. |
| Address validation, ownership, default reset | `app/Plugins/LocationsDelivery/routes/tenant.php`, `src/Http/Controllers/Api/LocationsDeliveryApiController.php`, `src/Http/Requests/AddressRequest.php`, `src/Policies/AddressPolicy.php` |
| Checkout guard | `resources/js/core/router/guards/CheckoutGuard.tsx`; guest access depends on active guest-checkout plugin |
| Real broker/token/mail | Laravel `Password` broker; vendor framework `config/auth.php` and `Auth/Notifications/ResetPassword.php`; tenant mail override `app/Listeners/ConfigureTenantMail.php` |
| Current test patterns | `playwright.config.ts`, `tests/e2e/helpers/{live-fixture,auth,integration-token}.ts`, `tests/e2e/themes/{theme.contract,base/BaseThemeAdapter,default/index}.ts` |
| Existing fixtures | `database/seeders/E2eSeeder.php`, `e2e-test-suite.md`; `tests/Feature/Api/AuthApiTest.php` for API contracts (its Notification::fake is not suitable for mail E2E) |

## Critical supported-function distinctions

- `/profile` has both backend `auth` middleware for direct navigation and a React effect for SPA navigation. SPA guest redirect explicitly carries `/login?redirect=/profile`; the root reviewer verified direct guest navigation on live port 8000 redirects to `/login` exactly. Do not invent intended-URL restoration for the SPA login API: it uses the explicit safe `redirect` query.
- Laravel GET guest middleware protects direct `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`. React does not wrap the same routes with a guest-only component; do not assert identical direct/SPA guest-only behavior without a production requirement.
- Valid safe redirects are local absolute paths, optionally including query/hash. Reject `https://...`, `//...`, backslashes, control characters, and non-absolute paths to `/`. Preserve the redirect when switching login/register via their links. Forgot-password links do not preserve it.
- Profile currently persists **name and email only**. A phone field is displayed/submitted but `ProfileUpdateRequest` omits phone and `AuthController::updateProfile` only fills name/email. Treat phone persistence as an existing product gap, not an assertion that losing a submitted phone is correct. Report the gap; optionally add one documented expected-failure test only if the reviewer elects to track it.
- `User` does not implement `MustVerifyEmail`; React profile uses `mustVerifyEmail=false`, and customer resource omits `email_verified_at`. No runnable verification prompt/resend journey can honestly be claimed. Backend verification routes exist, but React has no verify/confirm screens.
- `DELETE /profile` exists server-side, but the current profile UI has no account deletion control. Do not add a synthetic API-only delete journey and label it user E2E. Password-confirmation routes likewise lack React UI.
- Google login is an external OAuth handoff. Test the rendered link and a safe local failure path if useful; do not automate a real Google account, invent callback identities, or call native login a browser journey. Existing `tests/Feature/Auth/GoogleAuthTest.php` and native/API tests cover mocked provider contracts separately. Current Google href omits login redirect and remember state; do not claim these survive OAuth.
- A reset does not log the customer in. Password update does not explicitly log the customer out. Cross-tab React Query state is not explicitly broadcast; after another tab logs out, a reload or protected request must enforce session state, but immediate cross-tab UI synchronization is not promised.
- Address phone is supported and validated as `+20` followed by ten digits. Account profile phone and address phone are separate capabilities.
- The active default-theme address slot renders `DefaultLocationsDelivery`: deletion uses native `window.confirm`, and selecting a governorate automatically selects its first area. The fallback `AddressManager` instead uses an AlertDialog and different area selection behavior; its selectors and confirmation test ID do not describe the active slot.

## Isolation and safe fixtures

1. Match the existing `integration-token.ts` pattern: `execFileSync('php', ['-r', script])` or a committed test support PHP script with structured input; never interpolate user strings into shell commands. Resolve repo root relative to `import.meta.url`. Capture stderr privately and return sanitized diagnostics.
2. Refuse fixture mutation unless URL is local HTTP `e2e.localhost`, application env is `local`/`testing`, tenant id exactly `e2e`, and its domain is verified in the central domain relationship. Port can differ for the dedicated mail-capture server. No tenant creation, activation, migrations, reset/reseed, or global cache clears in tests.
3. Run each test with a fresh Playwright browser context and a unique lowercase email such as `auth-e2e-<run>-<test>-<random>@example.test`; fixture-created users must be `is_admin=false`. Maintain a registry of exact created IDs and all old/new emails. Do not change `admin@example.com`/`password` because the rest of the suite depends on it.
4. Create fixture users with `Hash::make`, explicitly control verified timestamp only where checked, and use `$tenant->run(...)`. Browser registration tests register their own account and register its resulting ID/email for teardown.
5. Teardown in `finally`/fixture teardown, including failures: delete only exact owned reset-token rows, addresses, cart/favorite-related rows if any, known session rows if safely attributable, and owned users. Honor FK order/schema; never `User::query()->delete()`, wildcard-delete a shared prefix, truncate tables, or reset all sessions/rate limits. If a test changes email, cleanup tracks both identities and exact user ID.
6. Login lockout has its own unique email/IP key. Clear only that specific Laravel `LoginRequest::throttleKey` in tenant scope when needed; never flush cache. HTTP throttle middleware for forgot/reset/password may share a guest IP key, so run serially and allow it to decay before a fresh group; verify the configured key behavior before assuming unique emails isolate these routes. Each test must stay below its real route budget except the explicit throttle scenario.
7. Settings mail capture, if used, must snapshot exact presence/value of only `SettingKey::MAIL_MAILER` in `e2e`; set log through `SettingService`; restore original value or delete row if originally absent, even after failure. Do not print other settings, SMTP credentials, or entire config. Prefer the test-only request hook below, which requires no persistent tenant setting mutation.

### Real reset-mail capture without shared log access

`MAIL_MAILER=log` alone is insufficient: `ConfigureTenantMail` can override it from tenant settings. Do not read/truncate `storage/logs/laravel.log` or search historical messages/reset URLs. Laravel logging paths are built before tenant filesystem suffixing, so do not assume the normal log is tenant-private.

Preferred implementation is a **test-only local PHP router** under `tests/e2e/support/` and a separate built-in HTTP server port (e.g. 8011). Serve existing `public` assets as Laravel's server router does, then bootstrap the real app as `public/index.php` does. Register an `app->booted` callback before `handleRequest`; after normal providers boot, register a final `TenancyInitialized` listener that:

- Is enabled only through an explicit E2E run environment flag and refuses all hosts/tenant IDs except `e2e.localhost`/`e2e` in local/testing.
- Runs after `ConfigureTenantMail`, sets real mail default to a dedicated `log` transport and a dedicated single logger writing a **fresh run-owned file** in an absolute artifact directory. Purge/rebuild the named mailer if resolution occurred earlier.
- Keeps normal auth/controller/broker/notification behavior. This is a real mail transport, not a fake notification; it safely sinks messages without SMTP.
- Restricts the test server to loopback and serves no extra HTTP fixture/token endpoints. Validate any static-file passthrough path stays under `public`.

Use UI forgot-password, then bounded-poll only that new file for the exact owned recipient and extract its `/reset-password/<token>?email=...` URL. Validate URL origin/hostname, path, and owned email; avoid logging/attaching raw mail/reset tokens. Pass the URL only to the current test. Ensure HTML quoted-printable/link escaping is decoded correctly. Remove only the owned capture file at teardown; disable traces/screenshots on successful secret-bearing pages if report leakage is a concern, and do not print secrets on failures.

If a dedicated mail server is not implemented, broker fixture `Password::createToken($ownedUser)` can arrange reset scenarios and return the token privately (or use owned broker reset rows to age expiry). Label that coverage **broker-backed reset**; it does not prove the forgot-email-link handoff. `Password::createToken` is not raw SQL token fabrication. Do not claim a complete mail journey with that fallback.

Read broker config dynamically: framework default expires in 60 minutes and throttles repeat send for 60 seconds, but env/config may override. For expiry tests age only the owned user's token `created_at` older than configured expiry; never sleep an hour. To test resend after an interval, age only owned token time beyond broker throttle while still within expiry, submit UI again, then prove old URL invalid and new URL usable.

## Scenarios to implement

Each row is one meaningful test; related assertions stay in one journey. Core tests are independent and use owned fixture users unless the registration action is under test.

| ID | Journey and concrete assertions |
| --- | --- |
| A01 | Guest opens account entry from desktop navigation, follows register/login/forgot/back links, reaches correct rendered forms; login/register switch preserves an explicit safe redirect. |
| A02 | Valid UI login reaches home, menu shows owned name/email, profile has matching fields, reload remains authenticated. |
| A03 | Wrong password and then corrected password: visible inline error, stay guest/on login, field values usable, valid correction signs in; never leak password/hash in `/auth/user` response. |
| A04 | Empty login fields return field errors, malformed browser email blocks invalid submission, no authenticated menu appears. Distinguish native HTML validity from backend errors. |
| A05 | Remember checked: UI login creates a persistent Laravel recaller cookie; copy only persistent cookies into a new context (drop session cookies), reopen real profile and prove identity restored. Do not assert persistence from a simple reload alone. |
| A06 | Remember unchecked: login persists across same-context reload, fresh context is guest, no persistent recaller cookie is created. |
| A07 | UI login with `?redirect=/profile` arrives at profile; a second representative local redirect with query/hash remains same-origin. |
| A08 | Malicious external/protocol-relative/backslash redirect samples all resolve locally to `/` after UI login (table samples inside one test with fresh session between samples). No external page navigation. |
| A09 | Five wrong passwords for unique owned email, next valid-password attempt blocked with visible throttle message; state remains guest. Clear only this test's key in teardown. |
| A10 | Password reveal/hide on login and registration: type changes preserve values; reveal button does not submit the form. Buttons currently lack accessible names, so use tightly scoped form/container selectors rather than global positional buttons. |
| R01 | Register unique valid customer via UI, becomes authenticated, intended profile redirect works, reload persists and new identity has no admin privileges. |
| R02 | Blank registration produces name/email/password errors; fixing values can succeed; no partial owned customer row exists after invalid request. |
| R03 | Duplicate fixture email is rejected visibly, original account remains unchanged, no authenticated session is created. |
| R04 | Short password and mismatched confirmation are rejected; corrected password/confirmation creates account. Password rule defaults are backend contract, not all visual strength-tip text. |
| R05 | Uppercase email rejected by Laravel lowercase validation; normalize by editing UI to lowercase and registration succeeds. Use source-backed rule and verify actual runtime message. |
| R06 | Registration with unsafe redirect authenticates but stays same-origin on `/`; subsequent logout/relogin with chosen password works. |
| F01 | Existing owned email submitted in forgot UI renders success and real captured mail targets that recipient; full captured link opens real reset form with read-only query email. |
| F02 | Unknown unique email displays visible broker user error, no success, no captured reset email for that identity. Current source exposes existence, so do not invent enumeration-safe generic behavior. |
| F03 | Blank/malformed forgot email: required backend error/native email validity respectively, submit remains usable, no reset mail. |
| F04 | Immediate repeat forgot request for same owned email receives visible broker throttle, only one actual mail is captured; no rapid retries. |
| P01 | Captured or broker-arranged valid URL: matching new password succeeds, redirects login with visible success status; old password fails, new password succeeds through UI, token is consumed. |
| P02 | Invalid token: visible reset email-field error, stay reset form, original password still works. |
| P03 | Expired owned broker token: visible error, no login/new-password acceptance, original password remains valid. |
| P04 | Reused previously successful token is rejected and cannot replace the successful password. |
| P05 | Valid token plus mismatched/short new passwords shows errors; correct fields using same unconsumed token succeeds. |
| P06 | Token for owner A with email B fails; neither user's password changes. Query-provided email read-only; link lacking query permits user email entry. |
| P07 | After aging owned token past resend throttle, request second UI link: first link fails, second succeeds; capture both only from the run-owned mail file. |
| U01 | Profile opened through account menu shows name/email; edit name, save, visible Saved feedback and menu updated name; reload retains name. |
| U02 | Change email to new owned lowercase identity, save/reload, logout; old email cannot login, new email can. Fixture read verifies email_verified_at cleared without inventing verification UI. |
| U03 | Saving unchanged email and new name succeeds and preserves preexisting verified timestamp (scoped fixture read), avoiding unique-self conflict. |
| U04 | Profile duplicate email rejected visibly and persists neither submitted name nor email after reload. |
| U05 | Profile empty required fields/native malformed email block invalid submission; backend uppercase email fails visibly; original identity survives reload. |
| U06 | Password tab: valid current/new/confirmation saves, clears all password fields, Saved feedback appears; session remains usable, logout then old password fails/new password works. |
| U07 | Wrong current password produces visible error, clears/focuses current-password field; account password unchanged and correction can succeed. |
| U08 | Short/mismatched new password produces error, clears/focuses new-password fields; correct entries succeed without requiring navigation. |
| D01 | Fresh account address tab: create valid address through dialog with E2E Cairo/Downtown, +201012345678, required street/building/floor/apartment; card renders and survives reload. Area disabled until governorate chosen; active plugin automatically chooses that governorate's first area, which must be visibly verified. |
| D02 | Edit owned address including notes/phone, save, card displays updates and edit dialog reopens with persisted values. |
| D03 | Two owned addresses: mark second as default via create/edit, only second badge/default persists after reload; fixture/API confirms exactly one default owned address. |
| D04 | Native browser delete confirmation cancel retains card; accept removes card; reload proves deletion. Await the dialog action so immediate card presence is not mistaken for proven cancellation. Create dialog cancel does not create data. |
| D05 | Invalid/incomplete address: required/native validation and invalid Egyptian phone or missing area visibly block save; correction succeeds. Do not assume address fields have associated labels: source labels lack htmlFor in places. Scope inputs to dialog and container, selects by dialog combobox/order or placeholders. |
| D06 | User A address hidden from user's B profile list; real browser-origin PATCH and DELETE for A address under B return 403, address unchanged. Supplementary security requests are explicitly not UI CRUD. |
| G01 | Guest direct `/profile` redirects to login without showing private data; SPA profile link/navigation guard carries redirect; successful UI login using explicit redirect arrives profile. Test both entry paths with their actual differing URL contracts. |
| G02 | Guest real browser-origin profile PATCH, password PUT, address GET/POST return 401 using real CSRF/session handling; no mutation. `/auth/user` returns `{user:null}`. |
| G03 | Logout via menu returns home, menu becomes guest; reload and back navigation cannot expose usable private profile; direct protected navigation is denied. Subsequent UI login works with regenerated CSRF/session. |
| G04 | Two tabs share session: UI logout in first, reload profile in second is denied, no authenticated identity resurrected from bootstrap. Do not require immediate state broadcast. |
| L01 | Complete Arabic RTL mobile journey: start guest via bottom navigation, UI register or login, account/profile navigation, profile save, password tab reachable, logout. Assert html lang/dir, translated key labels, no horizontal overflow and visible actionable controls. Set viewport after live fixture (which overwrites to 1280x800), before navigation; initialize i18nextLng='ar'. |
| L02 | English mobile forgot/reset journey with owned capture/token: form controls/status readable, submit usable at 390x844, dir=ltr; no overlap/overflow from fixed bottom navigation. |

The inventory contains 10 login, 6 register, 4 forgot, 7 reset, 8 profile/password, 6 address, 4 guard/logout, 2 locale/mobile = **47** cases. Implement all 47 because each covers a distinct supported branch.

## Implementation layout and selectors

- Place new specs under `tests/e2e/plugins/auth/` so existing config `testDir: './tests/e2e/plugins'` discovers them without expanding config scope: `01-login.live.spec.ts`, `02-register.live.spec.ts`, `03-password-recovery.live.spec.ts`, `04-profile-security.live.spec.ts`, `05-addresses.live.spec.ts`, `06-session-locales.live.spec.ts`.
- Add narrowly scoped `tests/e2e/helpers/auth-fixture.ts` for owned data lifecycle and reusable account UI methods; leave existing login shortcut intact. Keep page interaction helpers distinct from PHP data ownership/mail capture helpers.
- Extend theme contract/adapter with an account surface only if needed for consistency with the current suite; avoid production test IDs when existing semantic selectors or stable input IDs work. New helper selectors: login/register/reset `input[name=email]`, `input[name=password]`, `input[name=password_confirmation]`; profile stable `#name/#email`, password `#current_password/#password/#password_confirmation` scoped within visible tabpanel. Use translated role names for buttons/tabs; import actual translation JSON or define a small exact-name dictionary.
- Profile transient Saved feedback lasts 2 seconds: assert promptly, then persisted values after reload. Do not wait for initial `/auth/user` network response: inline bootstrap may satisfy it. Assert visible identity first. Mutation responses can be awaited paired with click when precise status is useful.
- Browser-native fetch is necessary for supplementary security requests because Chromium resolves `*.localhost` while Node APIRequestContext may not. Obtain real XSRF token as existing helper does; never exempt CSRF globally. Assert status/envelope plus unchanged owned data.
- Full auth suite must run with one worker, no retries masking lockouts, and no parallel describes. The implemented server, cache prefix, and private capture transport are isolated per test; retain real throttling within each journey. Normal Playwright defaults retain failed trace/screenshots; do not intentionally attach credentials/mail/token records.
- For guest-checkout-disabled guard coverage, do not deactivate shared tenant plugin just for an auth test. Existing suite covers checkout session expiry. Report guest-checkout-disabled branch as separately configured tenant coverage unless dedicated isolated configuration is available; do not mock bootstrap to pretend it is server policy.
- Do not silently skip unsupported mail/preconditions. Fail early with a concise provisioning message. Optional external OAuth is a separately documented handoff/manual limit, not an unreported skip.

## Verification and handoff gates

1. Check new spec discovery: `npx playwright test tests/e2e/plugins/auth --project=theme-default --list`.
2. With the dedicated E2E tenant provisioned and current frontend assets built, run `npm run test:e2e:auth`. The implemented fixture starts/stops its safe capture server automatically on an available loopback port and supplies its own `baseURL`; no external mail server or `E2E_BASE_URL` override is required. Equivalent direct command: `npx playwright test tests/e2e/plugins/auth --project=theme-default --workers=1 --retries=0 --reporter=list --output=test-results-auth`. Run complete suite once, inspect failures, fix tests only when source-backed; report production gaps explicitly.
3. Run focused existing cart login/session regression specs against the normal server if helpers/auth bootstrap behavior changed: `npx playwright test tests/e2e/plugins/cart-engine/09-persistence-continuity.live.spec.ts tests/e2e/plugins/order-management/14-session-expiry.live.spec.ts --project=theme-default --workers=1 --retries=0`.
4. Verify cleanup with scoped fixture read showing zero owned users/reset rows/addresses and restored mail setting (if mutated). Verify `admin@example.com` password/account untouched.
5. Test/support-only source changes usually do not alter atlas inputs. Run `python .agents/skills/engineering-atlas/scripts/test_atlas.py` and `python .agents/skills/engineering-atlas/scripts/atlas.py check .`; report preexisting stale Checkout input without claiming current. If production route/page/request/access code changes are actually needed, follow repository contract: inspect side effects, refresh using documented build command, review diagrams and run required checks. Do not migrate application data to build atlas.
6. Final report separates passing real user flows, supplementary security assertions, any broker-only reset coverage, unsupported OAuth/verification/delete UI, existing profile-phone gap, and provisioning/runtime failures. Include exact test count, commands and artifact paths. Stage only task-owned files if asked to commit.

## Implementation coverage review

This final source-review snapshot corresponds to 57 discovered tests, not a claim that every test passed. Test cases are split/combined differently from the 47 behavior IDs; the final Playwright run is the authority for executed count/outcomes. Root rebuilt current frontend assets and Terra then verified the active plugin address component uses native confirmation; the earlier fallback-render observation came from stale assets. The consolidated run was in progress at this review.

Implementation files: `tests/e2e/plugins/auth/{auth,profile,addresses,session}.live.spec.ts`; local real-backend support: `tests/e2e/auth/{fixture.ts,tenant-fixture.php,router.php}`. Each test starts a loopback PHP server on an available port, verifies its identity, uses a fresh cache prefix and private log transport for real reset notifications, validates reset link origin and recipient, and cleans exact registered users, reset rows, addresses, and attributable tenant database sessions. Scoped fixture reads verify admin/verified state; production auth requests remain browser/UI actions.

| Plan behaviors | Implemented reference and remaining assertion scope at review |
| --- | --- |
| A01 | `auth.live.spec.ts` “login and registration links preserve the intended local destination” and “desktop account entry and password recovery links open the expected forms” cover account entry and login/register/forgot/back navigation. |
| A02–A03 | `auth.live.spec.ts` “signs in, logs out…” and “a corrected password signs in…” plus profile identity/reload assertions. `session.live.spec.ts` nonremembered context test asserts the authenticated resource omits password/remember_token. |
| A04 | `auth.live.spec.ts` parameterized “login rejects …” covers empty password. Blank/malformed login-email samples are a smaller unimplemented assertion variation; profile/forgot native-email validation is covered separately. |
| A05–A06 | `session.live.spec.ts` remembered recaller-only fresh context and nonremembered new context tests. |
| A07–A09 | `auth.live.spec.ts` local query/fragment destination, five unsafe redirect samples, repeated-credential lockout. |
| A10 | `auth.live.spec.ts` login password toggle. Registration/confirmation reveal controls are a smaller unimplemented UI variation. |
| R01 | `auth.live.spec.ts` registration/session reload with scoped nonadmin assertion. |
| R02–R05 | `auth.live.spec.ts` parameterized invalid registration, duplicate email, uppercase-to-lowercase correction. Not every invalid-input case corrects and completes registration in the same test. |
| R06 | `auth.live.spec.ts` external registration redirect creates usable customer, logout/relogin. |
| F01–F03 | `auth.live.spec.ts` real notification journey, unknown-email error, blank/malformed forgot validation. Exact-recipient reset URL is validated by fixture; explicit no-mail assertions for invalid/unknown inputs are not included. |
| F04 | `auth.live.spec.ts` immediate second reset request error; explicit captured message count and broker-specific error distinction remain smaller assertion gaps. |
| P01/P04 | `auth.live.spec.ts` real notification changes password, visible login status, old password rejected, new password accepted, reuse rejected. |
| P02–P03 | `auth.live.spec.ts` invalid/expired token errors; both cases prove original password still works. |
| P05 | `auth.live.spec.ts` reset validation correction retains usable token. |
| P06 | `auth.live.spec.ts` wrong-customer token rejection, query-email readonly, both original accounts remain usable. Missing-query editable-email branch not yet included. |
| P07 | `auth.live.spec.ts` replacement reset link invalidates first and remains usable; age only owned token beyond broker throttle and collect distinct real captured links. |
| U01/U03 | `profile.live.spec.ts` name-only save survives reload and scoped verified flag preserved. Updated menu-name assertion not yet included. |
| U02 | `profile.live.spec.ts` email changes, verified flag clears, old email fails, new email succeeds. |
| U04 | `profile.live.spec.ts` duplicate email failure preserves previous name/email on reload. |
| U05 | `profile.live.spec.ts` native-required blank name and “profile rejects empty, malformed and uppercase email and retains the identity” cover native and backend validation with reload persistence. |
| U06 | `profile.live.spec.ts` password change, current field cleared, session retained, old/new password acceptance checked. New/confirmation field-clear assertions not yet included. |
| U07–U08 | `profile.live.spec.ts` wrong-current/short/mismatched cases assert the corresponding password fields clear and receive focus, then correct and submit the same form, prove Saved feedback, and sign in with the updated credential. |
| D01–D03 | `addresses.live.spec.ts` create/reload, edit/reopen with persisted notes/phone, two-address default replacement. |
| D04 | `addresses.live.spec.ts` native delete cancellation and acceptance/reload; create-dialog cancellation is not yet included. |
| D05–D06 | `addresses.live.spec.ts` native-required validation with correction, server Egyptian-phone rejection with correction, cross-customer hidden list plus 403 PATCH/DELETE and owner persistence. |
| G01 | `auth.live.spec.ts` direct guest profile denial and explicit redirect login; `session.live.spec.ts` “browser back after logout cannot reopen the profile and login still works” exercises authenticated menu/client routing followed by unauthenticated browser history denial. |
| G02 | `session.live.spec.ts` guest user-null and 401 profile/password/address writes/reads using real CSRF. |
| G03–G04 | `auth.live.spec.ts` UI logout/direct protected navigation; `session.live.spec.ts` browser Back after logout plus second-tab reload denial. Logout helper awaits actual logout response, guest UI and user-null verification. |
| L01 | `session.live.spec.ts` Arabic mobile bottom-nav entry, register/profile save/reload, lang/RTL/no overflow, password tab, logout. |
| L02 | `auth.live.spec.ts` English mobile real forgot/reset and no overflow. |

Additional expected failure: `profile.live.spec.ts` “profile phone persists after saving” records the existing ignored-phone product gap. It must remain distinct from passing supported name/email profile changes. Unsupported external Google completion, verification/confirmation/delete React UI, immediate cross-tab broadcast, and alternate themes remain outside claimed browser coverage. The Google test checks the rendered handoff link only.

No material supported journey remains absent in the final source review. The explicitly noted smaller assertion variations above do not constitute additional passing coverage; they remain transparent limits of this test inventory. Passing status and fixture cleanup must still come from the consolidated execution and scoped cleanup verification.
