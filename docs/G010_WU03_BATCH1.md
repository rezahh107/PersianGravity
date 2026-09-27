# G-010 WU-03 Batch 1 — Inbox + Status compatibility contracts

## Scope

This batch migrates only these existing direct Gravity Flow presentation capabilities:

- `gravityflow.inbox.date-created`
- `gravityflow.inbox.last-updated`
- `gravityflow.inbox.due-date`
- `gravityflow.status.date-created`
- `gravityflow.status.workflow-timestamp`

Entry Detail, Timeline/history, Print inheritance, `gravityflow.status.due-date`, package/source admission, localization provider version policy, release versioning and vendor files are unchanged.

The authentic qualification baseline remains the Owner-supplied Gravity Flow `3.1.0` package identified by `tools/compatibility/gravityflow-package.json`. This migration does not qualify any real newer Gravity Flow package.

## Compatibility rule

A version-string difference alone is no longer a runtime veto for the five capabilities above. Each adapter first validates the capability-local runtime contract and only then records an informational compatibility observation.

Both adapters still require:

- `GRAVITY_FLOW_VERSION` to exist and contain a bounded syntactically valid version observation;
- `GRAVITY_FLOW_PLUGIN_BASENAME` to equal the canonical `gravityflow/gravityflow.php` main-file identity;
- the admitted callback and exact target field/column identity;
- the required source/context/provenance contract for that capability;
- the existing Jalali presentation facade when conversion is required.

The adapters do not read `includes/localization/products.php`, its `target_version`, `PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()`, or persisted compatibility option state to decide whether presentation runs.

## Capability contract map

| Capability | Old runtime veto | Replacement runtime contract | Native fallback |
| --- | --- | --- | --- |
| `gravityflow.inbox.date-created` | exact Flow version equality + basename | admitted Inbox value callback, exact `date_created_human_readable`, canonical Flow host identity, array Entry context, strict UTC `Entry['date_created']`, usable Jalali conversion | missing/invalid host, context, source or conversion |
| `gravityflow.inbox.last-updated` | exact Flow version equality + basename | admitted Inbox value callback, exact `last_updated_human_readable`, canonical Flow host identity, array Entry context, authoritative positive `workflow_timestamp` Unix instant; native `-` sentinel preserved | missing/invalid host, context, source or conversion |
| `gravityflow.inbox.due-date` | exact Flow version equality + basename before raw/display handling | exact raw `due_date` integer epoch/0 callback followed immediately by the matching `due_date_human_readable` callback, same canonical form+entry identity, one-shot consumption, canonical Flow host identity, no operational getter re-entry, no display parsing | missing/malformed raw authority, wrong row/form, intervening identity/order drift, inconsistent 0/`-` pair, invalid source or conversion |
| `gravityflow.status.date-created` | exact Flow version equality + basename | one-shot `gravityflow_entry_url_status_table` proof for the same canonical form+entry followed by exact `date_created` Status value callback, canonical Flow host identity, strict UTC `Entry['date_created']`, usable conversion | direct/export call, missing/mismatched token, invalid host/source/conversion |
| `gravityflow.status.workflow-timestamp` | exact Flow version equality + basename | one-shot Status-table proof for the same canonical form+entry followed by exact `workflow_timestamp` value callback, canonical Flow host identity, positive Unix source instant, usable conversion | direct/export call, missing/mismatched token, invalid host/source/conversion |

`gravityflow.status.due-date` is intentionally not changed or admitted by this batch.

## Due-date preservation

The Inbox due-date adapter continues to consume the raw compare value already computed by Gravity Flow. It does not call `get_due_date_timestamp()`, does not register `gravityflow_step_due_date_timestamp`, and does not parse `due_date_human_readable`.

The proof is one-shot and bound to canonical positive form/entry IDs. A malformed raw type, missing raw callback, reordered row, mismatched form/entry, intervening field identity, or inconsistent raw-0/display-dash relation fails only due-date presentation to the native value. The other Inbox capabilities remain independently eligible when their own contracts pass.

## Status table-only preservation

The Status adapter continues to use `gravityflow_entry_url_status_table` as post-branch table proof. The token is one-shot and same-form/same-entry bound. `gravityflow_field_value_status_table` without that token — including export/direct value-filter paths — remains native.

## Diagnostics mapping

Diagnostics observe decisions after adapter-local validation and never authorize presentation.

- satisfied full contract → `AVAILABLE` / `PGR-GFLOW-CONTRACT-SATISFIED`;
- invalid/missing Gravity Flow host identity → `UNAVAILABLE` / `PGR-GFLOW-HOST-UNQUALIFIED`;
- missing/mismatched one-shot row/table context → `DEGRADED` / `PGR-GFLOW-CONTEXT-UNAVAILABLE`;
- malformed/missing authoritative source → `DEGRADED` / `PGR-GFLOW-SOURCE-INVALID`;
- unavailable/failed bounded Jalali conversion → `DEGRADED` / `PGR-GFLOW-CONVERSION-UNAVAILABLE`.

Non-target fields/columns do not create compatibility noise. No new diagnostics state or reason taxonomy is introduced.

## Version-only falsification boundary

Unit tests deliberately substitute a synthetic version string while preserving canonical Flow basename, callback identity, source data and request-local provenance. The five target capabilities must still produce the same bounded Persian presentation and `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED` observations.

That is architecture evidence only. It does not establish compatibility with Gravity Flow `3.1.1.1`, any other real newer package, or an arbitrary future source tree.

## Authentic runtime evidence

The required final-Head verification remains the existing exact-package infrastructure:

- CI;
- Artifact Install Smoke;
- G008 Jalali Presentation Runtime;
- WU008 Licensed Real Integration;
- any G006 exact-runtime lane triggered by the final diff.

WU008 remains the authentic proof boundary for current Flow `3.1.0` Inbox/Status presentation, disabled/native fallback, raw compare and sort/filter behavior, due/overdue/schedule/workflow state, assignments, DB/GFAPI/REST semantics and Status CSV/export isolation. Green workflow results prove only the scenarios exercised by those workflows.

Detailed validation boundaries and falsification coverage are recorded in `docs/VALIDATION_G010_WU03.md`.
