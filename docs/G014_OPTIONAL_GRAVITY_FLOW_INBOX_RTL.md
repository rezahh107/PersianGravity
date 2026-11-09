# G-014 — Optional native Gravity Flow Inbox RTL and read-only status v1

Status at the documentation-edit intake: PR #114 OPEN / **not Draft** / UNMERGED / UNRELEASED. Reciprocal GPP #174 Settings integration is implemented. The last **product-code-qualified** PersianGravity commit is `8d84c1e9462a8d544d90491887907dabb750ec80`, tested with GPP #174 `c8cf9fd7bb615075402b78eaa1f01dd33d855d28` in licensed WU008 run [42517920199](https://github.com/rezahh107/PersianGravity/actions/runs/42517920199): **27/27 focused authentic Chromium Settings controls PASS**. Documentation-only commits after that tested commit are not retroactively qualified by its CI. Independent G-013 Vazir PR #113 is untouched. Merge, release, tag, and deployment remain separate Owner actions.

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

- Mode A: intended ag-ltr internal Grid with Persian RTL text and GPP five-column presentation. **This is a schematic goal, not proof of aligned scrolling or persisted Grid state**: independent GPP #174 tests include failures with PGR OFF and GPP axis ON.
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

GPP ON/OFF four-axis CSS behavior and the historical 34 controls were separately recorded in the GPP run. **These are historical evidence, not re-executed by PR #114's 27 Settings controls.** The 27 controls validate the reciprocal Settings behavior, permissions, legitimate saves, rejection of invalid or unauthorized saves, and restoration; they do not repair or close the 28 failures.

## Evidence and remaining acceptance

Historical licensed PR #102 WU008 G-014 observations and PR #113 G-013 results remain scoped to their original Heads. The previous PR #114 source `237e8006fae00be586d90a5b76666ceef7a025ca` + GPP `c8cf9fd7bb615075402b78eaa1f01dd33d855d28` passed the earlier **19** reciprocal Settings controls in [WU008 42473965646](https://github.com/rezahh107/PersianGravity/actions/runs/42473965646); that is **superseded for current-source claims** by the later tested PGR commit `8d84c1e9462a8d544d90491887907dabb750ec80`.

At that latter exact source commit:
- [WU008 licensed WordPress/Chromium run 42517920199](https://github.com/rezahh107/PersianGravity/actions/runs/42517920199): both real integration and evidence publication jobs SUCCESS. The job log attests `PGR_G014_RECIPROCAL_BROWSER_PASS cases=27` and `PGR_G014_RECIPROCAL_LICENSED_EVIDENCE_PASS controls=27`. Covered cases include four actual PGR/GPP combinations, mixed locales, authorized Settings saves/reloads, invalid/missing nonces, anonymous/subscriber denials, **valid nonce + Subscriber POST denied** for both plugins, inactive GPP, missing Flow, and exact read-back restoration of GPP/PGR option state, plugin activation and site/admin locale. Evidence artifact `wu008-real-integration-42517920199-1` (ID `13633095057`).
- [CI 42517920195](https://github.com/rezahh107/PersianGravity/actions/runs/42517920195): SUCCESS, including quality and PHP 8.2/8.3/8.4/8.5 unit jobs.
- [Artifact Install Smoke 42517920252](https://github.com/rezahh107/PersianGravity/actions/runs/42517920252): SUCCESS.
- [G008 Jalali Presentation Runtime 42517920253](https://github.com/rezahh107/PersianGravity/actions/runs/42517920253): SUCCESS.

Those are **attributable tests on PGR `8d84c1e`**, not automatic CI evidence for this documentation-only successor commit. Settings success does **not** establish native AG Grid compatibility. Actual five-column order, saved state, horizontal scroll, touch, keyboard, and native interactions remain separate product concerns. PR #114 has not been merged, released, tagged, or deployed by this documentation work.

No GPP/vendor/G-013/Inbox 2 product code, production setting, Merge, release, tag, or deployment is changed by this **documentation update**. The PR itself already contains earlier changes to the WU008 test workflow/harness; do not describe the entire PR as leaving CI workflow files unchanged.
