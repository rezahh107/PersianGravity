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

## Gravity Flow discovery boundaries

Exact Gravity Flow 3.1.0 source/runtime discovery is collected in the existing WU008 exact-package lane. The discovery target includes Inbox `date_created`, `last_updated`, `due_date`; Status `date_created`, `workflow_timestamp`, `due_date`; Entry Detail schedule/due/expiration; Timeline/history; and Print.

Inbox and Status display-hook candidates are not admitted merely because a filter name or a `*_human_readable` value exists. Qualification must prove that the proposed presentation seam is distinct from raw compare/sort/filter/query/workflow values and must establish source timezone semantics.

Workflow due/schedule/expiration timestamps remain workflow-owned operational data. No adapter may alter raw timestamps, chronological comparisons, deadlines, schedules or expiration semantics.

Print does not get a print-specific calendar engine. It remains `NOT_PROVEN` until an admitted underlying Entry Detail/Timeline presentation seam is shown to propagate correctly through authentic print rendering.

## GravityView discovery boundaries

GravityView 3.3.4 `date_created` and `date_updated` are candidates only. Exact source/runtime discovery must identify a supported output-only filter/context, establish timezone semantics, and prove that query/search/sort/filter continue using raw host fields. Browser proof is required before support is admitted.

## Independent evidence

The same current-Head WU008 WordPress/Playwright environment may collect both G-009 and G-008 evidence, but the outputs are independent. RTL/BiDi results do not promote Jalali support, and source discovery of a date seam does not promote RTL support.

No Gravity Flow or GravityView production date adapter is added by this foundation/discovery batch.
