# G-014 — Optional real RTL for Gravity Flow Inbox

Status: **Unmerged candidate; no user-facing release or cross-product qualification.** This document does not supersede G-013's test-only Vazir coexistence PR #100.

## Product intent

PersianGravity optionally supplies the *initial* AG Grid `enableRtl` option for admitted Gravity Flow 3.1.0 Inbox grids, through the native pre-grid `gravityflow_js_config_shared` filter. Gravity Flow still owns Inbox data, column definitions, authorization and interactions. GPP still owns its presentation and the current Owner-approved legacy LTR exceptions.

The switch is under PersianGravity → Settings:

- fa_IR label: «اصلاح راست‌به‌چپ جدول‌های Gravity Flow».
- **OFF by default** for upgrades and fresh sites. OFF does not register the filter and does **not** set `enableRtl=false`, or modify previously persisted Grid state.
- ON: for authentic `GRAVITY_FLOW_PLUGIN_BASENAME=gravityflow/gravityflow.php`, exact `GRAVITY_FLOW_VERSION=3.1.0` and bounded `inbox`/`inbox_*` shared-config grids, supply missing `enableRtl` from WordPress's active request locale (`is_rtl()`), before AG Grid initializes.
- Any already-present explicit `enableRtl` (true/false/other) stays vendor-/other-integration-owned. Do not silently overwrite. Source-equivalent version drift, absent identity, malformed config and unrelated grids stay native/unchanged.
- Source module catalog remains exactly seven modules; this integration is a separate explicit opt-in setting in existing `pgr_settings`, not a font module, a GPP presentation component or a new custom database.

## Verification and compatibility

Historical exact-vendor WU008 probes in G-013 demonstrated that `gravityflow_js_config_shared` reaches real Admin and Frontend Inbox initialization: RTL `ag-rtl`/LTR `ag-ltr` at 1280/390, and observed mobile scrolling, column relocation and keyboard focus. They were run with a **temporary MU-plugin**, not this production integration. These historical outcomes do **not** qualify this new candidate.

Required proof prior to merge: on exact Head, real licensed Gravity Flow 3.1.0, actual setting changes, fresh login and request-local effective locale for en_US/fa_IR including mixed site/user locale, Admin and Frontend Inbox at 1280/390, stable loaded rows/headers, config/key/class alignment, column order, mobile scroll and restoration, keyboard directional navigation, Sort/Filter/pagination, resize/drag and saved column state, JS/network error checks, and OFF native equivalence. Test existing explicit options owned by other integrations. Independently inspect the evidence and candidate package. No generic CI check alone qualifies browser RTL.

**Cross-product boundary:** after qualifying PersianGravity's own candidate, install that *unreleased* ZIP beside current real GPP and licensed Gravity Flow in the GPP lab. Measure OFF/ON interactions with GPP CSS forced-LTR internals, column reordering, InboxInitialGeometryGuard, persisted state, horizontal scroll, touch/keyboard, sorting/filtering/pagination. Compare before/after and change GPP code only when an authentic conflict is demonstrated and its Owner-approved locked contract has been deliberately reviewed/superseded. A candidate that makes a single AG Grid screenshot look right is insufficient.

**G-013 remains distinct:** historical document-root `direction:ltr` came from Gravity Forms `gform_admin-css` and is a different test-enforcement defect. The G-013 PR must later replace that invalid document-root-only assertion with truthful post-grid-readiness and native grid-mode checks. Do not suppress the known failure, overwrite G-013 evidence, or call the real `WITH_VAZIR` profile complete before its full runtime data and separate publisher pass.

No auto-merge, public release, deployment, vendor patch or modification of GPP source is authorized by this candidate.
