# G-010 WU-03 Batch 2 — Entry Detail compatibility contracts

## Scope

WU-03 Batch 2 covers only the existing Gravity Flow Entry Detail presentation paths:

- Jalali workflow-info dates: `gravityflow.entry-detail.submitted`, `gravityflow.entry-detail.last-updated`, `gravityflow.entry-detail.due-date`, `gravityflow.entry-detail.expiration`;
- presentation-only Persian digit shaping in the qualified Entry Detail workflow-info status box.

It does not admit Entry Detail Scheduled calendar presentation, migrate Timeline/history or inherited Print, change GravityView/Gravity Forms behavior, alter localization source admission, or replace package authority. The authentic package baseline remains the Owner-supplied Gravity Flow `3.1.0` package recorded by `tools/compatibility/gravityflow-package.json`.

## Final Batch 2 admission split

The two Entry Detail paths do not share the same forward-compatibility boundary.

The Persian-digit PHP gate follows the WU-03 Batch 1 host-identity pattern: canonical `gravityflow/gravityflow.php`, bounded syntactically valid `GRAVITY_FLOW_VERSION`, exact post-workflow-info hook, `fa_IR`, assets, and the existing browser DOM/text-node contract. Exact target-version equality is not its runtime oracle.

The Jalali date family is stricter. Qualification of Gravity Flow 3.1.0 proves that the shared `gravityflow_date_format_entry_detail` → owned marker → `date_i18n` seam corresponds to exactly four admitted workflow-info date surfaces, but the seam itself exposes no semantic row identity. A later host could reuse that same shared hook for an additional date row. Converting immediately and relying only on post-render call counting would therefore allow an unqualified fifth surface to be converted before the mismatch was detected.

Accordingly, the date-family adapter retains exact-qualified-version admission using the repository's existing Gravity Flow product authority before marker ownership is armed. No second version SSOT, package hash gate, or compatibility service is introduced.

## Entry Detail date-family enforcement boundary

Marker ownership is admitted only when all preconditions hold:

1. `GRAVITY_FLOW_PLUGIN_BASENAME` is exactly `gravityflow/gravityflow.php`.
2. `GRAVITY_FLOW_VERSION` is present and syntactically bounded.
3. `PGR_PATH . 'includes/localization/products.php'` is readable and identifies the existing `gravityflow` product.
4. Runtime `GRAVITY_FLOW_VERSION` exactly equals that product's existing `target_version`.
5. `gravityflow_date_format_entry_detail` actually invokes the adapter with an empty native format.
6. `GFCommon::get_default_date_format()` returns a non-empty native format.
7. `PGR_Jalali_Presentation` is available.

Only after those checks may PersianGravity arm its unique escaped `PGRJALALIENTRYDETAIL:` marker plus the exact native format for the current request context.

Once armed, the existing composed hardening remains unchanged:

- `date_i18n` conversion occurs only for the exact currently armed marked format;
- `$gmt === true` and an integer localized timestamp-with-offset are required;
- `gmdate()` reads the already-local Gregorian civil components without a second timezone shift;
- only the calendar date is converted through `PGR_Jalali_Presentation::format_date()`;
- Gravity Flow's surrounding native time text is preserved;
- every fallback from a PersianGravity-owned marked format strips the marker, including stale/unarmed/wrong-format paths;
- unrelated non-owned `date_i18n` output remains byte-for-byte native, including literal marker collisions;
- operational due/schedule/expiration getters and Timeline hooks are not entered.

Wrong/missing canonical identity, malformed version observation, target-version drift, unreadable/malformed product authority, non-empty format override, invalid default format, stale/wrong marker context, unexpected GMT semantics, malformed timestamp, out-of-range date, unavailable conversion facade, or conversion failure all fail to native presentation.

## Version-drift defect-class closure

A synthetic different-version host with otherwise intact callback/marker/timestamp behavior must fail before marker ownership. No Entry Detail workflow-info Jalali date conversion occurs and no four-capability `AVAILABLE` observation is fabricated.

The dedicated defect-class falsification exercises five otherwise valid attempts through the shared date seam under an unqualified version. All five remain native. This proves that an additional/unqualified fifth semantic surface cannot slip through before post-render reconciliation.

This date-family behavior is intentionally different from Batch 1 Inbox/Status and from the Entry Detail Persian-digit adapter. It does not qualify Gravity Flow 3.1.1.1 or any other newer package.

## Truthful date diagnostics attribution

The four date capability IDs already exist in `PGR_Gravity_Flow_Compatibility_Diagnostics`; no new date IDs are created.

For the exact qualified Flow 3.1.0 host, source/runtime qualification establishes that a complete four-row workflow-info render routes Submitted, Last Updated, Due and Expiration through the shared date-format/date_i18n chain before `gravityflow_below_workflow_info_entry_detail`. Because the shared hooks do not expose semantic row identity, the adapter does not invent per-field attribution.

The diagnostics-only post-render observer may record all four IDs as `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED` only when exactly four successful marked calls were observed. Partial renders or mixed four-call results emit no per-capability observation. Uniform source/conversion failure may use the existing bounded reason only when that same failure was actually observed across the complete four-call family.

An unqualified/different host version never arms the marker, so no marked calls exist to reconcile and no four-capability `AVAILABLE` promotion can occur. Native output / `NOT_EVALUATED` reporting remains applicable. The observer never authorizes conversion and the adapter never reads `status_snapshot()`, `pgr_gravityflow_compatibility_latest`, or persisted diagnostics as activation authority.

## Persian digit-shaping contract

The separate digit adapter remains version-equality-independent. PHP eligibility is bounded to:

- actual `gravityflow_below_workflow_info_entry_detail` invocation;
- exact `fa_IR` locale;
- canonical Gravity Flow plugin basename plus bounded valid runtime-version observation;
- available `PGR_URL` and `PGR_VERSION` assets;
- one-time enqueue semantics.

The browser adapter is unchanged. It requires exactly:

`#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box`

Only `.gravityflow-status-box-field` descendants are scanned. Only visible text nodes are eligible. Script/style/textarea/select/option/template/noscript, hidden, `aria-hidden`, contenteditable and CSS-hidden descendants are excluded. Only ASCII digit glyphs are substituted. Attributes, URLs, control values, IDs, `data-*`, hidden machine state and semantic numeric values are never rewritten.

A missing/drifted root safely does nothing. Visible Scheduled text may receive digit glyph shaping when rendered in this status box; that does not change the separate `gravityflow.entry-detail.schedule` calendar `FINAL_NO_ADMISSION` disposition.

No `gravityflow.entry-detail.persian-digits` diagnostics capability is added because PHP enqueue eligibility cannot truthfully prove browser DOM execution without intrusive instrumentation.

## Isolation and preservation

- Date-family exact-version failure does not disable the digit adapter.
- Digit hook/locale/asset/DOM failure does not weaken date-family exact-version admission.
- Inbox/Status Batch 1 contracts are unchanged.
- DB/GFAPI/REST/query/export/workflow/assignment/deadline/overdue/expiration/schedule semantics remain host-owned.
- Entry Detail Schedule Jalali calendar admission remains unchanged.
- Timeline/history exact version/source/caller-chain guards remain unchanged for WU-04.
- Print continues to inherit only the verified Timeline renderer and is not migrated here.
- Canonical package authority, plugin version, Stable tag, vendor files, release state and deployment state are unchanged.

## Validation boundary

Focused PHP/JS tests cover exact 3.1.0 behavior; date-family synthetic version drift and five-call defect-class fail-closed behavior; marker lifecycle, GMT/timestamp/conversion fallback, partial/mixed diagnostic non-attribution; independent synthetic version drift for the digit adapter; exact DOM root/field scoping; excluded descendants; and machine-state non-mutation.

Authentic acceptance still requires exact-final-Head CI, Artifact Install Smoke, G008 Jalali Presentation Runtime and WU008 Licensed Real Integration against the unchanged Owner-supplied Gravity Flow 3.1.0 package. Unavailable execution is `NOT_PROVEN`, never inferred as pass.
