# G-010 WU-03 validation record

## Batch 1 boundary — Inbox + Status

WU-03 Batch 1 covers the direct Gravity Flow presentation-contract migration for:

- `gravityflow.inbox.date-created`
- `gravityflow.inbox.last-updated`
- `gravityflow.inbox.due-date`
- `gravityflow.status.date-created`
- `gravityflow.status.workflow-timestamp`

Batch 1 planning and execution base was `571a9d38c9a7ef6aecf329b8d1130d2adca3b28e`, the merge commit of PR #68. Its exact final-Head evidence is recorded in the merged Batch 1 history.

The authentic licensed qualification package remains Gravity Flow `3.1.0`, authority `OWNER_SUPPLIED_GOOGLE_DRIVE`, expected bytes `2603034`, SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`, plugin main file `gravityflow/gravityflow.php`. `tools/compatibility/gravityflow-package.json`, localization source-admission policy, product `target_version`, vendor files, plugin version and Stable tag are not changed by WU-03.

The G-008 registry continues to describe the exact-package authentic evidence boundary. WU-03 does not reinterpret exact 3.1.0 evidence as qualification of a newer licensed package. Its narrower runtime claim is only that, for migrated capabilities, a version-string difference is no longer an independent veto when the adapter's actual host/callback/data/context/provenance contract is intact.

### Batch 1 root cause and replacement

Before Batch 1, the direct Inbox/Status adapters loaded `includes/localization/products.php`, found the Gravity Flow product by plugin basename and required runtime `GRAVITY_FLOW_VERSION` to equal source-admission `target_version` (`3.1.0`). The adapters already had useful capability-local callback/source/provenance evidence, but a version-string mismatch rejected the callback before those contracts could establish safety.

The replacement keeps existing adapter boundaries rather than introducing a compatibility service. Both adapters require a bounded syntactically valid `GRAVITY_FLOW_VERSION` observation and exact `GRAVITY_FLOW_PLUGIN_BASENAME = gravityflow/gravityflow.php`, then independently validate the capability-local contract. Localization/source-admission target version is no longer runtime activation authority for these five capabilities.

Diagnostics remain observation-only. `PGR_Gravity_Flow_Compatibility_Diagnostics::record()` is called only after the adapter has made its own current-callback decision. The direct adapters do not read `status_snapshot()`, `pgr_gravityflow_compatibility_latest`, or previous-request diagnostic state.

### Inbox contract evidence

`date-created` requires the admitted Inbox value callback, exact `date_created_human_readable` identity, canonical Flow host identity, array Entry context, strict Gravity Forms UTC `date_created` and successful bounded Jalali conversion.

`last-updated` requires exact `last_updated_human_readable` identity and authoritative positive `workflow_timestamp` Unix instant. The native `-` sentinel remains native.

`due-date` retains the stronger one-shot provenance contract: exact raw `due_date` callback, native integer epoch/0 domain, canonical same form+entry identity, immediate matching `due_date_human_readable` callback and single consumption. The adapter never calls `get_due_date_timestamp()`, never registers `gravityflow_step_due_date_timestamp`, never parses the localized display string, and preserves raw `0` plus native display `-`.

### Status contract evidence

The Status adapter retains its existing table-only provenance sequence:

`gravityflow_entry_url_status_table` one-shot proof → matching `gravityflow_field_value_status_table` callback.

The token is bound to canonical positive form+entry identity and consumed once. `date_created` requires strict UTC Entry source; `workflow_timestamp` requires a positive Unix instant. A direct/export value-filter call has no table token and remains native. Missing/mismatched token for one capability does not disable the other when the other's own table contract is satisfied.

## Batch 2 boundary — Entry Detail date family + Persian digits

Batch 2 starts from planning and actual canonical `main` `eb5397f6c3acefcfee8d089c59a0ac7d2bd95d94`, the merge commit of PR #69. No base rebind was required.

The migrated Entry Detail date capabilities are exactly:

- `gravityflow.entry-detail.submitted`
- `gravityflow.entry-detail.last-updated`
- `gravityflow.entry-detail.due-date`
- `gravityflow.entry-detail.expiration`

Entry Detail Scheduled remains a separate exact-package calendar `FINAL_NO_ADMISSION` surface. Timeline/history and inherited Print remain outside Batch 2 and retain their exact version/source/caller-chain guards for WU-04.

Batch 2 also migrates only the PHP eligibility gate for the existing Entry Detail Persian-digit browser adapter. The browser root/text-node contract is intentionally not broadened.

### Batch 2 root cause and replacement

Both Entry Detail adapters had the same unnecessary coupling as Batch 1: they loaded localization product metadata and rejected runtime participation unless `GRAVITY_FLOW_VERSION === target_version`. This exact-version veto duplicated source-admission policy even though the actual presentation safety boundaries were stronger and local to each adapter.

Batch 2 replaces only that veto. Both adapters require:

- exact canonical `GRAVITY_FLOW_PLUGIN_BASENAME = gravityflow/gravityflow.php`;
- present, non-empty, maximum-32-character `GRAVITY_FLOW_VERSION` matching the bounded version-token syntax;
- their own callback/context/data/DOM contracts.

Localization/source-admission `target_version` remains exact 3.1.0 policy but is no longer activation authority for these two runtime paths. Whole-file hashing, method hashing, a compatibility service, Force Enable and newer-package substitution are not introduced.

### Entry Detail composed date contract

The date adapter remains bounded by the behavior of the composed seam:

1. `gravityflow_date_format_entry_detail` must invoke the adapter.
2. A non-empty host/plugin format override is preserved and disarms marker ownership.
3. `GFCommon::get_default_date_format()` must return a non-empty string.
4. PersianGravity arms only its unique escaped `PGRJALALIENTRYDETAIL:` marker plus the exact native GF format for the current request context.
5. `date_i18n` calendar replacement runs only when the incoming format exactly equals that currently armed marked format.
6. The admitted WordPress callback semantics remain `$gmt === true` and an integer localized timestamp-plus-offset.
7. `gmdate()` reads the already-local Gregorian civil components; it does not apply a second timezone conversion.
8. `PGR_Jalali_Presentation::format_date()` remains the only calendar-conversion authority.
9. The host's surrounding native time text is preserved.
10. The owned marker is removed on every fallback, including stale/unarmed/wrong-format paths.
11. Unrelated `date_i18n` calls remain native when they do not contain the owned marker.
12. No operational due/schedule/expiration getter and no Timeline seam is hooked or re-entered.

Focused falsification covers non-empty host format, missing native default format, wrong/stale marker format, unexpected GMT semantics, non-integer timestamp, out-of-range/conversion failure, unrelated `date_i18n`, wrong plugin basename and malformed version observation.

### Truthful Entry Detail date diagnostics attribution

The shared date-format and `date_i18n` hooks do not expose a trustworthy semantic row identity. Exact Flow 3.1.0 source/runtime evidence does establish that a complete workflow-info render routes the admitted Submitted, Last Updated, Due and Expiration rows through the shared chain before `gravityflow_below_workflow_info_entry_detail`.

Batch 2 therefore adds only a diagnostics-only post-render observer on that already-qualified hook. The four existing date capability IDs are recorded together as `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED` only when exactly four successful marked calls were observed in the complete render. Fewer calls or mixed failures become `DEGRADED / PGR-GFLOW-CONTEXT-UNAVAILABLE` rather than a guessed field assignment. A uniform observed source/conversion failure may use its existing bounded reason for all four only when the same failure is actually observed for the complete family.

The observer is not required for conversion and is never read by the conversion path. The date adapter does not call `status_snapshot()`, read `pgr_gravityflow_compatibility_latest`, or consume persisted diagnostics as activation authority.

### Entry Detail Persian-digit contract

PHP eligibility remains:

- actual `gravityflow_below_workflow_info_entry_detail` invocation;
- exact `fa_IR` locale;
- canonical Flow basename plus bounded valid runtime-version observation;
- `PGR_URL` and `PGR_VERSION` asset constants;
- one-time enqueue semantics.

The browser adapter remains unchanged. It requires the exact root:

`#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box`

Only `.gravityflow-status-box-field` descendants are scanned. Only human-visible text nodes are eligible. `script`, `style`, `textarea`, `select`, `option`, `template`, `noscript`, `[hidden]`, `[aria-hidden="true"]`, `[contenteditable="true"]` and CSS-hidden descendants are excluded. Only ASCII digit glyphs are substituted. Attributes, element IDs, `data-*`, URLs/query strings, form/control values, hidden machine state and semantic numeric values are never rewritten. Missing/drifted root safely does nothing.

Visible Scheduled text may receive glyph shaping if it is rendered in the same qualified status box; this does not change `gravityflow.entry-detail.schedule` calendar no-admission.

### Digit diagnostics identity decision

No `gravityflow.entry-detail.persian-digits` capability is added. Existing registries do not establish a shared diagnostics identity for this browser-only presentation failure domain. PHP can truthfully observe enqueue eligibility, but cannot prove exact-root/browser text-node execution. Treating enqueue as browser `AVAILABLE` would overstate evidence. AJAX, telemetry, persistent DOM reporting or other instrumentation is not added solely to populate System Status.

Digit-shaping truth therefore remains in focused JS tests and authentic WU008 browser evidence. This avoids forcing observations into an unrelated date capability.

## Diagnostics mapping used across WU-03

The implementation uses only existing WU-02 states/reasons:

- full evaluated current contract satisfied → `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED`;
- host identity missing/invalid → `UNAVAILABLE / PGR-GFLOW-HOST-UNQUALIFIED`;
- required shared marker/render context unavailable or attribution ambiguous → `DEGRADED / PGR-GFLOW-CONTEXT-UNAVAILABLE`;
- required seam/default format unavailable → `DEGRADED / PGR-GFLOW-SEAM-UNAVAILABLE`;
- admitted GMT/timestamp/source semantics malformed → `DEGRADED / PGR-GFLOW-SOURCE-INVALID` where attribution is truthful;
- conversion facade/output unavailable → `DEGRADED / PGR-GFLOW-CONVERSION-UNAVAILABLE`.

No new state or reason taxonomy is introduced.

## Synthetic version-only falsification

Focused unit harnesses deliberately set a synthetic non-3.1.0 version token while retaining canonical Flow basename and the exact local contract.

- Batch 1 direct capabilities retain bounded Persian output under intact callback/data/context/provenance contracts.
- Batch 2 Entry Detail date presentation still arms and converts through the exact marker/date_i18n contract.
- Batch 2 Entry Detail digit PHP gate still enqueues under exact post-render/locale/assets/host identity.

This proves only that architecture no longer rejects solely on version equality. It is **not** authentic qualification of Gravity Flow `3.1.1.1`, `99.0.0-synthetic`, or any other real newer package.

## Batch 2 true contract-drift and isolation evidence

Focused PHP/JS tests require native/fail-closed behavior for wrong canonical basename, malformed version observation, host format override, missing default format, stale/wrong marker context, unexpected GMT semantics, malformed timestamp, out-of-range/conversion failure, non-Persian locale, missing assets, duplicate enqueue, missing exact DOM root, hidden/editable/excluded nodes and machine-state attributes/controls/URLs.

Date and digit presentation remain separate adapters. A date marker/conversion failure does not change the post-render digit PHP/DOM contract; digit locale/asset/root failure does not change the composed date contract. No global compatibility service couples their activation.

## Scope-preservation checks

The intended Batch 2 production diff is restricted to the two existing Entry Detail PHP adapters. The existing digit browser implementation remains unchanged; tests harden its exact DOM/text-node boundaries.

Batch 2 does not migrate Timeline/history or Print, does not admit Entry Detail Scheduled calendar conversion, does not change Status due-date no-admission, Gravity Forms, GravityView, Jalali converter arithmetic/range, localization package authority, vendor files, plugin version, Stable tag, release or deployment state.

`tools/compatibility/gravityflow-package.json` remains the exact Owner-supplied 3.1.0 authority.

## Authentic final-Head evidence requirement

Merge eligibility still requires successful execution on the unchanged final PR Head of the applicable existing lanes:

- CI;
- Artifact Install Smoke;
- G008 Jalali Presentation Runtime;
- WU008 Licensed Real Integration;
- any G006 exact-runtime lane actually triggered by the final diff.

The exact workflow run IDs and final statuses are execution evidence and must be reported from that final Head; they are not inferred or predeclared in this source record. WU008 remains the authentic exact-3.1.0 boundary for browser/runtime presentation plus DB/GFAPI/REST, query/sort/filter/compare values, due/overdue/schedule/workflow state, assignments and CSV/export isolation. A green lane proves only the scenarios it actually exercises.