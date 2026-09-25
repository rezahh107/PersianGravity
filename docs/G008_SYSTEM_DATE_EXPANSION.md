# G-008 — System-date expansion and bounded Gravity Flow Inbox/Status/Entry Detail/Timeline admission

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

## Exact Gravity Flow 3.1.0 adversarial requalification

The adversarial review reopened the Entry Detail workflow-info date family and separated schedule, Timeline and Print instead of preserving the earlier blanket residual conclusion. Exact authority remains Gravity Flow `3.1.0`, package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`.

- Entry Detail workflow-info Submitted / Last Updated / Due / Expiration are now `ADMITTED_VERIFIED`. Exact source proves `gravityflow_date_format_entry_detail` supplies the shared date format for all four values, Flow delegates to `Gravity_Flow_Common::format_date()`, Gravity Forms delegates to `GFCommon::format_date()`, and WordPress `date_i18n` exposes the downstream presentation callback. The qualified production adapter returns a unique escaped marker plus the native GF date format only for exact Flow 3.1.0, then acts only on that request-local exact marker at `date_i18n`. WordPress supplies a localized timestamp-plus-offset on this path; the adapter reads its UTC components with `gmdate()` as the already-local Gregorian civil date, avoiding a second timezone conversion, converts only the date through `PGR_Jalali_Presentation::format_date()`, and preserves native time text.
- The composed mechanism was qualified at Tehran local-midnight boundaries with distinct due and expiration timestamps, enabled/disabled modes, exact-version and forced version-drift states, out-of-range and forced conversion-failure fallback, repeated rendering, unrelated WordPress `date_i18n()` and Gravity Forms formatting, DB/GFAPI/REST equality, workflow/deadline/overdue/expiration state, CSV/export isolation, equal operational getter counts, and zero nested presentation re-entry. Every fallback strips the marker and reproduces native output. Because the format hook scopes all four workflow-info dates, all four were independently qualified rather than silently widening due/expiration support.
- Status `due_date` remains `FINAL_NO_ADMISSION` for exact 3.1.0: `column_due_date()` directly calls the operational `get_due_date_timestamp()`, formats and echoes it without the admitted table-only value/proof seam. CSV/export is a separate branch and the due timestamp filter is operational and also feeds overdue classification.
- Entry Detail Scheduled is a separate `FINAL_NO_ADMISSION` surface. Authentic explicit `date`, `date_field`, delay-relative and empty `date_field` branches prove the host-owned type-specific display and equal enabled/disabled operational getter behavior. `display_queued_step_details()` reads `get_schedule_timestamp()` and directly prints the Scheduled value; no supported downstream value-only presentation hook exists. Delay scheduling uses the step-scoped `workflow_step_<step_id>_timestamp` authority, not generic `workflow_timestamp`.
- Timeline/history is `RUNTIME_PROVEN + ADMITTED_VERIFIED` for exact Flow `3.1.0` and GF `3.1.1.1` through `PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter`. The calendar adapter arms only on the qualified contiguous `get_note_header()` → `Gravity_Flow_Common::format_date()` → `GFCommon::format_date()` renderer path, validates the four exact source fingerprints and approved date-format profiles, and consumes a request-local one-shot marker at `date_i18n`. It replaces only the localized Gregorian date component.
- Timeline time-digit presentation is a separate bounded concern inside the existing Timeline adapter. It requires the same exact Flow/GF/source authority plus `fa_IR`, captures only the authentic Timeline row's host `get_default_time_format()` lookup, and shapes ASCII digits only when the matching time `date_i18n()` call occurs on that same note/Entry/form/ordering context. It does not parse the time, calculate a new timestamp, change timezone handling, or touch note/event bodies. Existing Persian digits are unchanged; unsupported locale/source/version/caller/context states remain native.
- Authentic multi-note browser evidence maps each fixture event to exactly one row-local header/body pair. Enabled Persian headers must contain Persian time glyphs at the `11:59`/`12:01` boundaries while the disabled and English controls remain ASCII. The body vector stays byte/text-equivalent between Persian enabled and disabled modes, row IDs/order and duplicate-timestamp identities remain stable, date-looking user text is untouched, and Timeline DOM attributes are unchanged. Marker-leak checks remain hard gates.
- Print owns no independent date formatter, calendar adapter or time-digit engine. `Gravity_Flow_Print_Entries::render()` reuses optional `Gravity_Flow_Entry_Detail::timeline()`, so `gravityflow.print` inherits the already verified Timeline calendar and bounded time-digit presentation for the same mode. Its Timeline headers/bodies/order must match Entry Detail Timeline, the workflow sidebar remains absent, and no independent Print conversion path is introduced.

`g008-timeline-print-qualification.json` is the dedicated authoritative runtime artifact for the admitted Timeline and inherited Print presentation. `g008-flow-residual-no-admission.json` is authoritative only for the genuinely residual `gravityflow.status.due-date` and `gravityflow.entry-detail.schedule` no-admission claims. A final no-admission record is an evidence-qualified disposition for the exact 3.1.0 surface, not a claim of permanent impossibility: discovery of a new supported bounded seam even in the same exact package requires fresh qualification. Workflow deadline/schedule/expiration/history truth remains owned by Gravity Flow/Gravity Forms.

## GravityView 3.3.4 date qualification

Exact GravityView `3.3.4` package SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` now has bounded source plus authentic runtime/browser qualification for both `gravityview.date-created` and `gravityview.date-updated`. This batch still adds **no production GravityView system-date adapter**; both registry rows therefore retain `support_state: NOT_PROVEN` while recording the independent exact-version disposition `QUALIFIED_FOR_PRODUCTION_ADAPTER`.

Exact source proves `DateCreated::get_content()` reads the authoritative Entry `date_created` value and formats it through `GVCommon::format_date()`, while `DateUpdated` inherits that renderer under its distinct `date_updated` identity. After native rendering, `Template_Field::field_output()` exposes the supported field-specific filters `gravityview/template/field/date_created/output` and `gravityview/template/field/date_updated/output` with `Template_Context`, including field identity and the Entry object. Gravity Forms `3.1.1.1` defines `date_created` as UTC `Y-m-d H:i:s`; its REST/write contracts likewise establish `date_updated` as UTC. The qualification prototype therefore reads the raw Entry property from context and passes a strict UTC `DateTimeImmutable` to the existing `PGR_Jalali_Presentation` facade instead of parsing GravityView's localized display string.

The disposable WU008 fixture sets WordPress to `Asia/Tehran` while PHP remains `UTC` and uses timestamps that cross local midnight. Authentic rendering proves GravityView first maps the UTC system instant to site-local civil time, and the bounded prototype produces the corresponding Jalali date/time only under `fa_IR`, exact GravityView `3.3.4`, the exact field-specific hook and enabled `jalali_presentation`. Module-disabled, `en_US`, and forced-version-drift controls remain native. The prototype is an MU-plugin fixture copied only into the disposable runtime and is deleted before the pre-existing Flow regression lane.

Machine semantics remain host-owned in the qualification: DB, GFAPI and REST `date_created`/`date_updated` values are byte/text-equal across enabled and control modes; raw GFAPI sorting and authentic GravityView ascending/descending sorting are unchanged for both fields; the host-native `entry_date` search path for `date_created` returns the same boundary-sensitive result set in every mode; and an unconfigured direct `filter_date_updated` request remains the same native no-op in every mode. That latter check is deliberately narrow and does not claim exhaustive browser coverage of every optional `date_updated` search configuration. Row attributes, sort links, entry identity/order, repeated rendering and user-authored date-looking text remain stable. The authoritative qualification artifact is `g008-gravityview-date-qualification.json`.

Independent exact-version dispositions:

- `gravityview.date-created`: `QUALIFIED_FOR_PRODUCTION_ADAPTER`
- `gravityview.date-updated`: `QUALIFIED_FOR_PRODUCTION_ADAPTER`

These dispositions mean a future production adapter may be implemented against the qualified exact seams with the demonstrated fail-closed constraints. They do **not** mean either GravityView surface is currently admitted or supported in production.

## G-009 remains independent

The same WU008 infrastructure carries G-009 RTL/BiDi qualification, but the evidence domains remain independently attributable. The existing `fa_IR` RTL scenarios, `en_US` LTR controls, Gravity Forms/Gravity Flow/GravityView qualification and `gform_admin` disposition must continue to pass without being reclassified by G-008.
