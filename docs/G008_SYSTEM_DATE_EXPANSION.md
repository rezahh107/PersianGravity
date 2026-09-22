# G-008 — System-date expansion and bounded Gravity Flow Inbox admission

## Stable conversion/consumer contract

The expansion rule remains **one conversion authority, many bounded presentation adapters**.

`PGR_Gregorian_Jalali_Converter` and the typed `PGR_Jalali_Presentation` facade remain the only conversion authority. `format_datetime()` accepts an authoritative `DateTimeInterface` whose source timezone semantics have already been established; `format_date()` accepts an explicitly Gregorian civil date. Both retain the validated product range `1800-01-01..2124-03-19` and the null/native-fallback contract.

A consumer must never pass an arbitrary display string or a `pgr_jalali_date` value into Gregorian conversion. `pgr_jalali_date` is already Jalali-domain data and remains independent.

`jalali_presentation` remains opt-in. When it is disabled, its runtime presentation classes and hooks are not active. If host identity, raw source, parsing, validated range, facade availability or conversion is unsafe, the host's native display value is returned unchanged.

## Source-backed surface registry

`tools/jalali/g008-system-date-surfaces.json` is the repository-owned development/qualification registry. It is not loaded as a second runtime calendar registry.

Each surface records exact product/version/package authority, raw source/domain/timezone, the presentation seam, operational dependencies, native fallback, independent discovery/support states, admitted adapter identity, evidence provenance and fail-closed drift behavior.

`SOURCE_PROVEN` remains discovery evidence only. `RUNTIME_PROVEN + ADMITTED_VERIFIED` requires exact source semantics plus authentic exact-version runtime/browser evidence.

## Existing Gravity Forms V1 remains the regression reference

Gravity Forms 3.1.1.1 Entries List `date_created` remains admitted. Its adapter reads the raw UTC Entry property, never the formatted display string, and changes only `gform_entries_field_value`. Storage, API, query, sort and filter semantics remain native.

The existing G-008 V1 runtime workflow remains a mandatory regression lane for later expansion batches.

## Gravity Flow 3.1.0 Inbox — admitted surfaces

This batch admits exactly two Gravity Flow Inbox system-date presentations:

- `gravityflow.inbox.date-created`
- `gravityflow.inbox.last-updated`

Both are exact-version support for Gravity Flow `3.1.0` package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`. No compatibility claim is made for later Gravity Flow versions.

The production adapter is `PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter`. It hooks only `gravityflow_inbox_field_value` and recognizes only the two human-readable display identities:

- `date_created_human_readable`
- `last_updated_human_readable`

It deliberately ignores raw `date_created`, raw `last_updated`, `due_date`, Status output, Entry Detail, Timeline/history, Print and arbitrary date-looking strings.

### `date_created` source and timezone contract

The exact Gravity Flow 3.1.0 Inbox task model derives:

- `date_created_human_readable` from the Entry's `date_created` string;
- raw AG Grid `date_created` separately from that same source for comparison/sorting/filtering.

Gravity Forms 3.1.1.1 defines Entry `date_created` as `Y-m-d H:i:s` UTC. The adapter therefore strict-parses only the authoritative Entry property in explicit `UTC`, then delegates timezone localization and Jalali formatting to `PGR_Jalali_Presentation::format_datetime()`.

It never reparses the native human-readable cell string.

### `last_updated` source and timezone contract

Gravity Flow owns `workflow_timestamp` as numeric Entry meta. Its update callback produces Unix epoch timestamps and the Inbox task model uses the integer `workflow_timestamp` directly as raw AG Grid `last_updated` compare data.

The human-readable `last_updated` is derived separately from that epoch. In the qualified WordPress runtime, PHP's default timezone is explicitly proven to remain `UTC` for Gravity Flow's native `date()` intermediate; Gravity Forms formatting then applies the WordPress site timezone.

The PersianGravity adapter avoids the intermediate string entirely: it constructs an instant directly from the authoritative Unix epoch and passes it to `PGR_Jalali_Presentation::format_datetime()`. The native `-` sentinel is preserved unchanged.

## Exact runtime/browser qualification

The existing current-Head WU008 WordPress/Playwright lab is reused; no second integration lab exists.

The lane installs and hash-verifies the exact Gravity Flow 3.1.0 package, the exact current PersianGravity production ZIP, Gravity Forms 3.1.1.1 and GravityView 3.3.4. It creates an authentic Gravity Flow approval workflow and three deterministic pending Inbox entries assigned to the authenticated test user.

The site timezone is set to `Asia/Tehran` while PHP/WordPress default timezone is asserted as `UTC`. The fixture intentionally includes timestamps that cross local civil-date boundaries. With `jalali_presentation` enabled, the browser must observe the exact Jalali values generated through the admitted adapter. With the module disabled, the browser must observe the exact Gravity Flow native values generated by the same exact vendor runtime.

Primary machine-readable evidence is emitted as:

- `g008-flow-inbox-fixture-baseline.json`
- `g008-flow-inbox-state-enabled.json`
- `g008-flow-inbox-browser-enabled.json`
- `g008-flow-inbox-state-disabled.json`
- `g008-flow-inbox-browser-disabled.json`
- `g008-flow-inbox-admission.json`

Screenshots are supplemental only.

## Operational non-interference

The admission gate compares enabled and disabled runs and fails if presentation changes any operational state covered by the fixture.

The exact runtime proves equality of:

- stored Entry `date_created` values;
- `workflow_timestamp` values;
- workflow step and final-status metadata;
- assignees;
- authentic Inbox query IDs and count;
- AG Grid raw `date_created` / `last_updated` compare values;
- visible sort behavior for both target columns;
- quick-filter behavior using an exact raw `date_created` compare value.

The display and raw channels are distinct in the exact host source: each target column has a raw `field` and a separate `displayKey`. The adapter is invoked only for the human-readable display identity, so no query, workflow, assignment, storage, compare, sort or filter value is replaced by Jalali text.

## Fail-closed host/version behavior

Production admission is exact to the existing Gravity Flow version authority in `includes/localization/products.php`; the adapter does not create another version SSOT.

The adapter requires the host's Gravity Flow version/basename authority to resolve to the admitted product/version. Version or host identity drift returns the native value. Display identity or hook/seam drift likewise prevents conversion because the adapter recognizes only the exact admitted human-readable IDs and only runs through the exact Inbox filter.

Unit coverage also proves native fallback when the facade is unavailable, the source is malformed/missing, the value is outside the validated product range, or the host version/basename drifts. The WU008 registry/evidence reconciliation binds committed runtime-admitted Flow Inbox claims to `g008-flow-inbox-admission.json` and exact current-Head/package identity.

## Gravity Flow surfaces still not admitted

The following remain deliberately outside this production batch:

- Inbox `due_date` — still `SOURCE_PROVEN + NOT_PROVEN`; its deadline/scheduling semantics require independent operational qualification.
- Status `date_created` — `SOURCE_PROVEN + NOT_PROVEN`.
- Status `workflow_timestamp` — `SOURCE_PROVEN + NOT_PROVEN`.
- Status `due_date` — `NOT_PROVEN`.
- Entry Detail due/schedule/expiration — `NOT_PROVEN`.
- Timeline/history — `NOT_PROVEN`.
- Print — `NOT_PROVEN`.

No production adapter for any of those surfaces is introduced here. Workflow due/schedule/expiration timestamps remain workflow-owned operational data.

## GravityView remains unproven

GravityView 3.3.4 `date_created` and `date_updated` remain `NOT_PROVEN`. Existing discovery evidence is retained, but no GravityView production system-date adapter is added by this batch.

## G-009 remains independent

The same WU008 infrastructure carries G-009 RTL/BiDi qualification, but the evidence domains remain independently attributable. The existing `fa_IR` RTL scenarios, `en_US` LTR controls, Gravity Forms/Gravity Flow/GravityView qualification and `gform_admin` disposition must continue to pass without being reclassified by G-008.
