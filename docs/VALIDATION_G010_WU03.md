# G-010 WU-03 Batch 1 validation record

## Boundary

This record covers only the direct Gravity Flow presentation-contract migration for:

- `gravityflow.inbox.date-created`
- `gravityflow.inbox.last-updated`
- `gravityflow.inbox.due-date`
- `gravityflow.status.date-created`
- `gravityflow.status.workflow-timestamp`

Planning `main` and actual execution base are both `571a9d38c9a7ef6aecf329b8d1130d2adca3b28e`, the merge commit of PR #68. No base rebind was required.

The authentic licensed qualification package remains Gravity Flow `3.1.0`, authority `OWNER_SUPPLIED_GOOGLE_DRIVE`, expected bytes `2603034`, SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`, plugin main file `gravityflow/gravityflow.php`. `tools/compatibility/gravityflow-package.json`, localization source-admission policy, product `target_version`, vendor files, plugin version and Stable tag are not changed by this batch.

The G-008 registry continues to describe the exact-package evidence boundary for authentic admission. WU-03 does not reinterpret that exact 3.1.0 evidence as qualification of a newer licensed package. Its narrower runtime claim is only that, for the five capabilities above, a version-string difference is no longer an independent veto when the adapter's actual host/callback/data/context/provenance contract is intact.

## Root cause and replacement

Before WU-03, both direct adapters first called an exact-version host gate that loaded `includes/localization/products.php`, found the Gravity Flow product by plugin basename and required runtime `GRAVITY_FLOW_VERSION` to equal the source-admission `target_version` (`3.1.0`). The adapters already had useful capability-local callback/source/provenance evidence, but a version-string mismatch rejected the callback before those contracts could establish safety.

The replacement keeps the existing adapter boundaries rather than introducing a compatibility service. Both adapters require a bounded syntactically valid `GRAVITY_FLOW_VERSION` observation and exact `GRAVITY_FLOW_PLUGIN_BASENAME = gravityflow/gravityflow.php`, then independently validate the capability-local contract. The localization/source-admission target version is no longer runtime activation authority for these five capabilities.

Diagnostics remain observation-only. `PGR_Gravity_Flow_Compatibility_Diagnostics::record()` is called only after the adapter has made its own current-callback decision. The direct adapters do not read `status_snapshot()`, `pgr_gravityflow_compatibility_latest`, or any previous-request diagnostic state.

## Inbox contract evidence

`date-created` requires the admitted Inbox value callback, exact `date_created_human_readable` identity, canonical Flow host identity, array Entry context, strict Gravity Forms UTC `date_created` and a successful bounded Jalali conversion.

`last-updated` requires the exact `last_updated_human_readable` identity and authoritative positive `workflow_timestamp` Unix instant. The native `-` sentinel remains native.

`due-date` retains the stronger one-shot provenance contract: exact raw `due_date` callback, native integer epoch/0 domain, canonical same form+entry identity, immediate matching `due_date_human_readable` callback and single consumption. The adapter never calls `get_due_date_timestamp()`, never registers `gravityflow_step_due_date_timestamp`, never parses the localized display string, and preserves raw `0` plus native display `-`.

Focused falsification covers missing/wrong host identity, missing conversion facade, malformed raw due type and due contract drift while the other Inbox capabilities remain independently eligible. Existing tests continue to cover row mismatch, form mismatch, intervening identity/order drift, one-shot consumption, malformed/out-of-range sources, native compare identities and zero additional operational due getter invocations.

## Status contract evidence

The Status adapter retains its existing table-only provenance sequence:

`gravityflow_entry_url_status_table` one-shot proof → matching `gravityflow_field_value_status_table` callback.

The token is bound to canonical positive form+entry identity and consumed once. `date_created` requires strict UTC Entry source; `workflow_timestamp` requires a positive Unix instant. A direct/export value-filter call has no table token and remains native. Missing/mismatched token for one capability does not disable the other when the other's own table contract is satisfied.

## Diagnostics mapping

The batch uses only the existing WU-02 catalog:

- full current-callback contract satisfied → `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED`;
- host identity unavailable/invalid → `UNAVAILABLE / PGR-GFLOW-HOST-UNQUALIFIED`;
- required row/table/provenance context unavailable → `DEGRADED / PGR-GFLOW-CONTEXT-UNAVAILABLE`;
- authoritative source malformed/missing → `DEGRADED / PGR-GFLOW-SOURCE-INVALID`;
- conversion facade/output unavailable → `DEGRADED / PGR-GFLOW-CONVERSION-UNAVAILABLE`.

No new state or reason taxonomy is introduced. Non-target fields/columns do not emit compatibility observations.

## Synthetic version-only falsification

The focused unit harness deliberately sets a synthetic non-3.1.0 version string while retaining the canonical Flow basename and the exact callback/data/context contracts. All five migrated capabilities are expected to retain their bounded Persian output and record `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED`.

This proves only that the architecture no longer rejects solely on version equality. It is **not** authentic qualification of Gravity Flow `3.1.1.1`, `9.9.9`, or any other real newer package.

## Scope-preservation checks

The intended production diff is restricted to the existing Inbox and Status adapters. Entry Detail, Entry Detail digit presentation, Timeline/history, Print inheritance, Gravity Forms, GravityView, Jalali converter/facade arithmetic, package manifest, vendor files, plugin version and release/deployment state remain outside the production change.

Entry Detail and Timeline retain their pre-existing exact-version/source/caller guards. `gravityflow.status.due-date` and `gravityflow.entry-detail.schedule` remain outside this batch at their existing no-admission dispositions.

## Authentic final-Head evidence requirement

Merge eligibility still requires successful execution on the unchanged final PR Head of the applicable existing lanes:

- CI;
- Artifact Install Smoke;
- G008 Jalali Presentation Runtime;
- WU008 Licensed Real Integration;
- any G006 exact-runtime lane actually triggered by the final diff.

The exact workflow run IDs and final statuses are execution evidence and must be reported from that final Head; they are not inferred or predeclared in this source record. WU008 remains the authentic exact-3.1.0 boundary for browser/runtime presentation plus DB/GFAPI/REST, query/sort/filter/compare values, due/overdue/schedule/workflow state, assignments and Status CSV/export isolation. A green lane proves only the scenarios it actually exercises.
