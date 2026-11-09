# G-014 — Optional native Gravity Flow Inbox RTL and read-only status v1

Status: PR #114 OPEN / Ready for Review / UNMERGED / UNRELEASED. Last *executed* product Head was `53379268ca554b0dd81e40a3ee9d3cb74884c5e1` with GPP `c8cf9fd7bb615075402b78eaa1f01dd33d855d28`; 35/35 reciprocal Settings controls PASS in WU008 #42597965918 (not 27/27). The successor guidance/documentation commit requires its own exact-Head checks. GPP/G-013/vendor and host Grid remain unchanged.

## Native implementation carried forward from qualified PR #102

Under PersianGravity → Settings, the existing pgr_settings gravity_flow_inbox_rtl checkbox defaults OFF. New sites and old options with no key remain OFF. While OFF (or invalid), no gravityflow_js_config_shared filter is registered and no enableRtl, WordPress option, vendor Grid state or GPP setting is written.

When ON (strict integer 1), the existing production adapter is loaded at gform_loaded after Gravity Forms initialises. It only admits the authentic Gravity Flow 3.1.0 host with the canonical basename gravityflow/gravityflow.php. The filter priority is 11, after the native producer's priority 10, and it only adds a missing enableRtl value for producer-verified authentic Inbox Grid IDs. A preexisting enableRtl of any value is retained as owned by the host/other integration. Request locale uses is_rtl(), not the site option or document CSS. Native Inbox auth, sort, page, saved state, row data and AG Grid construction remain host-owned.

## Public read-only PHP integration API — version 1

PersianGravity loads the pure function pgr_gravity_flow_inbox_rtl_status() unconditionally when its plugin main file loads, independent of G-014 ON/OFF and Gravity Forms/Flow presence. No internal G-014 class needs to be loaded by consumers.

Return keys and stable values:

- api_version: integer 1.
- capability: supported (this PersianGravity source contains G-014); if function does not exist, GPP must handle older/absent PersianGravity as unknown.
- requested: on, off, unknown; missing setting defaults off; malformed stored settings return unknown but fail closed OFF at runtime.
- host: admitted (exact Gravity Flow 3.1.0 + canonical basename), unsupported (known mismatched identity/version), absent (no vendor identity after plugins_loaded), unknown (early lifecycle or incomplete identity).
- request_rtl: true/false from effective WordPress request locale after init, or null while lifecycle/locale not yet ready. Admin-user and site locales may differ.
- grid_mode: always not_observed. Browser AG Grid mode must be observed independently after native Grid load; ON is never proof of ag-rtl or GPP compatibility.

Sample optional consumer:

    $status = function_exists( 'pgr_gravity_flow_inbox_rtl_status' )
        ? pgr_gravity_flow_inbox_rtl_status()
        : null;
    if ( is_array( $status ) && 1 === $status['api_version'] ) {
        // Read only: requested/host/request_rtl; do not write PGR options.
        // Independently observe the loaded Grid's ag-rtl/ag-ltr and column state.
    } else {
        // No supported PGR seam. Retain GPP's native Mode A fallback.
    }

The function only returns non-sensitive enums/Boolean state, not a REST endpoint, user records or a permission grant. Never automatically expose admin links to low-privilege users. PersianGravity Settings UI uses manage_options and the Settings API for writes. GPP must not duplicate the setting or write into private pgr_settings.

Supported source ownership: PHP status reports PGR capability/intention/host identity; the browser must prove actual Grid mode and GPP must qualify presentation compatibility. Other filters may take precedence; PGR never silently overwrites an explicit option.

## RTL-UX-EXPLAIN-001 presentation

The Settings field includes the exact Owner-approved Persian warning, an expandable section titled «جزئیات فنی و روش بررسی», an admin-only compatibility summary, and accessible schematic textual panels:

- Mode A: **intended** ag-ltr internal Grid with Persian RTL text and GPP five-column presentation. Header/body scroll alignment and saved-layout recovery are *goals requiring independent proof*, not guaranteed in all settings, locales, viewports and reloads; this is schematic only. GPP #174 historically recorded failures even with PGR OFF and the GPP axis ON.
- Mode B before GPP qualification: native ag-rtl can conflict with GPP's four LTR-axis CSS overrides, column order and persisted restore; the historical 434px offset is only one old lab observation, not a universal measurement.

The anchor actions lead to concrete compatibility checks, an embedded GPP verification guide and the existing OFF checkbox; no fake GPP admin URL, hidden write, reset, auto-toggle or guaranteed compatibility status is presented. The full illustrated diagram and actual user/browsers acceptance remain further UI work; the current panels are clearly schematic.

## Reciprocal GPP compatibility warning — PR #114 follow-on

PersianGravity owns its original Settings API checkbox, default **OFF**, and the public read-only `pgr_gravity_flow_inbox_rtl_status()` contract. GPP PR #174 independently owns `gpp_gravity_flow_inbox_axis_status()` and its own four-node physical LTR-axis switch (default ON). The PersianGravity Settings page consumes that **public, read-only** version-1 interface, validates `api_version`, `capability`, `scope`, `requested`, `effective` and `grid_mode`, catches optional-provider exceptions, and never reads/writes the private GPP option.

On the existing administrator-only Settings page, the warning classifier shows an **evidence-scoped representative failure** for an admitted combination from the pinned GPP run 42469384530. It is **not** an exhaustive map of all 28 failing test scenarios, nor evidence that the installed GPP build matches the pinned tested source. PGR ON + GPP ON warns about fa_IR Frontend reversed five-column order and possible LTR-axis conflict. PGR ON + GPP OFF still warns about reversed Frontend order. PGR OFF + GPP OFF warns about the proven fa_IR Frontend 390px horizontal scroll divergence. PGR OFF + GPP ON retains only the demonstrated 390px Admin mobile scroll warning when the request locale is Persian. Mixed-locale Admin touch failures are distinguished from Frontend order; unrelated or unsupported conditions remain **UNKNOWN**, not compatible. The UI repeats the actual site/request locale and explicitly scopes evidence to PGR #114, GPP #174, Gravity Flow 3.1.0 and the tested Chromium environment. It never infers the initialized `ag-rtl/ag-ltr` mode or the installed GPP source version from a PHP status.

Warnings are recalculated from current status on every authorized Settings GET after WordPress Settings API saves. Neither plugin toggles the other, clears stored Grid state, changes native task/order/permission semantics, or claims that switching either axis OFF repairs independent defects. Existing educational diagrams for ag-ltr/ag-rtl are retained and labelled schematic; native Inbox 1 AG Grid is distinct from experimental Inbox 2 cards. GPP missing/disabled/unsupported/malformed/throwing status is UNKNOWN, with no fatal error.

### Failure inventory, evidence boundary, and coverage of the warnings

The independent GPP #174 [four-state browser run 42469384530](https://github.com/rezahh107/Gravity-Presentation-Profiles/actions/runs/42469384530), Head `c8cf9fd7bb615075402b78eaa1f01dd33d855d28`, used PGR source `311c2f5d188aec7254e11d37a351510736bd97de`, WordPress 6.8.3, Gravity Forms 3.1.1.1, licensed Gravity Flow 3.1.0, and Chromium 140.0.7339.16. Its published `gpp-fourstate-42469384530-1` artifact (`report.json` and `coverage.json`) records **96 scenarios: 40 PASS / 28 FAIL / 28 NOT_PROVEN**. The 28 FAIL records describe five recurring observed manifestations:

| Observed failure description | FAIL cases | Existing PersianGravity warning coverage |
| --- | ---: | --- |
| Header/body horizontal scroll divergence, including persistent offsets after 720 ms | 14 | Explicit for selected mobile/language/setting combinations; **not every failing scenario** |
| Native touch swipe did not move the horizontal grid | 6 | Explicit or mentioned for selected mobile combinations; **not every failing scenario** |
| Intended five-column right-to-left presentation order reversed | 4 | Explicit for PGR ON + GPP ON and PGR ON + GPP OFF on fa_IR Frontend, 1280px |
| Saved column width did not survive a same-mode OFF reload | 2 | Only generic saved-state caution; **not an explicit case-specific notice** |
| Saved width/order did not restore after OFF→ON→OFF / reload | 2 | Only generic persisted-state caution; **not an explicit case-specific notice** |
| **Total** | **28** | **Representative evidence-scoped warnings, not 28 automated repairs or 28 individually disclosed notices** |

Of the 28 failures, **16 occur with PGR OFF** (including both phase 0 and phase 2) and **12 with PGR ON**. Therefore they must not all be described as consequences *caused by enabling* PGR. Different surfaces, viewport widths, site/admin locales, GPP ON/OFF, and previous persisted state matter. The 28 FAIL records are classified `PRODUCT_OR_HARNESS_UNDETERMINED`: a browser assertion failed, but product-versus-harness root cause was not separately proven. The 28 `NOT_PROVEN` scenarios are **not FAIL and not PASS**.

The reciprocal warning is a **communication / informed-choice control**, not prevention, remediation, a per-scenario live diagnostic, or automatic recovery. It uses the public GPP v1 status contract; that contract does **not** report the installed GPP source SHA, and neither PHP API observes a constructed browser AG Grid. A recognized historical failure may be reported as a scoped warning, but neither that warning nor switching PGR/GPP ON/OFF proves the current site compatible. Unknown host, GPP interface, locale, setting, or nonqualified build means compatibility remains **NOT_PROVEN**, even if an old warning happens to match.

GPP ON/OFF four-axis CSS behavior and 34 historical Grid controls were separately recorded in the GPP run. **They were not re-executed by either the 27- or 35-control PGR Settings lane.** The 35 current Settings controls verify warning copy, locale decisions, permissions, saves and restoration—not any of the 28 historical Grid failures.

## Evidence and remaining acceptance

The latest **executed reciprocal Settings** evidence is [licensed WU008 #42597965918](https://github.com/rezahh107/PersianGravity/actions/runs/42597965918), run attempt 1, on exact PersianGravity `53379268ca554b0dd81e40a3ee9d3cb74884c5e1` with GPP #174 `c8cf9fd7bb615075402b78eaa1f01dd33d855d28`. Both the licensed WordPress/Chromium job and evidence-publication job completed **SUCCESS**. The actual job log records `PGR_G014_RECIPROCAL_BROWSER_PASS cases=35` and `PGR_G014_RECIPROCAL_LICENSED_EVIDENCE_PASS controls=35`. Artifact `wu008-real-integration-42597965918-1` (ID `13665675245`) includes `g014-reciprocal-warning-evidence/report.json` with **35/35 PASS** and exact source identities, WordPress 6.8.3, Gravity Forms 3.1.1.1, licensed Gravity Flow 3.1.0 and Chromium 140.0.7339.16.

The 35 controls preserve the prior 27, add six authenticated fa_IR/en_US safety-copy/accessible-label checks in same/mixed site/admin locales and two negative Arabic/Hebrew Admin classifications (`UNKNOWN`). Both Subscriber valid-nonce unauthorized POSTs returned HTTP **403**; original plugin activation, PGR/GPP option existence/value and site/user locales were restored with exact readback. In this fixture both plugin options were originally absent, so recovery from other preexisting values was **not** tested.

**Historical, not current Head:** Earlier PGR `8d84c1e9462a8d544d90491887907dabb750ec80` passed 27/27 reciprocal Settings controls in [WU008 #42517920199](https://github.com/rezahh107/PersianGravity/actions/runs/42517920199), artifact ID `13633095057`. These 27 PASS are not the current 35-test result.

For exact predecessor `53379268ca554b0dd81e40a3ee9d3cb74884c5e1`: [CI #42597965878](https://github.com/rezahh107/PersianGravity/actions/runs/42597965878) **SUCCESS** (gettext/quality and PHPUnit PHP 8.2–8.5), [ZIP Install Smoke #42597965895](https://github.com/rezahh107/PersianGravity/actions/runs/42597965895) **SUCCESS**, and [G008 Jalali Runtime #42597966035](https://github.com/rezahh107/PersianGravity/actions/runs/42597966035) **SUCCESS**. This successor's checks must be evaluated on its **own new commit**, not inferred from the predecessor.

Separate historical GPP [four-state browser #42469384530](https://github.com/rezahh107/Gravity-Presentation-Profiles/actions/runs/42469384530), on GPP `c8cf9fd7bb615075402b78eaa1f01dd33d855d28` with **older PGR `311c2f5d188aec7254e11d37a351510736bd97de`**, recorded **40 PASS / 28 FAIL / 28 NOT_PROVEN**. The 28 observed failures include header/body scroll (14), touch (6), reversed order (4) and persisted state (4), with causation still `PRODUCT_OR_HARNESS_UNDETERMINED`. None is repaired or proved resolved by the 35 Settings PASS; current-pair native Grid parity remains **NOT_PROVEN**.

The Mode A `ag-ltr` engine with Persian RTL text and five-column presentation is an **intended schematic**, not evidence of universal alignment or persistence: header/row scroll synchronization and saved-layout recovery must be tested under actual viewports, locale/setting combinations and reloads. Visible/accessible English and fa_IR gettext guidance now says so explicitly. The public status v1 (`grid_mode=not_observed`), default-OFF option, independent GPP setting, host/vendor/Grid ownership, and security checks remain unchanged. No merge, release or deployment has been authorized.

Earlier 19-control [WU008 #42473965646](https://github.com/rezahh107/PersianGravity/actions/runs/42473965646) on PGR `237e8006fae00be586d90a5b76666ceef7a025ca` is also historical. No older run is retroactively claimed on a later commit.
