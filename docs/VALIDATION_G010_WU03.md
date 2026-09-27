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

The G-008 registry continues to describe the exact-package authentic evidence boundary. WU-03 does not reinterpret exact 3.1.0 evidence as qualification of a newer licensed package.

### Batch 1 root cause and replacement

Before Batch 1, the direct Inbox/Status adapters loaded `includes/localization/products.php`, found the Gravity Flow product by plugin basename and required runtime `GRAVITY_FLOW_VERSION` to equal source-admission `target_version` (`3.1.0`). Their capability-local callback/source/provenance contracts were sufficient to isolate the admitted direct surfaces, so Batch 1 removed version equality as an independent runtime veto while retaining canonical host identity and those stronger local contracts.

Diagnostics remain observation-only. The direct adapters do not read `status_snapshot()`, `pgr_gravityflow_compatibility_latest`, or previous-request diagnostic state to activate conversion.

### Inbox contract evidence

`date-created` requires the admitted Inbox value callback, exact `date_created_human_readable` identity, canonical Flow host identity, array Entry context, strict Gravity Forms UTC `date_created` and successful bounded Jalali conversion.

`last-updated` requires exact `last_updated_human_readable` identity and authoritative positive `workflow_timestamp` Unix instant. The native `-` sentinel remains native.

`due-date` retains the stronger one-shot provenance contract: exact raw `due_date` callback, native integer epoch/0 domain, canonical same form+entry identity, immediate matching `due_date_human_readable` callback and single consumption. The adapter never calls `get_due_date_timestamp()`, never registers `gravityflow_step_due_date_timestamp`, never parses the localized display string, and preserves raw `0` plus native display `-`.

### Status contract evidence

The Status adapter retains its existing table-only provenance sequence:

`gravityflow_entry_url_status_table` one-shot proof → matching `gravityflow_field_value_status_table` callback.

The token is bound to canonical positive form+entry identity and consumed once. `date_created` requires strict UTC Entry source; `workflow_timestamp` requires a positive Unix instant. Direct/export value-filter calls without the table token remain native.

## Batch 2 boundary — Entry Detail date family + Persian digits

Batch 2 started from canonical `main` `eb5397f6c3acefcfee8d089c59a0ac7d2bd95d94`, the merge commit of PR #69. The implementation PR is #70.

The Entry Detail Jalali date capabilities are exactly:

- `gravityflow.entry-detail.submitted`
- `gravityflow.entry-detail.last-updated`
- `gravityflow.entry-detail.due-date`
- `gravityflow.entry-detail.expiration`

Entry Detail Scheduled remains a separate exact-package calendar `FINAL_NO_ADMISSION` surface. Timeline/history and inherited Print remain outside Batch 2 and retain their exact version/source/caller-chain guards for WU-04.

The separate Entry Detail Persian-digit browser adapter remains part of Batch 2, but its compatibility boundary differs from the date family.

### Confirmed date-family defect in PR #70

The initial Batch 2 implementation removed exact-version admission from the Entry Detail date adapter and used canonical host identity plus the composed `gravityflow_date_format_entry_detail` → owned marker → `date_i18n` contract. That was insufficient because the shared format/date hooks expose no semantic row identity.

Exact Gravity Flow 3.1.0 evidence proves that the qualified shared seam corresponds to exactly Submitted, Last Updated, Due and Expiration. A future host can nevertheless reuse that same hook for an additional date row. Because the adapter converts a marked `date_i18n` call immediately, post-render four-call reconciliation cannot prevent an unqualified fifth surface from being converted before the mismatch is observed.

The defect is therefore at the marker-ownership admission boundary, not in the existing marker lifecycle or diagnostics reconciliation.

### Selected repair and source of truth

The repair reuses the adapter's pre-Batch-2 repository authority model rather than adding a new version constant, package hash gate, or compatibility service.

Before marker ownership is armed, the date adapter now requires:

- exact `GRAVITY_FLOW_PLUGIN_BASENAME = gravityflow/gravityflow.php`;
- a present, bounded syntactically valid runtime `GRAVITY_FLOW_VERSION`;
- readable `PGR_PATH . 'includes/localization/products.php'`;
- the existing `gravityflow` product record from that repository-owned manifest;
- exact equality between runtime `GRAVITY_FLOW_VERSION` and the manifest's existing `target_version`.

The adapter contains no parallel hard-coded `3.1.0` runtime authority. Version/source ownership remains in the existing repository manifest. Package authority itself remains unchanged.

This exact-version requirement applies only to the Entry Detail Jalali date family. Inbox/Status Batch 1 and the Entry Detail Persian-digit adapter remain on their separately qualified contracts.

### Qualified Entry Detail composed date contract

For the exact qualified host, the existing Batch 2 hardening remains:

1. `gravityflow_date_format_entry_detail` must invoke the adapter.
2. A non-empty host/plugin format override is preserved and disarms marker ownership.
3. `GFCommon::get_default_date_format()` must return a non-empty string.
4. PersianGravity arms only its unique escaped `PGRJALALIENTRYDETAIL:` marker plus the exact native GF format for the current request context.
5. `date_i18n` calendar replacement runs only when the incoming format exactly equals that currently armed marked format.
6. `$gmt === true` and an integer localized timestamp-plus-offset are required.
7. `gmdate()` reads the already-local Gregorian civil components without a second timezone conversion.
8. `PGR_Jalali_Presentation::format_date()` remains the only calendar-conversion authority.
9. The host's surrounding native time text is preserved.
10. The owned marker is removed on every fallback from a PersianGravity-owned marked format, including stale/unarmed/wrong-format paths.
11. Unrelated non-owned `date_i18n` calls remain byte-for-byte native, including literal marker collisions.
12. No operational due/schedule/expiration getter and no Timeline seam is hooked or re-entered.

Focused exact-3.1.0 coverage continues to exercise marker leakage, GMT/timestamp semantics, native time preservation, source/conversion fallback and unrelated `date_i18n` controls.

### Truthful Entry Detail date diagnostics attribution

The shared date-format and `date_i18n` hooks do not expose a trustworthy semantic row identity. Exact Flow 3.1.0 source/runtime evidence establishes only that a complete qualified four-row workflow-info render routes Submitted, Last Updated, Due and Expiration through the shared chain before `gravityflow_below_workflow_info_entry_detail`.

The diagnostics-only post-render observer may therefore record the four existing date IDs together as `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED` only after exactly four successful marked calls on the exact qualified host. Partial renders or mixed four-call results emit no per-capability observation. Uniform source/conversion failure may use its existing bounded reason only when the same failure was actually observed across all four calls.

On an unqualified/different host version the marker is never armed. There are no qualified marked calls to reconcile, so no four-capability `AVAILABLE` observation can be fabricated. Native presentation / `NOT_EVALUATED` reporting remains applicable.

The observer never authorizes conversion. The adapter does not call `status_snapshot()`, read `pgr_gravityflow_compatibility_latest`, or consume persisted diagnostics as activation authority.

### Date-family falsification coverage

The focused fail-closed harness deliberately sets a synthetic non-3.1.0 version token while retaining canonical Flow basename and otherwise valid callback/timestamp behavior.

Required result:

- `filter_entry_detail_date_format( '' )` returns native empty format;
- no Entry Detail date marker is armed;
- no Jalali date conversion occurs;
- post-render reconciliation creates no four-capability `AVAILABLE` observation;
- native output is preserved.

A separate defect-class test performs five otherwise valid shared-seam attempts under that unqualified version. All five must remain native and diagnostics must remain empty. This directly proves that a fifth/unqualified semantic date surface cannot receive Jalali conversion before post-render reconciliation.

### Entry Detail Persian-digit contract remains independent

The separate digit adapter keeps version-equality-independent PHP eligibility:

- actual `gravityflow_below_workflow_info_entry_detail` invocation;
- exact `fa_IR` locale;
- canonical Flow basename plus bounded valid runtime-version observation;
- `PGR_URL` and `PGR_VERSION` asset constants;
- one-time enqueue semantics.

The browser adapter remains unchanged. It requires the exact root:

`#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box`

Only `.gravityflow-status-box-field` descendants are scanned. Only human-visible text nodes are eligible. `script`, `style`, `textarea`, `select`, `option`, `template`, `noscript`, `[hidden]`, `[aria-hidden="true"]`, `[contenteditable="true"]` and CSS-hidden descendants are excluded. Only ASCII digit glyphs are substituted. Attributes, element IDs, `data-*`, URLs/query strings, form/control values, hidden machine state and semantic numeric values are never rewritten.

Synthetic version-only drift may still enqueue this digit adapter when canonical host/locale/assets contracts are satisfied. That control is deliberately the opposite of the date-family drift control and proves the failure domains remain independent.

No `gravityflow.entry-detail.persian-digits` diagnostics capability is added because PHP enqueue eligibility cannot truthfully prove the browser DOM/text-node outcome without intrusive instrumentation.

## Preservation checks

The repaired Batch 2 boundary must preserve all of the following:

- Inbox/Status Batch 1 contracts remain version-equality-independent.
- Entry Detail Schedule calendar remains no-admission.
- Timeline/history retains exact version/source/caller-chain guards.
- Print remains inherited only through the verified Timeline renderer.
- Module-disabled and English/native controls remain native.
- DB/GFAPI/REST/query/export/workflow/assignment/deadline/overdue/expiration/schedule semantics remain host-owned.
- Existing digit browser implementation and machine-state immutability remain unchanged.
- `tools/compatibility/gravityflow-package.json`, vendor files, plugin version, Stable tag, release and deployment state remain unchanged.

The G008 runtime workflow path filters cover the Entry Detail adapter/tests so the existing exact runtime lane executes for this class of change; no parallel lab or workflow behavior redesign is introduced.

## Authentic final-Head evidence requirement

The final unchanged PR Head must be checked in the applicable existing lanes:

- CI;
- Artifact Install Smoke;
- G008 Jalali Presentation Runtime;
- WU008 Licensed Real Integration;
- any G006 exact-runtime lane actually triggered by the final diff.

The exact workflow run IDs and final statuses are execution evidence and must be reported from that final Head; they are not inferred or predeclared in this source record. WU008 remains the authentic exact-3.1.0 boundary for browser/runtime presentation plus DB/GFAPI/REST, query/sort/filter/compare values, due/overdue/schedule/workflow state, assignments and CSV/export isolation. A green lane proves only the scenarios it actually exercises.
