# Persian Gravity Forms Validation — 4.3.0

## G-008 Gravity Flow residual system-date closure — 2026-09-23

Planning base and actual canonical base were both `main@670c9104987c067ce1e3a244864571d0e4bad4e6`; no rebind was required. Exact Gravity Flow authority remains version `3.1.0`, owner-supplied package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`.

The exact-package residual source probe closes the four remaining non-GravityView candidates without adding a production adapter. Registry `support_state` stays `NOT_PROVEN` to preserve the existing schema vocabulary, while each exact 3.1.0 residual record is explicitly marked `exact_version_disposition=FINAL_NO_ADMISSION`.

- Status `due_date`: the table method directly calls and formats `get_due_date_timestamp()` and echoes the result; it does not traverse the admitted Status value/filter proof seam. The same getter is operational deadline authority and feeds overdue classification. CSV/export is a separate path and cannot establish a table-only presentation seam.
- Entry Detail due/expiration: `gravityflow_date_format_entry_detail` is present but only supplies a shared native date-format pattern; it has no raw timestamp/calendar replacement contract. `maybe_display_entry_detail_workflow_info()` then directly formats/prints the operational getters and the nearby extension action is after output. Schedule: `display_queued_step_details()` directly reads/prints `get_schedule_timestamp()`. The corresponding timestamp filters are workflow timing/state hooks rather than display hooks.
- Timeline/history: initial entry time is Entry `date_created`; workflow note times are Gravity Forms note `date_created`, stored in UTC. `get_note_header()` formats the timestamp directly. `gravityflow_timeline_notes` exposes the complete note array upstream of host-owned ordering/rendering, so using it for calendar display would mutate history-layer data rather than a bounded date-only output seam.
- Print: `Gravity_Flow_Print_Entries::render()` reuses Entry Detail field-grid rendering and optional Timeline rendering. It has no date formatter/calendar engine; `gravityflow_print_styles` is a CSS-asset hook only.

WU008 source evidence is `g008-residual-source-probe.json`. The residual runtime lane exercises authenticated Entry Detail/Timeline and the authentic `gravityflow_print_entries` endpoint with timelines enabled under both module states, while Status due-date remains native in the existing Status browser lane. `g008-flow-residual-no-admission.json` is the exact-run registry↔source↔browser reconciliation artifact. Green workflows prove only their executed scenarios; final exact-Head run IDs are recorded after the final Head is fixed.

Existing admitted G-008 surfaces remain separate regression gates: Gravity Forms Entries List `date_created`; Flow Inbox `date_created`, `last_updated`, `due_date`; Flow Status `date_created`, `workflow_timestamp`. G-009 evidence/state is preserved and no Gravity Perks package is consumed by this closure.

## G-008 Gravity Flow Inbox `due_date` admission — 2026-09-23

Planning and actual implementation base were both `main@f8bb09d2fef6458441731c45086de70178db6f09`; no base rebind was required. The admission remains exact to Gravity Flow `3.1.0`, package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`.

Exact-package source provenance establishes that the Inbox task model reads current-step `get_due_date_timestamp()` for both raw AG Grid `due_date` and, separately, native `due_date_human_readable`; the step contract documents that operational result as a UTC timestamp, `is_overdue()` compares the same authority with `time()`, and no-due is raw `0` plus display `-`. The same exact source also proves raw `due_date` is inserted before `due_date_human_readable`, row data is built by iterating those columns in order, and both values pass through `gravityflow_inbox_field_value`. The bounded production adapter now captures that already-computed raw integer epoch/0 by form+entry, returns it unchanged, consumes it only for Jalali display, and never invokes `get_due_date_timestamp()` from presentation processing.

The shared current-Head WU008 exact-package WordPress/Playwright lane contains three authentic assigned Inbox fixtures: an overdue due date crossing the UTC→`Asia/Tehran` civil-day boundary, a future due date, and a no-due current step. Its test-only `gravityflow_step_due_date_timestamp` callback pins deterministic epochs, records per-request/per-entry invocation counts, and returns a deliberately different timestamp if presentation processing re-enters the operational filter. The admission gate requires enabled invocation counts to equal disabled/native counts with zero nested presentation re-entry. It also compares authoritative due timestamp, overdue state, due configuration/highlight, workflow step timestamp/final status, scheduling state, assignments, Inbox IDs/count, AG Grid raw compare values, ascending/descending sort and raw-due quick-filter behavior. PersianGravity production code never registers the operational filter; only `due_date_human_readable` may become Jalali when enabled, while raw `0` and native display `-` stay unchanged.

Primary evidence is `g008-flow-inbox-admission.json` plus the enabled/disabled browser/state artifacts. Registry/evidence reconciliation requires a committed `RUNTIME_PROVEN + ADMITTED_VERIFIED` Flow Inbox surface to name that runtime artifact and requires the artifact to admit the exact surface while binding the exact PersianGravity Head and exact Gravity Flow package. The existing Inbox `date_created`/`last_updated`, Status `date_created`/`workflow_timestamp`, Gravity Forms V1 and G-009 lanes remain separate regression gates. For exact Gravity Flow 3.1.0, Status `due_date`, Entry Detail due/schedule/expiration, Timeline/history and dependent Print retain schema-level `NOT_PROVEN` support state but are explicitly `FINAL_NO_ADMISSION`; GravityView system dates remain deferred/unqualified by this batch.

## G-008 Jalali presentation validation — 2026-09-20

Required and observed implementation base: `3c89992ab19482df10bdcba22e00aba62ec0f761`.

G-008 adds the opt-in `jalali_presentation` module without changing plugin version `4.5.0`. The six pre-G-008 module defaults remain enabled; the new module default is disabled. A schema-v1 option written by an older installation is merged over source defaults so existing boolean states are preserved and the missing G-008 key remains false. `jalali_date` / `pgr_jalali_date` remains a separate true-Jalali data contract.

Production algorithm lineage is Borkowski 1996, adapted from the MIT-licensed `jalaali-js` 2.0.1 reference at commit `7ff10a0a4145c84a6911e87bfacf40ddf51a2adc`; the required notice is shipped with production runtime under `includes/jalali-presentation/LICENSE.jalaali-js.txt`. No production calendar package, Node requirement, or `ext-intl` requirement was added.

The ranges are intentionally distinct:

- `REFERENCE_CROSSCHECK_RANGE`: Gregorian years `1800..2256`, recorded as the upstream `jalaali-js` documented `Intl` agreement claim.
- `VALIDATED_PRODUCT_RANGE`: Gregorian civil dates `1800-01-01..2124-03-19` inclusive.
- Official golden coverage: directly sourced published dates from the University of Tehran Institute of Geophysics Calendar Center final calendar for 1405; this is not represented as official coverage for the whole product range.

Local exhaustive differential execution before the production commit used PHP `8.4.23`, Node `22.16.0`, and Unicode ICU `77.1`. `tests/js/g008-jalali-oracles.test.js` exercised all `118,417` Gregorian dates in `VALIDATED_PRODUCT_RANGE` and observed:

| Evidence | Result |
| --- | --- |
| Production PHP vs ICU Persian Calendar | PASS — `0` mismatches / `118,417` |
| Production PHP vs locked `jalaali-js` reference | PASS — `0` mismatches / `118,417` |
| Production Jalali → production Gregorian round-trip | PASS — `0` mismatches / `118,417` |
| First excluded date | `2124-03-20` |
| Borkowski/reference at first excluded date | `1502-12-30` |
| ICU 77.1 at first excluded date | `1503-01-01` |

That first current independent-oracle divergence is the reason V1 fails native after `2124-03-19` instead of silently equating the broader upstream reference claim with PersianGravity product support. Round-trip is consistency evidence only; it is not treated as official-calendar correctness proof.

Official 1405 golden fixtures include `2026-03-21 → 1405-01-01` plus published month-boundary cases from the same Calendar Center source. The fixture records the source identity and does not manually invent unrelated historical/future official values.

Targeted source execution before the production commit:

- PHP syntax: PASS for all staged G-008 PHP files and runtime harnesses.
- Deterministic direct assertions: PASS for official anchor, validated-range boundaries, malformed Gregorian dates, timezone day-crossing, date-only no-shift, adapter fallback, and raw Entry-value immutability.
- Exhaustive ICU/reference verifier: PASS as recorded above.
- Full repository `composer test`, `composer cs`, `composer compat`, localization checks, and PHP 8.2–8.5 matrix: delegated to the exact PR Head CI because Composer/PHPCS/PHPUnit are not installed in the source-preparation runtime.

`.github/workflows/g008-jalali-presentation-runtime.yml` is the exact-host evidence gate. It independently runs the exhaustive oracle check and installs the hash-verified owner-supplied Gravity Forms `3.1.1.1` package in disposable WordPress. The runtime harness inspects exact Gravity Forms 3.1.1.1 Entry List source for the bounded `gform_entries_field_value` / `date_created` seam; verifies visible Jalali/site-time presentation while raw GFAPI/database/REST values and native sorting/filtering remain Gregorian; disables the module; then starts a fresh WP-CLI request and proves the presentation classes/filter are absent and the display pipeline returns its native value unchanged. Exact resulting-Head workflow status must be taken from the PR run, not inferred from this source record.

The original G-008 V1 lane remains the Gravity Forms Entries List regression contract. The shared current-Head WU008 licensed integration separately validates exact Gravity Flow 3.1.0 Inbox `date_created`/`last_updated` and Status `date_created`/`workflow_timestamp`. For Status it binds exact-package source/timezone/context evidence to enabled/disabled browser output and proves DB/GFAPI/REST values, workflow state/assignees, query IDs/count, ascending/descending sort, local-civil-day start/end filtering, and CSV/export are unchanged. Its admission artifact is `g008-flow-status-admission.json`. Due dates, Entry Detail, Timeline/history, Print, GravityView system dates, global WordPress dates, GPP, ordinary GF Date fields, and future Gravity Flow versions remain unverified by that admission.

## Current content-admission snapshot — 2026-09-15

- Gravity Forms: revision 3 `CONTENT_ADMITTED_FULL`. The 6 historical surface records still deduplicate to the same 1759 identities; one reviewed `PRODUCT_REMAINDER` record admits the exact disjoint 2448 residual source-backed identities. The required union is 4207/4207, with 0 fuzzy, empty, rejected or unreviewed remainder entries.
- Gravity Forms remainder review: 2 semantic passes, 1604 second-pass corrections, per-identity accepted translation hashes and source-derived risk flags. The revision-3 validator pins the original 1759 keyset/translation fingerprint, requires the exact canonical keyset `1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb`, and fails closed on overlap, omission, stale-POT-only admission, source-census drift, review drift, placeholder/markup drift or protected technical-literal drift.
- Gravity Flow: revision 3 `CONTENT_ADMITTED_FULL` remains unchanged. The 7 historical surface records deduplicate to 732 identities and its reviewed `PRODUCT_REMAINDER` remains 366, for 1098/1098 with 0 fuzzy, empty, rejected or unreviewed remainder entries.
- Gravity Flow remainder review remains: 2 semantic passes, 34 second-pass corrections, with the original 732 keyset/translation fingerprint pinned and unchanged.
- Gravity Forms native JS handles: 0; generated translation JSON: 0. Gravity Flow also remains 0/0. `vendor_authenticity` remains `NOT_PROVEN`; exact package byte/version admission is not a vendor-authenticity claim.
- GravityView: revision 2, 6 admitted records with surface counts 2 / 1 / 340 / 41 / 30 / 53; 467 occurrences deduplicate to 461 unique admitted identities from a 3127-identity source census; provider content remains unchanged by this Gravity Forms-only batch.
- The historical WU-008/WU-009 19-surface browser evidence below remains historical evidence for those accepted surfaces. G-006 Gravity Forms runtime evidence is separately executed against the exact PR Head and exact verified Gravity Forms 3.1.1.1 package; it is representative runtime evidence, not browser coverage of all 4207 identities.

## G-006 Gravity Forms full-content qualification

This batch completes only the exact locked Gravity Forms 3.1.1.1 `gravityforms` authority. The source-backed census remains 4207 identities even though the verified vendor POT contains 4208 keys: the single POT-only stale identity `2b64a903d2065499fa0248c6199db150bb7d1a43025d2908e8a66e4af9d4ab92` (`I'm a Button!`) is not source-backed and remains excluded. The six historical surface records remain the unchanged 1759-identity accepted baseline. The new product remainder is exactly 2448 disjoint identities, and the committed aggregate must therefore be exactly 4207 identities with the pinned source keyset hash.

The remainder review index binds every accepted identity to the exact committed translation hash, records two semantic/context review passes and 1604 second-pass corrections, and reports 2448 accepted / 0 unreviewed / 0 rejected. Structural validation covers Persian plural completeness, printf placeholders, HTML/markup, URLs/entities, brace/merge-tag/template tokens and protected brand/technical literals. No correction to the original 1759 accepted Gravity Forms mappings is part of this batch. Gravity Flow must remain 1098/1098 and GravityView provider content must remain byte/semantic stable.

`G006 Gravity Forms Runtime` is the dedicated disposable integration gate for this batch. It installs the exact hash-verified Gravity Forms 3.1.1.1 and Gravity Flow 3.1.0 packages into a real `fa_IR` WordPress runtime, activates the exact PR Head, exercises newly admitted Gravity Forms text-field title and validation paths, checks representative preexisting Gravity Forms translations, checks Gravity Flow translation health, verifies provider-first/upstream-fallback behavior, verifies the stale POT-only key remains non-authoritative, renders a real Gravity Forms form, and asserts zero Gravity Forms native-JS authority/provider JSON. Passing this workflow proves representative host/runtime behavior only; it does not mean every one of the 4207 identities was browser-exercised.

## Current licensed real-browser/runtime/RTL evidence — WU-008 + WU-009

WU-008 exercised exactly 19 accepted production surfaces in a disposable real WordPress/browser runtime: Gravity Forms 6, Gravity Flow 7, and GravityView 6. That execution discovered a real PersianGravity defect on the Gravity Forms Form Builder Structured Scanner surface: the generated inline JavaScript did not preserve the `%1$s` / `%2$s` formatting placeholders as valid JavaScript. WU-009 remediated that focused defect in PR #25 by preserving those placeholders in the emitted Form Editor script and adding focused syntax/runtime regression coverage. PR #25 merged as `e2629498eb406177f876e8c9ea3c7bb976730c0b`.

The post-remediation licensed real-browser revalidation installed and tested exact PersianGravity commit `e2629498eb406177f876e8c9ea3c7bb976730c0b` with these exact owner-supplied package versions and package hashes:

| Product | Version | Package SHA-256 | Vendor authenticity |
| --- | --- | --- | --- |
| Gravity Forms | `3.1.1.1` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `NOT_PROVEN` |
| Gravity Flow | `3.1.0` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | `NOT_PROVEN` |
| GravityView | `3.3.4` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` | `NOT_PROVEN` |

Final post-remediation evidence:

| Evidence dimension | Result |
| --- | --- |
| Exact PersianGravity SHA installed | PASS — `e2629498eb406177f876e8c9ea3c7bb976730c0b` |
| Licensed package verification | PASS — 3/3 |
| Accepted runtime surfaces | PASS — 19/19 (Gravity Forms 6 / Gravity Flow 7 / GravityView 6) |
| Positive-admission provider proof | PASS — 18/18 |
| Gravity Flow Entry Detail sidebar | `NOT_APPLICABLE_ZERO_ADMISSION` — intentional zero-admission surface |
| RTL/BiDi presentation | PASS — 19/19 |
| Structured Scanner Form Builder remediation | PASS — functional browser proof after PR #25 |
| JavaScript/provider JSON boundary | PASS |
| Uncaught browser `pageerror` | 0 |
| Unresolved `BLOCKING_IN_SCOPE` findings | 0 |
| Internal artifact `SHA256SUMS` | PASS |

Authoritative run reference: GitHub Actions run `34888229939` (`https://github.com/rezahh107/PersianGravity/actions/runs/34888229939`). Final artifact: `wu008-final-revalidation-34888229939-1`. Artifact digest: `sha256:a0c781b463857ab428db8cbc10fa37849854fc59af4f33428100ca29a1939a45`. The artifact was independently downloaded and its internal `SHA256SUMS` verified. Remaining browser diagnostics were evidence-backed upstream/vendor or environment behavior; no unresolved project blocker remained.

Claim ceiling for this evidence remains strict:

- it does not claim all Gravity Forms content is Persian;
- that WU-008/WU-009 evidence by itself does not claim all Gravity Flow content is Persian;
- it does not claim all GravityView content is Persian;
- it does not claim ecosystem-wide Persian completion;
- identities outside the admitted sets remain outside this project's translation authority and continue through the documented fallback chain;
- package byte/version verification is proven, but vendor authenticity remains `NOT_PROVEN`.

Historical `NOT_PROVEN` / `NOT_EXECUTED` statements below are preserved as contemporaneous records of the work units in which they were written. They are not rewritten as though newer evidence existed at those earlier checkpoints; for the current accepted 19-surface licensed browser/runtime/RTL state, the WU-008/WU-009 record above is controlling.

WU-006 GravityView classified Content Admission v2 source evidence on 2026-09-14: exact GravityView 3.3.4 package SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` was reverified; the reviewed authority input was byte-verified before header-only GNU-gettext canonicalization; repository-native content validation reproduced all six surface fingerprints and the 461-identity aggregate; `composer i18n:build` and `composer i18n:check` passed deterministically; PHP syntax, `composer test`, `composer cs`, `composer compat`, Structured Scanner Node tests, runtime-integrity guards, zero-JS/no-JSON checks and protected-boundary diffs passed before the production commit. No real licensed GravityView browser/UI scenario was executed, so browser/runtime presentation remains `NOT_PROVEN`.

WU-007 cross-product integrated hardening evidence on 2026-09-14: no production runtime defect was found. The production manifest validates exactly 19 accepted records (Gravity Forms 6 / Gravity Flow 7 / GravityView 6) with deterministic unions 1759 / 732 / 461 and non-admitted counts 2448 / 366 / 2666. All three product script maps remain empty; `gk-query-filters` and `action-scheduler` remain unmanaged dependency domains. WordPress Core production-provider validation now exercises committed PHP/MO artifacts for all three products: admitted identities resolve from PersianGravity, representative non-admitted identities preserve upstream fallback in both PHP and MO formats and source-English fallback with upstream absent, six cross-domain contamination checks pass, and both excluded dependency domains remain unmanaged. The Core run passed 97 general localization checks plus 25 production-provider checks. A clean checkout ran `composer i18n:build` with zero tracked or untracked drift, followed by `composer i18n:check`; PHPUnit passed 130 tests / 1464 assertions on PHP 8.3 in that clean-build gate. Licensed WordPress/product browser surfaces and RTL presentation remain `NOT_PROVEN` here and belong to WU-008.

This file distinguishes source evidence from real WordPress/Gravity Forms browser evidence.

Status vocabulary:

- `SOURCE_AUTOMATED_PASS` — an automated source/unit/static check was actually executed and passed.
- `MANUAL_ADMIN_PASS` — a real WordPress admin scenario was executed and passed.
- `MANUAL_GF_PASS` — a real WordPress + Gravity Forms scenario was executed and passed.
- `NOT_EXECUTED` — the check was not run.
- `NOT_PROVEN` — available evidence is insufficient for the claimed behavior.

## Baseline

Initial `main` for the 4.2 work:

`b1a52c975988bf60f473d4b843de057ed5bd0c06`

Runtime plugin header / `PGR_VERSION` at that checkpoint: `4.1.0`.

Observed active-documentation drift at baseline:

- README current version: `4.0.0`.
- AGENTS current version: `4.0.0`.
- readme Stable tag: `4.0.0`.
- `languages/` contained no POT/PO/MO assets.

These observations do not rewrite historical 4.0/4.1 validation claims; they only establish the 4.2 starting state. Earlier detailed validation remains historical evidence in repository history.

## 4.2 source changes

The 4.2 branch adds:

- bounded `PGR_Module_Registry` and `pgr_modules` state persistence;
- class-load and hook/field runtime gates;
- bounded safe-disable usage inspection;
- bilingual Overview module controls;
- module state in System Status;
- Scanner Profiles availability while Scanner runtime is disabled;
- local bilingual Help Catalog and native contextual help;
- `fa_IR` POT/PO/MO translation assets;
- module/usage/runtime/help/repository-consistency tests;
- active version metadata synchronized to 4.2.0.

## Historical 4.2 preparation evidence

### PHP syntax

Command category:

```sh
php -l <new-or-replaced-4.2-php-file>
```

Result: `SOURCE_AUTOMATED_PASS` for the new/replaced 4.2 PHP files prepared for the branch.

### MO structure

The generated `languages/persian-gravityforms-fa_IR.mo` was identified by the local `file` utility as a GNU message catalog.

Result: `SOURCE_AUTOMATED_PASS` for binary catalog structure only.

This does not prove WordPress locale loading.

## Repository CI

The existing workflow continues to run:

- shipped PHP syntax;
- WPCS/PHPCS;
- PHPCompatibility baseline;
- Structured Scanner pure-JavaScript tests;
- runtime-integrity guard;
- PHPUnit on PHP 8.2, 8.3, 8.4, and 8.5.

The new PHPUnit files are discovered through the existing test suite; no second CI system is introduced.

The original preparation record did not bind a CI run. Reconciliation evidence below supersedes that pending statement for the integrated source tree; exact resulting-head CI is recorded in PR #8 after execution.

## Translation generation

`composer i18n:pot`: `NOT_EXECUTED` in the preparation runtime because WP-CLI i18n tooling was unavailable there.

The shipped POT/PO/MO are therefore source assets that still require CI/reviewer verification against extracted source strings. Do not treat their presence as proof of complete translation coverage.

## Manual WordPress / Gravity Forms validation

No disposable real WordPress + licensed Gravity Forms environment was available during source preparation.

Therefore all of the following remain `NOT_EXECUTED` / `NOT_PROVEN` until performed:

1. Overview renders six bilingual capability cards.
2. Upgrade from 4.1.0 leaves all six modules enabled.
3. Disable an unused custom-field module and confirm its GF field disappears after reload.
4. Re-enable it and confirm field return.
5. Used custom-field module disable is blocked with affected-form count.
6. Form-level digit-normalization use blocks disable.
7. Currency `UNKNOWN` flow requires explicit second confirmation.
8. Scanner disable removes Scanner field/runtime assets while Scanner Profiles stays available.
9. Scanner re-enable restores field availability and keeps prior profile/mapping configuration.
10. System Status matches persisted module state.
11. `default_force_english` remains preserved across National ID disable/enable.
12. Help opens in Persian and English and language tabs work server-side.
13. Contextual Help appears on all PersianGravity screens.
14. `fa_IR` ordinary UI loads from the plugin text domain.
15. English locale renders English ordinary UI.
16. Technical IDs remain LTR.
17. PersianGravity admin assets do not load on unrelated wp-admin screens.
18. National ID enabled behavior remains functionally equivalent to pre-4.2.
19. Jalali enabled behavior remains functionally equivalent to pre-4.2.
20. Structured Scanner enabled behavior and parser/mapping/browser lifecycle remain equivalent to 4.1.

## Evidence ceiling

Source validation claims must be bound to the integrated tree and its exact resulting PR Head; an older branch run does not validate reconciliation.

Until real WordPress/GF checks are executed, browser/admin/GF behavior remains `NOT_PROVEN` even if source/unit tests pass.

## Historical validation record

The complete pre-4.2 validation record from baseline `b1a52c975988bf60f473d4b843de057ed5bd0c06` is preserved byte-for-byte as `docs/VALIDATION_HISTORY_4.1.md`. It remains historical evidence and is not promoted to proof of 4.2 behavior.

## PR #8 semantic reconciliation

Work unit: `WU-PR8-SEMANTIC-RECONCILIATION-01`. Normal merge of main `62ee8b2808640578bfdba0583342d29b7ef8a164` into PR #8 starting at `7aa31708c38deffd98cd99752a75fdf2297ee0cf`; both histories are preserved. Active identity remains **4.2.0**, with exactly six modules, the current module-state gates, bilingual admin/help and own-plugin GNU-gettext assets. Localization is immediate, load-free, cross-cutting infrastructure outside the module registry. No PR merge into main, tag or release is performed.

| Independent dimension | Status | Evidence / boundary |
| --- | --- | --- |
| RUNTIME_PROVIDER_STATUS | PARTIAL | Shared core implemented; empty production source scaffolds and no approved JS handles |
| PHP_PRECEDENCE_STATUS | AUTOMATED_PASS | Actual Core gettext/JIT/controller with synthetic provider and upstream catalogs, all three manifest domains |
| JS_PRECEDENCE_STATUS | CONTENT_COMPOSITION_PASS; PRODUCT_LIFECYCLE_NOT_PROVEN | Pure Jed composition/pass-through tests; no invented vendor handle is tested |
| DISCOVERY_STATUS | AUTOMATED_PASS | Provider-only JIT, upstream custom path retained, unmanaged/non-fa_IR pass-through |
| CATALOG_BUILD_STATUS | AUTOMATED_PASS_FOR_SCAFFOLDS_AND_SYNTHETIC_FIXTURES | Pinned PO/MO dependency, reproducible PHP/JSON, PO unchanged, no fake production output |
| TRANSLATION_CONTENT_COVERAGE | SOURCE_UNAVAILABLE_EMPTY_SCAFFOLD | Each product PO: 0 translated / 0 untranslated / 0 fuzzy; actual product totals and coverage unknown |
| VENDOR_SURFACE_DRIFT_STATUS | NOT_EXECUTED_PACKAGE_UNAVAILABLE | Internal metadata/artifact/manifest checks are separate from exact source verification |
| REAL_INTEGRATION_STATUS | NOT_PROVEN_REAL_INTEGRATION_ENVIRONMENT_UNAVAILABLE | No licensed WordPress + GF/Flow/View site/browser execution |

### Integrated validation execution

Executed on 2026-09-08 with PHP 8.3.6, Composer 2.8.12, Node and GNU gettext:

| Required local check | Result |
| --- | --- |
| `composer validate --strict` and locked `composer install` | PASS; dependency graph unchanged |
| GNU `msgfmt --check`, `msgcmp`, and exact generated/committed MO comparison | PASS; existing PO header warnings for missing revision date, translator and language team |
| Shipped PHP syntax, including `languages/` | PASS; 19 PHP files |
| `composer cs` | PASS; 19 shipped PHP files |
| `composer compat` | PASS; configured PHP 8.2+ baseline |
| Scanner JavaScript tests | PASS; 15 tests |
| `composer i18n:check` | PASS; dormant source, provenance and artifact state unchanged |
| Pinned WordPress 6.7.2 (`6abb46bec8b58abba32602738a785ba1c83f2a1c`) | PASS; 97 localization contract checks |
| Pinned WordPress 7.1 (`b998fef9238af183f9523b3df71618e6e57498b6`) | PASS; 97 localization contract checks |
| Combined `composer test` | PASS; 72 tests, 708 assertions |

The integrated suite preserves every current-main test and PR #8 localization test.
An additional bootstrap regression verifies resolver registration with all six module
states disabled and all optional vendors absent. The runtime-integrity test now covers
all admin PHP files and uninstall code as well as includes; the legacy hook ban remains.
The workflow contains the union of the own-catalog GNU-gettext gate and pinned Core/provider
checks, plus the existing quality gates and PHP 8.2/8.3/8.4/8.5 unit jobs.

The two Core checkouts are read-only dependencies. Their translation implementations run
with host/DB/cache services stubbed; no licensed product integration is implied. Exact
resulting-head GitHub Actions and ancestry evidence are recorded in PR #8 after execution.
A fresh independent review of that exact Head remains required before any owner merge decision.

## WU-PGR-GF-FLOW-REAL-SOURCE-ADMISSION-01

Required and observed base: `b35453e68a1e71063d8f4212bed4f62791ca856e`.

| Evidence | Gravity Forms | Gravity Flow |
| --- | --- | --- |
| Observed version | 3.1.1.1 | 3.1.0 |
| Package SHA-256 | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` |
| Vendor POT SHA-256 | `a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf` | `09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961` |
| Source status | `PACKAGE_INSPECTED_METADATA_ONLY` | `PACKAGE_INSPECTED_METADATA_ONLY` |
| Vendor authenticity | `NOT_PROVEN` | `NOT_PROVEN` |
| Source-backed / vendor keys | 4207 / 4208 | 1098 / 1098 |
| Missing from vendor POT | 0 | 0 |
| Vendor-POT-only stale | 1 | 0 |
| Context / plural differences | 0 / 0 | 0 / 0 |

GravityView 3.3.4 remains `PACKAGE_UNAVAILABLE` / `NOT_ADMITTED_PACKAGE_UNAVAILABLE`.

Static source extraction used a deterministic PHP tokenizer plus byte-level reconciliation for distributed JavaScript and plugin-header strings. Temporary WP-CLI 2.12.0 regeneration was attempted but not executed because the environment blocked the tool download; this is recorded as a tooling gap, not a passed check.

Gravity Forms source confirms `gravityforms`, its explicit `load_textdomain()` path, `load_plugin_textdomain()`, one native script-translation attachment, and multiple `wp_localize_script()` surfaces. Gravity Flow confirms `gravityflow`, parent/add-on translation lifecycle, no native `wp_set_script_translations()` call in the admitted target, and multiple PHP-localized classic-script surfaces. Script Modules are `NOT_PRESENT_IN_TARGET_SOURCE` for both.

The committed Surface Registry contains 13 source-backed surfaces: six Gravity Forms and seven Gravity Flow. GF message census: 4207 unique, 1759 classified, 2448 unclassified, 55 multi-surface. Flow: 1098 unique, 732 classified, 366 unclassified, 30 multi-surface. Classification is explicit source-path-rule driven with no hidden semantic fallback.

RTL/Bidi inspection is source-informed but visual execution remains `NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`; no production RTL patch is included. Terminology baseline contains 5 common provisional entries with both-product evidence, 2 Gravity Forms extensions, 3 Gravity Flow extensions, and 9 anti-glossary tokens; `Entry` is not hard-locked.

Metadata-only scope invariants are enforced by `tools/i18n/admission.php`, `tools/i18n/build.php`, `SourceAdmissionTest`, and `CatalogBuildTest`: production product translated `msgstr` = 0; product MO = absent; product `.l10n.php` = absent; product translation JSON = absent; active product JS handles = 0; production RTL patches = 0; vendor ZIP/source/full POT committed = 0; PGR version bump = none. `source_pot_sha256` remains null in metadata-only state because no full source POT is committed; the inspected POT identity is held in `vendor_pot_sha256`.

These were source-admission-phase invariants at that historical checkpoint; later bounded Content Admission v2 records supersede the zero-content state only for their explicitly admitted surfaces. They are not a claim that current Gravity Forms or Gravity Flow production content authority is zero.

The exact PR-head CI for this work unit is the completion authority for repository tests, existing pinned WordPress localization contracts, admission consistency, Surface Registry validation, glossary evidence, and runtime integrity. A green static/source CI run still does not prove licensed browser/runtime RTL behavior.

## WU-005 — Gravity Flow Status bounded content admission

Required base: `main@8c8a7642fa8362775bc0730c6db3e00dede3eb3a`.

This Work Unit changes only Gravity Flow translation-content authority. Source admission remains separately pinned. Production revision-2 authority must contain exactly Inbox plus Status, with no third content record. The Status path rule is exactly `includes/pages/class-status.php`.

Deterministic preparation and independent reproduction establish these locked values before exact-final-Head CI:

| Evidence | Value |
| --- | --- |
| Reviewed Gravity Flow PO | `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9` |
| Source census | `1098` |
| Inbox admitted count | `255` |
| Inbox keyset | `446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a` |
| Inbox path fingerprint | `6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09` |
| Inbox translation fingerprint | `6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f` |
| Status admitted count | `43` |
| Status keyset | `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d` |
| Status path fingerprint | `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed` |
| Status translation fingerprint | `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7` |
| Inbox/Status identical overlap | `10` |
| Aggregate admitted unique count | `288` |
| Aggregate keyset | `58167ac415f0367a5a64dc098273b81bdca07a38641deaf75fd29ff4be42f363` |
| Aggregate translation fingerprint | `00c79c563019f01f68e0c03c9671e3587779cd6757fb830c53d1c2da4ecb4139` |
| Aggregate PO | `bc52c11763b536e44e977a1417d9096a1e3086f016b0a31206291340f411b757` |
| Generated MO | `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640` |
| Generated `.l10n.php` | `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44` |

The aggregate admitted count `288` is intentionally reported separately from the full Gravity Flow source census `1098`; no full-product coverage claim is implied. Gravity Flow native JS handles remain zero and no Gravity Flow translation JSON is generated. Provider-first/upstream-fallback semantics are unchanged.

`GravityFlowInboxContentAdmissionTest`, `GravityFlowStatusContentAdmissionTest`, the generic revision-2 content-admission regression suite, `composer i18n:check`, the PHP 8.2–8.5 CI matrix, and pinned WordPress Core localization contracts are the automated completion gates. Exact PASS/FAIL claims belong to the final PR Head only. Real WordPress + licensed Gravity Flow browser/UI validation remains `NOT_EXECUTED` unless separately evidenced.

## WU-007 / PR #15 Gravity Forms frontend bounded content

Gravity Forms production Content Admission v2 authority is exactly one record:

`gravityforms::frontend_runtime::shortcode:gravityform`

The registered source-path rule is exactly `form_display.php`; the admitted set remains 41 canonical identities from the independently admitted 4207-message source census. The keyset remains `827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb` and the source-path fingerprint remains `5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177`.

The WU-003 reviewed full Persian baseline SHA-256 `2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1` remains historical reviewed-input provenance. PR #15 applies exactly seven locked semantic corrections to the admitted sparse provider source; the final corrected translations are therefore not claimed to be a byte-for-byte untouched subset of WU-003. No msgid/context/plural/surface expansion is authorized.

The repaired sparse and aggregate PO SHA-256 is `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`; its translation-content fingerprint is `1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6`. `GravityFormsFrontendContentAdmissionTest` mechanically binds all seven exact English=>Persian mappings while retaining the 41-identity/keyset/path contract and non-admitted fallback check.

Gravity Flow regression tests are product-scoped: the global manifest may contain the Gravity Forms record, while the Gravity Flow production subset must remain exactly Inbox plus Status. A third Gravity Flow production record fails that exact-set assertion. Dedicated Status fixtures filter to Gravity Flow records before validation so removing Status restores Inbox-only authority without unrelated Gravity Forms fixture files. The generic content validator and its fail-closed test coverage are unchanged.

Gravity Forms runtime script handles remain empty and no Gravity Forms translation JSON is generated. `composer i18n:build` remains the sole deliberate generation path for `.mo`, `.l10n.php` and generated `metadata.json`; `composer i18n:check` is the deterministic non-mutating drift gate. Exact build/check/CI results are bound to the resulting PR state and must not be inferred from object existence alone.

Real licensed WordPress + Gravity Forms browser/integration validation remains `NOT_PROVEN` unless separately executed and recorded. This bounded admission is not full-product Gravity Forms localization.

## WU-006 — GravityView first bounded frontend content admission

Required base: `main@1a7108eea20ddd1d84680cc64e73e4c824f08811`.

GravityView production Content Admission v2 authority now contains exactly one record:

`gravityview::frontend_runtime::shortcode:gravityview`

The registered source-path rule is exactly `src/Shortcode/GravityViewShortcode.php`. The admitted set is exactly the two WU-004 source-proven canonical identities `3fe848f1b629acdcb7bfd703d8a9787cf579eabb1fc3db0c1ba7bae368ed86de` and `8d69b8871a337eb30ca4da453d62b8fbae894f7859cbdae3129485f8ded2588c`, from the separately admitted `gk-gravityview` source census of 3127 canonical identities.

The exact reviewed translation authority is final WU-002 baseline SHA-256 `2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca`. The full baseline contains 3281 reviewed canonical identities; only the two identities with exact target-surface source evidence are admitted here. Their Persian translations are copied unchanged from that baseline.

Deterministically derived fingerprints for the bounded record/aggregate are:

| Evidence | Value |
| --- | --- |
| Admitted count | `2` |
| Admitted keyset | `061ddb2324a950ef0e64f9c252cec438403031c7a07a5c235dee5e71594729db` |
| Surface-path fingerprint | `881e5750f77fbd1fedb4677d2e0775488ee9ac19ee42679e46ab6c045fe2668c` |
| Translation-content fingerprint | `293073a2b4d1a49b8c5becb59fe1f00f5c83e5b578b8b27de57749792368d13d` |
| Sparse / aggregate PO | `3232e2363aeebab7b83af786b2dfd17a2748b4918561e5123bd9cde900387fb4` |
| Generated MO | `9ba47e194420791882ceef7e183e65f441e185933a8c5fccfdfa7304b0baeeb7` |
| Generated `.l10n.php` | `f6896f38496a97074f079c173683f63f1160dbf5bfb381a319d8f2c283e7de37` |

The exact source package contains no vendor POT. The content validator therefore permits `vendor_pot_sha256=null` only as a representable value; the record still must bind exactly to the already-validated source-admission record. Products with a vendor POT continue to require their exact 64-hex POT hash, and existing hash-drift regression coverage remains in force.

`gk-query-filters` remains a separate bounded dependency/domain and is not merged into `gk-gravityview` or runtime-activated. The GravityView product script map remains empty, zero native JS translation handles are activated, and no GravityView translation JSON is generated. Non-admitted GravityView identities remain absent from the sparse provider and therefore preserve the existing upstream/vendor/TranslationsPress fallback, then source English.

`GravityViewContentAdmissionTest`, the generic Content Admission v2 suite, `CatalogBuildTest`, `composer i18n:check`, the PHP 8.2–8.5 test matrix, code style/compatibility checks, and pinned WordPress localization contracts are the automated repository evidence classes for the exact PR Head. Exact PASS/FAIL claims are recorded only after those checks execute on that Head.

Real WordPress + licensed GravityView browser/UI validation remains `NOT_RUN` / `NOT_PROVEN`. This Work Unit establishes bounded `CONTENT_ADMITTED_PARTIAL` evidence only; it does not establish full GravityView translation coverage, product completion, ecosystem completion, or Work Unit/project closure.
