# G-013 — PR #100 Scope and Evidence Handoff

> **Qualification-only / NOT COMPLETE.** This is an evidence and ownership handoff, not authorization to implement an RTL product feature, change an assertion without a correctness oracle, or merge/release PR #100.

## Freshness and exact-run references

- Repository: `rezahh107/PersianGravity`, PR [#100](https://github.com/rezahh107/PersianGravity/pull/100); reviewed source Head **`266915d30df0f1ad494c31cfae4cb8301762ec97`** (2026-10-31).
- At review, PR #100 was **open, Draft and unmerged**. Always query the current live PR Head again before resuming; this reviewed Head is not a promise about any future tip.
- Exact-head [WU008 run 41196821309](https://github.com/rezahh107/PersianGravity/actions/runs/41196821309): **FAILURE**, with the original `Expected authentic RTL for gravity-flow` exception in `tests/real-integration/vazir-coexistence-browser.mjs`. Its dependent evidence publisher was **SKIPPED**.
- Exact-head [CI run 41196821272](https://github.com/rezahh107/PersianGravity/actions/runs/41196821272) and [Artifact Install Smoke run 41196821335](https://github.com/rezahh107/PersianGravity/actions/runs/41196821335): **SUCCESS**. These do not establish G-013 browser qualification.
- The WU008 upload artifact is `wu008-real-integration-41196821309-1` (artifact ID `13040719408`). It contains bounded diagnostic outputs, not publishable licensed vendor sources.

The original WU008 four-manifest / 67-file publisher validation block was checked text-for-text against `main` at review and found **identical**. The added G-013 publisher validation remains a separate, gated path; successful execution of that new publisher gate has **not** been observed. Keep the original baseline checks, independent publisher checks, exact Head/allowlist controls, and the existing historical evidence unchanged.

## Three independent findings and owners

### 1. G-013: official Vazir 1.5.0 comparison — PR #100 ONLY, still incomplete

PR #100 is a **test-only** coexistence qualification, not an RTL implementation PR. It retains BASELINE (no Vazir) and proposes separately attributable WITH_VAZIR profiles using the official real release.

The exact-head run halted in `BASELINE/fa_IR` on the Admin Inbox root-direction assertion **before** it could install/activate official Vazir or exercise any WITH_VAZIR case. The artifact contains **one incomplete** `vazir-coexistence-evidence/baseline-fa_IR.json` with status `FAIL`, plus three baseline/diagnostic screenshots. There is no completed fa_IR/en_US BASELINE+WITH_VAZIR profile matrix; there are **no executed 72 observations / 36 pairs / 32 screenshots** for G-013 and no successful official-release font/WOFF2 comparison or publisher acceptance. A local validation script, CI green or an independent RTL experiment cannot substitute for these missing observations.

The proposed producer/comparator/publisher contract calls for four distinct profiles, nine surfaces × two viewports each, 72 observations, 36 unique BASELINE–WITH_VAZIR pairs, 32 JPEGs, exact official release and settings identity, per-viewport font responses, restored plugin state, and separate evidence publication. These are **acceptance requirements, not achieved results**.

### 2. Genuine Gravity Flow AG Grid RTL initialization — separate opt-in PersianGravity repair PR

For **authentic licensed Gravity Flow 3.1.0**, WU008 independently verified ZIP SHA-256
`ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` and installed version `3.1.0`.

With no experimental repair active, the stable fa_IR Admin Inbox had CSS-computed RTL on its visible AG Grid but the Grid carried `ag-ltr`; serialized `grid_options.enableRtl` was absent. This is a distinct internal initial-configuration defect, **not** proof that font coexistence failed.

The source index from the authenticated vendor package identifies `includes/inbox/class-inbox-service-provider.php` (`get_config_for_grids`, line 242), `includes/config/class-js-config.php` (localization and configuration), and the real `gravityflow_js_config_shared` filter. A **disposable** WU008 MU-plugin, `tests/real-integration/g013-grid-rtl-probe.mu.php`, set `grid_options.enableRtl` before Grid initialization. `tests/real-integration/g013-flow-grid-runtime-probe.mjs` then observed **12/12** ready cases: Admin Inbox, frontend Inbox with G008 rows, and public frontend Inbox, each at 1280/390 in fa_IR/en_US. In fa_IR, `enableRtl=true` and `ag-rtl`; in en_US, `enableRtl=false` and `ag-ltr`. Actual 390px scrolling was direction-sensitive and restored; keyboard probes were exercised; no JS/network failures were observed in those cases. This proves **the candidate pre-initialization seam in that exact runtime**, not production admission or general compatibility.

**Ownership:** any new opt-in, version/capability-bounded PersianGravity adapter must be designed, approved, implemented, regression-tested, and reviewed in a **separate PR**. It must respect host-owned Grid state and existing options, distinguish site locale from user locale where necessary, handle mixed-locale scenarios and conflicts, and preserve en_US/native/disabled behavior. No RTL production code, global CSS override, or vendor changes belong in PR #100. The vendor also owns the default behavior in Gravity Flow itself; the separate PersianGravity integration is an opt-in compatibility repair, not a takeover of Gravity Flow workflow semantics.

### 3. HTML-root-direction assertion — distinct G-013 **test enforcement** defect

The original `vazir-coexistence-browser.mjs` assertion rejects fa_IR Gravity Flow when the **HTML element's computed direction** is LTR, even if the actual Inbox is RTL. Exact-runtime browser CSS-causality probes showed HTML `dir="rtl"` with computed `ltr`, caused by the loaded Gravity Forms `gform_admin-css` (`assets/css/dist/admin.min.css`, a broad `direction:ltr` rule). Disabling **only** that stylesheet in the disposable browser changed HTML computed direction LTR→RTL; restoring it returned RTL→LTR. English controls did not exhibit this flip. The experiment always restored the stylesheet. This cause is separate from the actual AG Grid `enableRtl` omission.

**Ownership:** repair the G-013 **test oracle and its enforcement boundary** in PR #100, not Gravity Forms CSS or PersianGravity runtime. Wait for a fully initialized, non-loading AG Grid and validate the actual intended visible and internal Grid behavior/interaction, while retaining HTML/body direction as diagnostic evidence. Correctness criteria must still fail or explicitly block on a genuine RTL defect: **no exemption, unconditional PASS, assert suppression, or silently reclassifying the Grid's `ag-ltr` as an RTL success**. Add a meaningful negative control that proves an incorrect Grid mode is rejected.

## Exact conditions before resuming/completing PR #100

1. **Resolve ownership/dependency without scope leakage.** Obtain the Owner decision on the separate opt-in Gravity Flow RTL repair PR. If its behavior is a prerequisite for the final G-013 RTL acceptance oracle, qualify and land it separately first, then refresh/rebase PR #100 from the approved merged baseline. If it is not available, mark genuine Grid RTL acceptance blocked rather than weakening a gate.
2. **Repair the G-013 assertion as a test-only change.** Use deterministic ready-state checks, a correct visible/internal Grid validation boundary, fa_IR/en_US controls, both viewports, and a falsification fixture. Keep the real HTML-root CSS anomaly independently recorded; retain original evidence of the previous failure.
3. **Execute the actual full comparison.** After the baseline is correctly qualified, run all four authentic BASELINE/WITH_VAZIR profiles, including desktop/mobile per-surface observations, release/option verification, actual font HTTP/FontFace/computed usage, error capture, 36 paired classifications and cleanup. Do not infer a passed WITH_VAZIR case from the disposable Grid-filter experiment.
4. **Validate current Head and publication gates.** Run exact-Head WU008, inspect the resulting full evidence (72 observations, 36 comparisons, 32 screenshots, four manifests), verify independent comparator and credential-free publisher prerequisites, original 67-file baseline gate, SHA and no-production-font safeguards, and review any intentionally excluded portals or adversarial font/settings cases explicitly. Publisher may run **only** after its real producer/prerequisites pass; never publish partial evidence as completed results.
5. **Preserve governance boundaries.** Keep PR #100 Draft/Open/unmerged pending actual qualification and Owner review. No product/vendor file modifications, CSS workaround, tag/release/deploy, licensed code export, historical evidence rewrite or publisher weakening.

## Evidence limitations

- The filter experiment installed a throwaway MU-plugin only inside the ephemeral WordPress runner and removed it before the failing G-013 comparison. Its evidence supports technical feasibility; it **is not** a production PersianGravity implementation.
- User-vs-site mixed locales, third-party preexisting `enableRtl`, every AG Grid menu/popup/drag interaction, live Owner settings and future vendor versions are not established by these runs.
- CI and install-smoke success establish only the paths exercised there. WU008 failure and skipped publication are **correctly reported**, not certification of full G-013. Gravity Flow frontend G009 findings do not substitute for Admin Inbox observations.
- This handoff is descriptive and test-scoped. **No production patch is authorized or implemented.**
