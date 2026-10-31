# G-014 — Optional real RTL for Gravity Flow Inbox

Status: **Unmerged candidate; no user-facing release or cross-product qualification.** This document does not supersede G-013's test-only Vazir coexistence PR #100.

## Product intent

PersianGravity optionally supplies the *initial* AG Grid `enableRtl` option for admitted Gravity Flow 3.1.0 Inbox grids, through the native pre-grid `gravityflow_js_config_shared` filter. Gravity Flow still owns Inbox data, column definitions, authorization and interactions. GPP still owns its presentation and the current Owner-approved legacy LTR exceptions.

The switch is under PersianGravity → Settings:

- fa_IR label: «اصلاح راست‌به‌چپ جدول‌های Gravity Flow».
- **OFF by default** for upgrades and fresh sites. OFF does not register the filter and does **not** set `enableRtl=false`, or modify previously persisted Grid state.
- ON: for authentic `GRAVITY_FLOW_PLUGIN_BASENAME=gravityflow/gravityflow.php`, exact `GRAVITY_FLOW_VERSION=3.1.0` and producer-verified Admin/Frontend Inbox Grid identities (default, shortcode and block) with the exact native Grid config structure, supply missing `enableRtl` from WordPress's active request locale (`is_rtl()`), before AG Grid initializes.
- Any already-present explicit `enableRtl` (true/false/other) stays vendor-/other-integration-owned. Do not silently overwrite. Source-equivalent version drift, absent identity, malformed config and unrelated grids stay native/unchanged.
- Source module catalog remains exactly seven modules; this integration is a separate explicit opt-in setting in existing `pgr_settings`, not a font module, a GPP presentation component or a new custom database.

## Producer-grounded repair boundary

The Owner-supplied Gravity Flow 3.1.0 source archive (SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`) establishes this native path:

- `includes/inbox/models/class-task.php`, `get_unique_grid_id_from_args()` (lines 627–660), constructs `assignee_inbox_{default|shortcode|block}_{admin|page ID}_{adler32 hash}`. Admin derives the current user; frontend derives assignee and page. The two observed frontend identities are `user_id_1_inbox_shortcode_15_4de0e646` and `user_id_1_inbox_shortcode_15_4ef4e6b3`.
- `includes/inbox/class-inbox-service-provider.php`, `get_config_for_grids()` (lines 223–260), supplies `searchArgs`, `columnDefs`, `rowData`, pagination settings and `current_user_token`. The Inbox producer registers the shared-config population callback at priority 10 (lines 152–168).
- PersianGravity's opt-in callback runs at priority **11**, after the actual producer, and verifies the **exact Grid identity from that producer's public Task method** against the generated config's own `searchArgs`. A validated native Inbox structure is also mandatory. Unsupported or ambiguous structures stay native. No naming guess, broad substring recognition or hardcoded user/form/shortcode ID is used.

This is source-backed eligibility, **not** evidence of successful rendering or coexistence. Real exact-Head WU008 runs must prove both execution ordering and each admitted runtime surface.

## Verification and compatibility

Historical exact-vendor WU008 probes in G-013 demonstrated that `gravityflow_js_config_shared` reaches real Admin and Frontend Inbox initialization: RTL `ag-rtl`/LTR `ag-ltr` at 1280/390, and observed mobile scrolling, column relocation and keyboard focus. They were run with a **temporary MU-plugin**, not this production integration. These historical outcomes do **not** qualify this new candidate.

Required proof prior to merge: on exact Head, real licensed Gravity Flow 3.1.0, actual setting changes, fresh login and request-local effective locale for en_US/fa_IR including mixed site/user locale, Admin and Frontend Inbox at 1280/390, stable loaded rows/headers, config/key/class alignment, column order, mobile scroll and restoration, keyboard directional navigation, Sort/Filter/pagination, resize/drag and saved column state, JS/network error checks, and OFF native equivalence. Test existing explicit options owned by other integrations. Independently inspect the evidence and candidate package. No generic CI check alone qualifies browser RTL.

**Cross-product boundary:** after qualifying PersianGravity's own candidate, install that *unreleased* ZIP beside current real GPP and licensed Gravity Flow in the GPP lab. Measure OFF/ON interactions with GPP CSS forced-LTR internals, column reordering, InboxInitialGeometryGuard, persisted state, horizontal scroll, touch/keyboard, sorting/filtering/pagination. Compare before/after and change GPP code only when an authentic conflict is demonstrated and its Owner-approved locked contract has been deliberately reviewed/superseded. A candidate that makes a single AG Grid screenshot look right is insufficient.

**G-013 remains distinct:** historical document-root `direction:ltr` came from Gravity Forms `gform_admin-css` and is a different test-enforcement defect. The G-013 PR must later replace that invalid document-root-only assertion with truthful post-grid-readiness and native grid-mode checks. Do not suppress the known failure, overwrite G-013 evidence, or call the real `WITH_VAZIR` profile complete before its full runtime data and separate publisher pass.

No auto-merge, public release, deployment, vendor patch or modification of GPP source is authorized by this candidate.
