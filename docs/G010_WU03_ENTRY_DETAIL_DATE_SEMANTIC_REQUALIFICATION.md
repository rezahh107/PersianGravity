# G-010 WU-03 — Entry Detail date semantic-contract requalification

## Result

`NO_SAFE_REPLACEMENT_PROVEN`

Planning main and actual starting main are both:

`6739461b889246463b0158938eb9590a3db98b9d`

No intervening canonical-main change was present at requalification start.

This requalification does **not** change production eligibility. The four existing Entry Detail date capabilities remain exact-repository-target qualified before PersianGravity may arm marker ownership:

- `gravityflow.entry-detail.submitted`
- `gravityflow.entry-detail.last-updated`
- `gravityflow.entry-detail.due-date`
- `gravityflow.entry-detail.expiration`

The Owner-supplied Gravity Flow qualification authority remains version `3.1.0`, package SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`, canonical plugin main `gravityflow/gravityflow.php`. No licensed source text is committed by this record.

## Exact 3.1.0 call graph

Bounded exact-package source/runtime evidence reconstructs the four visible workflow-info dates as one shared family:

```text
Gravity Flow Entry Detail workflow-info renderer
  -> gravityflow_date_format_entry_detail        (once; format string only)
  -> Submitted source
     -> Gravity_Flow_Common::format_date
     -> GFCommon::format_date
     -> date_i18n
  -> Last Updated source
     -> Gravity_Flow_Common::format_date
     -> GFCommon::format_date
     -> date_i18n
  -> Due source
     -> current-step due timestamp getter
     -> Gravity_Flow_Common::format_date
     -> GFCommon::format_date
     -> date_i18n
  -> Expiration source
     -> current-step expiration timestamp getter
     -> Gravity_Flow_Common::format_date
     -> GFCommon::format_date
     -> date_i18n
  -> gravityflow_below_workflow_info_entry_detail (after all workflow-info values)
```

For the exact qualified render, the same date-format value is reused across all four `format_date` calls. The semantic CSS class/label for each row is emitted around/after its already-formatted value; it is not supplied to the formatting chain as pre-conversion context.

Authentic PR #70 evidence also records one workflow-info format-filter call and four production marked `date_i18n` calls. Due/expiration operational getters are not one-shot row provenance: the same authentic page execution observed multiple getter invocations while presentation enabled and disabled, with zero presentation-induced nested getter re-entry.

## Semantic-seam inventory and falsification

| Candidate | Pre-conversion semantic context | Support status | Can fail before an unqualified row converts? | Disposition |
| --- | --- | --- | --- | --- |
| `gravityflow_date_format_entry_detail` | Date-format string only; exact 3.1.0 calls it once for the shared family | Public documented Gravity Flow filter | No | **Rejected.** It scopes the family but cannot distinguish Submitted / Last Updated / Due / Expiration or a future fifth row. |
| PersianGravity marker + WordPress `date_i18n` | Owned format marker, formatted output, timestamp, GMT flag | `date_i18n` is a public WordPress filter; marker is PersianGravity-owned | No | **Rejected as semantic identity.** It safely bounds the formatting family but receives no Gravity Flow row identity. A fifth host row using the same format reaches conversion before count reconciliation. |
| `gravityflow_below_workflow_info_entry_detail` | Form, Entry, current step | Public documented Gravity Flow action | No | **Rejected for activation.** It runs after the workflow-info dates have already been formatted. It remains suitable only for post-render observation/diagnostics. |
| Named caller relationship: Entry Detail renderer -> `Gravity_Flow_Common::format_date` -> `GFCommon::format_date` -> `date_i18n` | Same named chain for all four rows | Exact-package behavioral/source provenance, not a row-specific public hook | No | **Rejected.** Function provenance does not differentiate the four semantics. Distinguishing them would require prohibited source-line/stack-line coupling or equivalent brittle source fingerprinting. |
| `gravityflow_step_due_date_timestamp` | Due timestamp plus step/expiration-type context | Public documented Gravity Flow operational filter | No for the four-capability family | **Rejected.** It changes/guards an operational deadline value, covers only Due, is invoked outside the single visible row path, and supplies no Submitted/Last Updated/Expiration family identity. It is not presentation provenance. |
| `gravityflow_step_expiration_timestamp` | Expiration timestamp plus step/expiration-type context | Public documented Gravity Flow operational filter | No for the four-capability family | **Rejected.** Same ownership and multiplicity problem as Due; it cannot identify the other three surfaces and must not be used as presentation activation state. |
| Raw-value matching against Entry/current-step timestamps | Candidate source values only | Host data, not a semantic presentation hook | No | **Rejected.** Equal/colliding values and future rows can share timestamps. Value equality is data correlation, not provenance, and cannot establish a fail-before-conversion invariant. |
| Current four-call sequence/order/count | Ordinal position only | Observed exact-package behavior, not a semantic API | No | **Rejected.** Insertion/reordering converts before the later mismatch is known. Sequence is not identity. |
| Rendered row classes/labels | Strong semantic identity, but only in already-rendered markup | Exact-package presentation markup | No | **Rejected.** The semantic label/class exists too late. Output buffering, broad string replacement, and page-wide DOM date rewriting are outside the approved architecture and explicitly prohibited for this work. |
| Stack/source-line provenance or method/file hashes | Could synthetically distinguish exact call sites | Internal/brittle source identity | Potentially, but by prohibited coupling | **Rejected.** Source-line/stack-line checks, whole-file/method hashes and licensed-source fingerprinting are not acceptable runtime eligibility contracts. |

Public hook references used during qualification:

- Gravity Flow `gravityflow_date_format_entry_detail`: https://docs.gravityflow.io/gravityflow_date_format_entry_detail/
- Gravity Flow `gravityflow_below_workflow_info_entry_detail`: https://docs.gravityflow.io/gravityflow_below_workflow_info_entry_detail/
- Gravity Flow `gravityflow_step_due_date_timestamp`: https://docs.gravityflow.io/gravityflow_step_due_date_timestamp/
- Gravity Flow `gravityflow_step_expiration_timestamp`: https://docs.gravityflow.io/gravityflow_step_expiration_timestamp/
- WordPress `date_i18n`: https://developer.wordpress.org/reference/hooks/date_i18n/

## Fifth/new/reordered-date defect class

The new regression test intentionally models five otherwise valid calls through the already-armed shared marker/date chain on the exact-qualified baseline.

Observed contract being pinned:

1. calls 1–4 convert immediately;
2. call 5 also converts immediately because the shared formatting seam has no semantic identity;
3. only later, at `gravityflow_below_workflow_info_entry_detail`, the diagnostics observer sees a count other than four and refuses to fabricate per-capability observations;
4. that late refusal cannot undo the fifth conversion.

Therefore post-render sequence/count reconciliation is evidence only, never a safe activation authority. The existing exact repository-target gate is what prevents an unknown-version host from arming this semantically ambiguous marker family in the first place.

The existing synthetic version-drift tests remain complementary: with a non-qualified version, marker ownership is never armed and five attempted shared-seam calls remain native.

## Final production eligibility rule

Production remains unchanged:

```text
canonical Gravity Flow basename
+ bounded runtime-version observation
+ runtime version == existing gravityflow target_version
+ actual gravityflow_date_format_entry_detail invocation with empty format
+ valid native GF date format
+ PGR_Jalali_Presentation available
    -> arm exact PersianGravity marker
    -> convert only exact marked date_i18n calls with gmt === true and integer timestamp
    -> local-civil extraction via gmdate
    -> Jalali calendar replacement or marker-free native fallback
    -> post-render diagnostics observation only
```

No diagnostic snapshot, persisted compatibility state, generic compatibility oracle, operational due/expiration hook, package hash, source line or method hash is used to activate production conversion.

## Marker/date_i18n safety contract preserved

The current adapter remains the authority and is not modified by this requalification:

- marker ownership occurs only after exact repository-qualified host admission;
- the currently armed exact marked format is required at `date_i18n`;
- the exact-qualified host is rechecked before conversion;
- `$gmt === true` and an integer localized timestamp-with-offset are required;
- `gmdate()` supplies the already-local Gregorian civil components without a second timezone conversion;
- `PGR_Jalali_Presentation::format_date()` remains the calendar converter;
- every PersianGravity-owned fallback strips the marker;
- unrelated `date_i18n` calls stay native;
- diagnostics are post-render observations and never activation authority.

## Preserved independent contracts

This work intentionally leaves unchanged:

- Entry Detail Persian-digit eligibility: canonical host identity + bounded valid version observation + exact post-workflow-info hook + `fa_IR` + asset/DOM/text-node contract; **no exact version equality**;
- PR #69 Inbox/Status capability-local contracts;
- Entry Detail Scheduled calendar final no-admission disposition;
- Timeline/history production adapter and its exact guards;
- Print inheritance behavior;
- Gravity Forms / GravityView production adapters;
- `tools/compatibility/gravityflow-package.json`;
- localization `target_version` policy itself;
- plugin version, Stable tag, vendor files, release/tag/deployment state;
- DB/GFAPI/REST/query/export/workflow/assignment/deadline/overdue/expiration/schedule ownership.

## What would unblock the Owner destination

A future requalification can replace exact version equality only after host evidence exposes a supported pre-conversion contract that binds each conversion to a semantic row before formatting. Examples include a row-specific presentation hook, a formatting hook carrying stable semantic row/type identity, or an equivalent supported context token that cannot be consumed by an inserted/reordered unqualified date.

A newer package is not assumed to provide such a seam. It must be Owner-supplied/admitted and independently inspected and exercised before compatibility can be claimed.

## WU-03 consequence

This is a host-contract blocker / destination-exception candidate, not a successful date-family migration. WU-03 must remain open with respect to the Entry Detail date family. A later Owner decision may either keep G-010 open pending a stronger host contract or explicitly authorize a permanent exact-version exception for this family. This requalification makes neither Owner decision.
