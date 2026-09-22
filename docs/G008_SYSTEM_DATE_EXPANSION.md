# G-008 — System-date expansion foundation and discovery

## Stable conversion/consumer contract

The expansion rule remains **one conversion authority, many bounded presentation adapters**.

The existing `PGR_Gregorian_Jalali_Converter` and typed `PGR_Jalali_Presentation` facade remain the only conversion authority. `format_datetime()` accepts an authoritative `DateTimeInterface` whose source timezone semantics have already been established; `format_date()` accepts an explicitly Gregorian civil date. Both retain the existing validated product range and null/native-fallback contract.

No additional consumer API is introduced by this batch. The existing facade is already a stable feature-detectable seam: consumers can require the `jalali_presentation` module to be enabled and feature-detect `PGR_Jalali_Presentation`/its typed methods. When the module is disabled, its runtime classes are not loaded. When conversion returns `null`, the caller keeps the native host display.

A consumer must never pass an arbitrary display string or a `pgr_jalali_date` value into Gregorian conversion. `pgr_jalali_date` is already Jalali-domain data and remains independent.

## Source-backed surface registry

`tools/jalali/g008-system-date-surfaces.json` is the repository-owned development/qualification registry. It is not loaded by production and does not create a second calendar engine or runtime registry.

Each surface records:

- exact product/version/package authority;
- authentic UI surface;
- authoritative raw source field/property;
- source calendar/domain;
- source timezone semantics;
- exact presentation seam or the fact that it is not yet proven;
- sorting/filtering/query/workflow/deadline dependencies;
- native fallback;
- discovery state separately from support/admission state;
- adapter identity only after admission;
- fail-closed version/host-seam drift behavior.

`SOURCE_PROVEN` is only discovery evidence. It is not equivalent to support. New surfaces stay `NOT_PROVEN` until source/calendar/timezone/seam/side-effect evidence and exact-version authentic integration/browser evidence are sufficient.

## Existing V1 remains the regression reference

Gravity Forms 3.1.1.1 Entries List `date_created` remains the only admitted verified system-date surface. Its adapter reads the raw UTC Entry property, never the already-formatted display value, and changes only `gform_entries_field_value`. Storage, API, query, sort and filter semantics remain native.

The registry and regression tests fail closed if the exact host version, package identity or admitted presentation seam drifts. Native display remains the fallback.

## Gravity Flow discovery results

Exact Gravity Flow 3.1.0 source discovery runs inside the existing current-Head WU008 exact-package lane. It does not modify vendor files and uploads only normalized source-reference metadata.

The following candidates are now `SOURCE_PROVEN` while support remains `NOT_PROVEN`:

- Inbox `date_created`: exact source contains a distinct raw/compare value and `date_created_human_readable`; `gravityflow_inbox_field_value` is a display filter receiving value/form/id/entry context.
- Inbox `last_updated`: exact source contains a distinct raw/compare value and `last_updated_human_readable` through the same display filter.
- Inbox `due_date`: exact source contains raw due/compare data and `due_date_human_readable` through the same display filter. This does **not** authorize conversion of workflow-owned operational timestamps.
- Status `date_created`: exact source exposes the formatted display through `gravityflow_field_value_status_table` while raw/sort identity remains separately represented.
- Status `workflow_timestamp`: exact source exposes the formatted display through the same Status filter while the workflow timestamp remains a distinct underlying field.

Those source results are promising presentation-only seams, but they do not yet establish every authoritative timezone transformation or authentic Jalali browser/runtime behavior. No production adapter is admitted by this batch.

Status `due_date`, Entry Detail due/schedule/expiration, Timeline/history and Print remain `NOT_PROVEN`. Discovery found related references, but not a sufficiently narrow, side-effect-free date-presentation seam for those surfaces. Workflow due/schedule/expiration timestamps remain workflow-owned operational data; no adapter may alter raw timestamps, chronological comparisons, deadlines, schedules or expiration semantics.

Print does not get a print-specific calendar engine. The observed `gravityflow_print_styles` hook is a print asset seam, not a date-conversion seam. Print remains `NOT_PROVEN` until an admitted underlying Entry Detail/Timeline presentation adapter is shown to propagate correctly through authentic print rendering.

## GravityView discovery results

Exact GravityView 3.3.4 source contains implementations for `date_created` and `date_updated` and exposes output-filter/context infrastructure. That is not yet enough to identify the safest supported field-specific output-only seam, and `date_updated` source timezone semantics also remain unresolved.

Therefore both GravityView system-date candidates remain `NOT_PROVEN`. Admission still requires a supported output seam, explicit timezone semantics, evidence that query/search/sort/filter stay bound to raw host fields, and authentic exact-version browser/runtime proof.

## Independent evidence

The same current-Head WU008 WordPress/Playwright environment may collect both G-009 and G-008 evidence, but the outputs are independent. RTL/BiDi results do not promote Jalali support, and source discovery of a date seam does not promote RTL support.

No Gravity Flow or GravityView production date adapter is added by this foundation/discovery batch.
