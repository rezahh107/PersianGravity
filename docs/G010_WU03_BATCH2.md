# G-010 WU-03 Batch 2 — Entry Detail compatibility contracts

## Scope

WU-03 Batch 2 migrates only the existing Gravity Flow Entry Detail presentation paths:

- Jalali workflow-info dates: `gravityflow.entry-detail.submitted`, `gravityflow.entry-detail.last-updated`, `gravityflow.entry-detail.due-date`, `gravityflow.entry-detail.expiration`;
- presentation-only Persian digit shaping in the qualified Entry Detail workflow-info status box.

It does not migrate Entry Detail Scheduled calendar presentation, Timeline/history, inherited Print, GravityView, Gravity Forms, localization source admission, or package identity. The authentic package baseline remains the Owner-supplied Gravity Flow `3.1.0` package recorded by `tools/compatibility/gravityflow-package.json`.

## Root coupling removed

Before Batch 2, both Entry Detail adapters loaded localization product metadata and required the runtime `GRAVITY_FLOW_VERSION` string to equal the localization `target_version`. That made an otherwise intact presentation contract unavailable solely because the version string differed.

Batch 2 follows the WU-03 Batch 1 host-identity pattern:

- `GRAVITY_FLOW_PLUGIN_BASENAME` must be exactly `gravityflow/gravityflow.php`;
- `GRAVITY_FLOW_VERSION` must exist and be a bounded syntactically valid observation;
- the localization `target_version` is not runtime activation authority;
- localization/source-admission policy itself is unchanged.

Synthetic version-only drift tests vary the version string while keeping the relevant behavioral contract intact. They prove only that exact-version equality is no longer the runtime oracle. They do not qualify Gravity Flow 3.1.1.1 or any other real newer package.

## Entry Detail date-family contract

The safety boundary remains the composed presentation seam, not the version string:

1. `gravityflow_date_format_entry_detail` must actually invoke the adapter.
2. A non-empty host/plugin date-format override is preserved and disarms ownership.
3. `GFCommon::get_default_date_format()` must provide a non-empty native format.
4. PersianGravity owns only the unique escaped `PGRJALALIENTRYDETAIL:` marker and the exact request-local marked format it armed.
5. `date_i18n` conversion occurs only for that exact currently armed format.
6. Every fallback strips the owned marker, including stale/unarmed/wrong-format paths.
7. The admitted WordPress semantics remain `$gmt === true` plus an integer localized timestamp-with-offset.
8. `gmdate()` reads the already-local Gregorian civil components without a second timezone shift.
9. Only the calendar date is converted through `PGR_Jalali_Presentation::format_date()`; Gravity Flow's surrounding native time text is preserved.
10. Unrelated `date_i18n` output stays native.
11. The adapter does not call or hook operational due/schedule/expiration getters and does not hook Timeline.

Wrong/missing host identity, non-empty format override, unavailable/invalid default format, stale/wrong marker context, unexpected GMT semantics, malformed timestamp, out-of-range date, missing conversion facade or conversion failure fall to native presentation without marker leakage.

## Truthful date diagnostics attribution

The four date capability IDs already exist in `PGR_Gravity_Flow_Compatibility_Diagnostics`; no new date IDs are created.

Exact Flow 3.1.0 source/runtime qualification establishes that a complete workflow-info render routes Submitted, Last Updated, Due and Expiration through the shared date-format/date_i18n chain before `gravityflow_below_workflow_info_entry_detail`. The two shared date hooks themselves do not expose the current semantic row identity, so Batch 2 does not invent per-field attribution.

The adapter therefore records equivalent `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED` observations for all four IDs only when the diagnostics-only post-render observer sees exactly four successful marked calls in the complete render. A partial or mixed failure that cannot be assigned to a specific row becomes family-wide `DEGRADED / PGR-GFLOW-CONTEXT-UNAVAILABLE`. Uniform qualified failures use the existing bounded reason where attribution is truthful, including host-unqualified, source-invalid and conversion-unavailable.

The post-render observer is reporting-only. It is not required to authorize calendar conversion. The adapter never reads `status_snapshot()`, `pgr_gravityflow_compatibility_latest`, or any persisted diagnostic state.

## Persian digit-shaping contract

PHP eligibility remains bounded to:

- actual `gravityflow_below_workflow_info_entry_detail` invocation;
- `fa_IR` locale;
- exact canonical Gravity Flow plugin basename plus bounded valid runtime-version observation;
- available `PGR_URL` and `PGR_VERSION` assets;
- one-time enqueue semantics.

The browser adapter is intentionally unchanged. It continues to require the exact root:

`#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box`

Only `.gravityflow-status-box-field` descendants are scanned. Only visible text nodes are eligible. Script/style/textarea/select/option/template/noscript, hidden, `aria-hidden`, contenteditable and CSS-hidden descendants are excluded. Only ASCII digit glyphs are substituted. Attributes, URLs, control values, IDs, `data-*`, hidden machine state and semantic numeric values are never rewritten.

A missing or drifted qualified root safely does nothing. Entry Detail Scheduled text may receive digit glyph shaping if it is visibly rendered in this status box; that does not change the separate `gravityflow.entry-detail.schedule` calendar `FINAL_NO_ADMISSION` disposition.

## Digit diagnostics identity decision

No `gravityflow.entry-detail.persian-digits` diagnostics capability is added in Batch 2.

Repository evidence establishes a browser presentation behavior but no existing shared diagnostics identity for it. PHP can truthfully observe only enqueue eligibility, not whether the exact browser root and visible-text-node contract succeeded. Treating PHP enqueue success as browser-contract `AVAILABLE` would overstate evidence. Adding AJAX, remote telemetry, persistent browser state or invasive DOM reporting solely for System Status is not justified.

Accordingly, digit shaping remains covered by focused JS tests and WU008 authentic browser evidence. Cross-request System Status may remain without a dedicated digit-shaping capability rather than forcing observations into an unrelated date ID.

## Isolation and preservation

- Date contract failure does not disable or broaden the digit adapter.
- Digit hook/locale/asset/DOM failure does not disable the date adapter.
- DB/GFAPI/REST/query/export/workflow/assignment/deadline/overdue/expiration/schedule semantics remain host-owned.
- Entry Detail Schedule Jalali calendar admission remains unchanged.
- Timeline/history exact version/source/caller-chain guards remain unchanged for WU-04.
- Print continues to inherit only the verified Timeline renderer and is not migrated here.
- Canonical package authority, plugin version, Stable tag, vendor files, release state and deployment state are unchanged.

## Validation boundary

Focused PHP/JS tests cover exact 3.1.0 behavior, synthetic version-only drift with intact contracts, malformed/wrong host identity, marker lifecycle and fallback leakage, GMT/timestamp/conversion failure, non-Persian and missing-asset digit fallback, one-time enqueue, exact DOM root/field scoping, excluded descendants, and machine-state non-mutation.

Authentic acceptance still requires exact-final-Head CI, Artifact Install Smoke, G008 Jalali Presentation Runtime and WU008 Licensed Real Integration against the unchanged Owner-supplied Gravity Flow 3.1.0 package. Synthetic drift evidence must never be reported as qualification of a real newer package.
