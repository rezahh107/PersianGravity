# G-008 — System-date expansion and bounded Gravity Flow Inbox/Status admission

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

The currently admitted Gravity Flow Inbox system-date presentations are exactly:

- `gravityflow.inbox.date-created`
- `gravityflow.inbox.last-updated`
- `gravityflow.inbox.due-date`

All three are exact-version support for Gravity Flow `3.1.0` package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`. No compatibility claim is made for later Gravity Flow versions.

The production adapter is `PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter`. It hooks only `gravityflow_inbox_field_value` and recognizes only these human-readable display identities:

- `date_created_human_readable`
- `last_updated_human_readable`
- `due_date_human_readable`

It deliberately ignores raw `date_created`, raw `last_updated`, raw `due_date`, Status output, Entry Detail, Timeline/history, Print and arbitrary date-looking strings.

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

### `due_date` source, deadline and timezone contract

The exact Gravity Flow 3.1.0 current step owns the due-date authority through `get_due_date_timestamp()`. Its documented result is a UTC timestamp. The Inbox task model uses that same result in two separate channels:

- raw `due_date` is the Unix epoch value used by AG Grid as a `date` compare value;
- `due_date_human_readable` is formatted separately from the same epoch for display.

When the current step has no due date, the authentic Inbox contract is raw `due_date = 0` and `due_date_human_readable = '-'`. The adapter preserves that sentinel exactly.

The same current-step `get_due_date_timestamp()` drives `is_overdue()`, which compares the due epoch with `time()`. Inbox overdue highlighting is then based on `is_overdue()` plus the host-owned highlight setting. Gravity Flow can derive a due timestamp from configured date, date-field or delay timing; that calculation remains entirely host-owned.

The adapter runs only after Gravity Flow has computed the Inbox cell value and operational due/overdue state. It does not parse `due_date_human_readable`, write due state, hook `gravityflow_step_due_date_timestamp`, change scheduling/deadline calculation or replace the raw compare value. It only re-reads the current step's authoritative timestamp at the admitted display seam, constructs an absolute instant and delegates site-time localization plus Jalali formatting to `PGR_Jalali_Presentation::format_datetime()`.

## Exact runtime/browser qualification

The existing current-Head WU008 WordPress/Playwright lab is reused; no second integration lab exists.

The lane installs and hash-verifies the exact Gravity Flow 3.1.0 package, the exact current PersianGravity production ZIP, Gravity Forms 3.1.1.1 and GravityView 3.3.4. It creates an authentic Gravity Flow approval workflow and three deterministic pending Inbox entries assigned to the authenticated test user.

The site timezone is set to `Asia/Tehran` while PHP/WordPress default timezone is asserted as `UTC`. The Inbox fixture includes an overdue due date whose UTC instant crosses into the next Tehran civil day, a future due date and a no-due-date current step. A test-only MU plugin uses Gravity Flow's supported `gravityflow_step_due_date_timestamp` filter only to pin the two due epochs across separate HTTP requests; production PersianGravity does not register that hook. The overdue and future epochs are separated by years, so the native host `time()` comparison is not near a boundary during qualification.

With `jalali_presentation` enabled, the browser must observe the exact Jalali values generated through the admitted adapter. With the module disabled, the browser must observe the exact Gravity Flow native values generated by the same exact vendor runtime. The no-due-date row must remain `-` in both modes.

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
- authoritative current-step due-date epoch and due-date enabled state;
- overdue classification and due-date highlight configuration;
- authentic Inbox query IDs and count;
- AG Grid raw `date_created` / `last_updated` / `due_date` compare values;
- visible ascending/descending sort behavior for all three admitted Inbox columns;
- quick-filter behavior using the exact raw `due_date` compare value.

The display and raw channels are distinct in the exact host source: each admitted column has a raw `field` and a separate `displayKey`. The adapter is invoked only for the human-readable display identity, so no query, workflow, assignment, storage, deadline, overdue, compare, sort or filter value is replaced by Jalali text.

## Fail-closed host/version behavior

Production admission is exact to the existing Gravity Flow version authority in `includes/localization/products.php`; the adapter does not create another version SSOT.

The adapter requires the host's Gravity Flow version/basename authority to resolve to the admitted product/version. Version or host identity drift returns the native value. Display identity or hook/seam drift likewise prevents conversion because the adapter recognizes only exact admitted human-readable IDs and only runs through the exact Inbox filter.

For `due_date`, native output is also retained when Gravity Flow API/current-step authority is unavailable, due dates are disabled, `get_due_date_timestamp()` is missing or returns a malformed/zero timestamp, the result is outside the validated range, or facade/timezone conversion fails. Unit coverage proves the existing date-created/last-updated malformed/missing/range fallbacks as well. The WU008 registry/evidence reconciliation binds committed runtime-admitted Flow Inbox claims to `g008-flow-inbox-admission.json` and exact current-Head/package identity.

## Gravity Flow 3.1.0 Status table — admitted surfaces

The Status-table admissions remain exactly:

- `gravityflow.status.date-created`
- `gravityflow.status.workflow-timestamp`

The production adapter is `PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter`. It does not infer table-vs-export authority from an intermediate `gravityflow_status_args` snapshot. Exact Gravity Flow 3.1.0 takes the final render branch only after all `gravityflow_status_args` callbacks complete. On the table branch, each admitted column then calls `gravityflow_entry_url_status_table` immediately before `gravityflow_field_value_status_table`; PersianGravity uses that table-only seam to issue a one-shot token bound to the same form/entry and consumes it at the value filter. `gravityflow_status_args` is retained only to clear stale proof at render start. Missing proof or unfamiliar/direct value-filter context stays native.

The exact 3.1.0 Status source proves `date_created` is the Gravity Forms Entry UTC `Y-m-d H:i:s` source. The browser column formats that raw Entry property before the value filter, while Status sorting, query construction, and start/end filtering continue to use the raw `date_created` field. PersianGravity ignores the formatted display string, strict-parses the raw Entry value in UTC, and delegates site-time localization and Jalali formatting to `PGR_Jalali_Presentation`.

The exact 3.1.0 Status source also proves `workflow_timestamp` is numeric Gravity Flow Entry meta containing a Unix epoch instant. The native Status formatter receives that numeric source; in the qualified runtime PHP's default timezone is UTC before Gravity Forms localizes display to the WordPress site timezone. PersianGravity constructs the instant directly from the raw epoch and delegates site-time localization to the shared facade.

Exact-package source also proves the Status CSV exporter invokes `gravityflow_field_value_status_table` directly but never calls `gravityflow_entry_url_status_table`. This post-branch proof boundary therefore distinguishes the actual table path from export without depending on filter priority or an intermediate format snapshot. Authentic WU008 evidence shows ordinary CSV remains native/raw and adds adversarial `PHP_INT_MAX` late mutators in both directions: `table → csv` remains completely native, while `csv → table` reaches the admitted Jalali table presentation when the module and all other gates pass.

The shared WU008 fixture runs with WordPress site timezone `Asia/Tehran` and PHP default timezone `UTC`. Authentic browser evidence proves exact Jalali presentation for all three deterministic entries when enabled, exact native Gravity Flow presentation when disabled, and a local civil-day boundary case where Status filtering for `2026-03-21` returns only the entry created at `2026-03-20 22:15:00` UTC.

Operational evidence additionally proves enabled/disabled equality for DB, GFAPI and REST values, workflow step/final status and assignees, Status query IDs/count, ascending/descending sorting on both target raw keys, Status start/end filtering, and ordinary CSV output. Module-disabled late `csv → table` also remains native. The admission artifact is `g008-flow-status-admission.json`.

## Gravity Flow surfaces still not admitted

The following remain deliberately outside this production batch:

- Status `due_date` — `NOT_PROVEN`.
- Entry Detail due/schedule/expiration — `NOT_PROVEN`.
- Timeline/history — `NOT_PROVEN`.
- Print — `NOT_PROVEN`.

No production adapter for any of those surfaces is introduced here. Workflow due/schedule/expiration timestamps outside the admitted Inbox presentation remain workflow-owned operational data.

## GravityView remains unproven

GravityView 3.3.4 `date_created` and `date_updated` remain `NOT_PROVEN`. Existing discovery evidence is retained, but no GravityView production system-date adapter is added by this batch.

## G-009 remains independent

The same WU008 infrastructure carries G-009 RTL/BiDi qualification, but the evidence domains remain independently attributable. The existing `fa_IR` RTL scenarios, `en_US` LTR controls, Gravity Forms/Gravity Flow/GravityView qualification and `gform_admin` disposition must continue to pass without being reclassified by G-008.
