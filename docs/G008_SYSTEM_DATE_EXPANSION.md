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

It never modifies raw `date_created`, raw `last_updated`, or raw `due_date`; the exact Flow 3.1.0 Inbox adapter only observes raw `due_date` at the existing presentation filter long enough to bind its already-computed epoch/0 to the same form+entry for the immediately following display callback. Status output is handled only by its separately admitted adapter; Entry Detail, Timeline/history, Print and arbitrary date-looking strings remain outside this Inbox seam.

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

The same current-step `get_due_date_timestamp()` drives `is_overdue()`, which compares the due epoch with `time()`. Inbox overdue highlighting is then based on `is_overdue()` plus the host-owned highlight setting. Gravity Flow can derive a due timestamp from configured date, date-field or delay timing; exact-package source qualification binds those modes as well: date/date-field civil values are converted to GMT before epoch conversion, date-field offsets are applied to the epoch, and delay mode starts from the step-scoped `workflow_step_<step_id>_timestamp` before adding the configured unit offset.

Scheduling remains a distinct host-owned channel. Exact Gravity Flow source proves step start gating calls `validate_schedule()`, which reads `get_schedule_timestamp()` using the `schedule` namespace and the separate `gravityflow_step_schedule_timestamp` filter; the schedule getter does not call the due getter and the due getter does not call the schedule getter.

Exact Gravity Flow 3.1.0 source qualification proves `get_columns()` inserts raw `due_date` before `due_date_human_readable`, `get_data_for_row()` iterates those columns in order, and `get_column_value()` sends both results through `gravityflow_inbox_field_value`. The adapter captures only the already-computed raw integer epoch/0 under the current form+entry key, returns it unchanged, then consumes that one-shot authority for `due_date_human_readable`. It never re-runs `get_due_date_timestamp()`, parses the localized display string, writes due state, hooks/bypasses `gravityflow_step_due_date_timestamp`, changes scheduling/deadline calculation, or substitutes Jalali text for the raw compare value. Missing, malformed, mismatched or out-of-order capture returns native display unchanged.

## Exact runtime/browser qualification

The existing current-Head WU008 WordPress/Playwright lab is reused; no second integration lab exists.

The lane installs and hash-verifies the exact Gravity Flow 3.1.0 package, the exact current PersianGravity production ZIP, Gravity Forms 3.1.1.1 and GravityView 3.3.4. It creates an authentic Gravity Flow approval workflow and three deterministic pending Inbox entries assigned to the authenticated test user.

The site timezone is set to `Asia/Tehran` while PHP/WordPress default timezone is asserted as `UTC`. The Inbox fixture includes an overdue due date whose UTC instant crosses into the next Tehran civil day, a future due date and a no-due-date current step. A test-only MU plugin uses Gravity Flow's supported `gravityflow_step_due_date_timestamp` filter to pin the two due epochs and count every operational-filter execution in each browser request. If that filter is re-entered while `gravityflow_inbox_field_value` is active, the probe deliberately returns a different timestamp; this falsifies any adapter that performs an extra operational getter call. Production PersianGravity never registers that hook. The overdue and future epochs are separated by years, so the native host `time()` comparison is not near a boundary during qualification.

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
- step-scoped workflow timestamp used by delay-mode due calculation;
- due-date type, delay offset/unit and `supports_due_date()` state;
- overdue classification and due-date highlight configuration;
- scheduled flag and schedule timestamp state;
- authentic Inbox query IDs and count;
- AG Grid raw `date_created` / `last_updated` / `due_date` compare values;
- visible ascending/descending sort behavior for all three admitted Inbox columns;
- quick-filter behavior using the exact raw `due_date` compare value.

The display and raw channels are distinct in the exact host source: each admitted column has a raw `field` and a separate `displayKey`. The adapter is invoked only for the human-readable display identity, so no query, workflow, assignment, storage, deadline, overdue, step-timing, scheduling, compare, sort or filter value is replaced by Jalali text.

## Fail-closed host/version behavior

Production admission is exact to the existing Gravity Flow version authority in `includes/localization/products.php`; the adapter does not create another version SSOT.

The adapter requires the host's Gravity Flow version/basename authority to resolve to the admitted product/version. Version or host identity drift returns the native value. Display identity or hook/seam drift likewise prevents conversion because the adapter recognizes only exact admitted human-readable IDs and only runs through the exact Inbox filter.

For `due_date`, native output is retained when the exact host/version/seam is unavailable, the preceding raw value was not captured for the same form+entry, the raw representation is not the qualified integer epoch/0 shape, call ordering is not the qualified Flow 3.1.0 ordering, raw `0` represents no due date, the instant is outside the validated range, or facade/timezone conversion fails. Unit coverage also proves one-shot consumption and cross-row isolation, while existing date-created/last-updated malformed/missing/range fallbacks remain intact. The WU008 registry/evidence reconciliation binds committed runtime-admitted Flow Inbox claims to `g008-flow-inbox-admission.json`, exact current-Head/package identity, and enabled-vs-disabled operational-filter invocation parity.

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

## Exact Gravity Flow 3.1.0 residual closure

The remaining non-GravityView G-008 candidates are source-closed for exact Gravity Flow `3.1.0` package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`. The registry retains `support_state=NOT_PROVEN` because that is the existing schema vocabulary, but each record now carries `exact_version_disposition=FINAL_NO_ADMISSION`. This is not an open discovery gap and is not a compatibility claim for future Flow versions.

- Status `due_date`: `column_due_date()` directly calls the operational `get_due_date_timestamp()`, formats it and echoes it. Unlike the admitted Status columns, this table path does not call `filter_field_value()` / `gravityflow_field_value_status_table` and does not reach the table-only entry-URL proof seam. CSV/export has a separate branch and generic value filter, so intercepting that filter cannot provide a table-only presentation adapter. The due timestamp filter is operational and also feeds overdue classification.
- Entry Detail due/expiration: `maybe_display_entry_detail_workflow_info()` exposes `gravityflow_date_format_entry_detail`, but that contract changes only the shared date-format pattern passed into native `Gravity_Flow_Common::format_date()`; it does not expose the raw timestamp or a calendar/value replacement. Due and expiration are then read from `get_due_date_timestamp()` / `get_expiration_timestamp()` and printed directly. The nearby `gravityflow_below_workflow_info_entry_detail` action runs only after those values have already been emitted. Entry Detail schedule: `display_queued_step_details()` directly reads `get_schedule_timestamp()` and prints the native type-specific scheduled value. The due/schedule/expiration timestamp filters are workflow timing/state authorities, not presentation hooks.
- Timeline/history: the initial event uses Entry `date_created`; workflow notes use Gravity Forms note `date_created`, stored in UTC. `get_note_header()` formats that raw timestamp directly and has no date-only output filter. `gravityflow_timeline_notes` runs after Gravity Flow's `array_reverse()` ordering step and before rendering. Because full-array access alone is not a rejection reason, the runtime experiment clones display notes: an added display-only property is ignored downstream, while changing cloned `date_created` changes rendered text; storage, IDs, order and note bodies remain unchanged. Final disposition therefore rests on the downstream representation contract, not on array ownership by itself.
- Print: `Gravity_Flow_Print_Entries::render()` has no independent date formatter/calendar engine. It reuses `Gravity_Flow_Entry_Detail::entry_detail_grid()` and, when requested, `Gravity_Flow_Entry_Detail::timeline()`. It does not call `workflow_entry_detail_status_box()`, `maybe_display_entry_detail_workflow_info()` or `display_queued_step_details()`, so workflow-sidebar due/schedule/expiration are absent from this Print path rather than propagated. `gravityflow_print_styles` only selects CSS assets; no independent Print adapter is introduced.

WU008 now writes `g008-residual-source-probe.json`, exercises authentic Entry Detail, all scheduled-step branches, multi-note Timeline and `gravityflow_print_entries` with timelines enabled in both module states, and reconciles source/runtime/browser evidence with separate candidate, schedule and Timeline/Print gates before the registry reconciliation. Status due-date is separately asserted native in the enabled and disabled Status browser lane. Any source/version/seam drift fails the exact 3.1.0 closure and requires fresh qualification.

No production adapter is introduced for these four targets. Workflow deadline/schedule/expiration/history truth remains owned by Gravity Flow/Gravity Forms.

## GravityView remains unproven

GravityView 3.3.4 `date_created` and `date_updated` remain `NOT_PROVEN`. Existing discovery evidence is retained, but no GravityView production system-date adapter is added by this batch.

## G-009 remains independent

The same WU008 infrastructure carries G-009 RTL/BiDi qualification, but the evidence domains remain independently attributable. The existing `fa_IR` RTL scenarios, `en_US` LTR controls, Gravity Forms/Gravity Flow/GravityView qualification and `gform_admin` disposition must continue to pass without being reclassified by G-008.
