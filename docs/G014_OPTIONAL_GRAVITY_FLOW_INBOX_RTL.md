# G-014 — Optional native Gravity Flow Inbox RTL and read-only status v1

Status: OPEN/DRAFT/UNMERGED/UNRELEASED PR #114. Reciprocal GPP #174 Settings integration is implemented; the exact current source pair was exercised in licensed WU008 run 42473965646 (19 original authentic Chromium Settings controls PASS). Independent G-013 Vazir PR #113 is untouched.

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

- Mode A: ag-ltr internal Grid, Persian RTL cells/heading and the accepted GPP five-column presentation, aligned scroll and preserved native saved state.
- Mode B before GPP qualification: native ag-rtl can conflict with GPP's four LTR-axis CSS overrides, column order and persisted restore; the historical 434px offset is only one old lab observation, not a universal measurement.

The anchor actions lead to concrete compatibility checks, an embedded GPP verification guide and the existing OFF checkbox; no fake GPP admin URL, hidden write, reset, auto-toggle or guaranteed compatibility status is presented. The full illustrated diagram and actual user/browsers acceptance remain further UI work; the current panels are clearly schematic.

## Reciprocal GPP compatibility warning — PR #114 follow-on

PersianGravity owns its original Settings API checkbox, default **OFF**, and the public read-only `pgr_gravity_flow_inbox_rtl_status()` contract. GPP PR #174 independently owns `gpp_gravity_flow_inbox_axis_status()` and its own four-node physical LTR-axis switch (default ON). The PersianGravity Settings page consumes that **public, read-only** version-1 interface, validates `api_version`, `capability`, `scope`, `requested`, `effective` and `grid_mode`, catches optional-provider exceptions, and never reads/writes the private GPP option.

On the existing administrator-only Settings page, each verified state shows the specifically observed Inbox 1 failure from pinned GPP run 42469384530. PGR ON + GPP ON warns about fa_IR Frontend reversed five-column order and possible LTR-axis conflict. PGR ON + GPP OFF still warns about reversed Frontend order. PGR OFF + GPP OFF warns about the proven fa_IR Frontend 390px horizontal scroll divergence. PGR OFF + GPP ON retains only the demonstrated 390px Admin mobile scroll warning when the request locale is Persian. Mixed-locale Admin touch failures are distinguished from Frontend order; unrelated or unsupported conditions remain **UNKNOWN**, not compatible. The UI repeats the actual site/request locale and explicitly scopes evidence to PGR #114, GPP #174, Gravity Flow 3.1.0 and the tested Chromium environment. It never infers the initialized `ag-rtl/ag-ltr` mode or the installed GPP source version from a PHP status.

Warnings are recalculated from current status on every authorized Settings GET after WordPress Settings API saves. Neither plugin toggles the other, clears stored Grid state, changes native task/order/permission semantics, or claims that switching either axis OFF repairs independent defects. Existing educational diagrams for ag-ltr/ag-rtl are retained and labelled schematic; native Inbox 1 AG Grid is distinct from experimental Inbox 2 cards. GPP missing/disabled/unsupported/malformed/throwing status is UNKNOWN, with no fatal error.

Historical 96-case four-state result for GPP #174 at `c8cf9fd7bb615075402b78eaa1f01dd33d855d28`: **40 PASS / 28 FAIL / 28 NOT_PROVEN**. GPP ON/OFF CSS behavior and 34 controls passed in the original run; **these are historical evidence, not new tests of this PersianGravity UI change**. Exact source: https://github.com/rezahh107/Gravity-Presentation-Profiles/actions/runs/42469384530 . A separate focused WordPress runtime checks the reciprocal notice, permissions, invalid nonce and legitimate saved settings, without repeating or overwriting the 96-case suite.

## Evidence and remaining acceptance

Historical licensed PR #102 WU008 G-014 observations and PR #113 G-013 failure remain historical, scoped to their exact Heads. The exact PR #114 (237e8006fa) + GPP #174 (c8cf9fd7bb) source pair already passed bounded reciprocal Settings qualification in authentic licensed WordPress/Chromium (WU008 run 42473965646, 19 original PASS controls). The follow-on candidate adds valid-nonce Subscriber POST controls and exact PGR/GPP option, plugin and locale restoration/readback, whose results require a successful new exact-Head WU008 run. Settings success does NOT establish native AG Grid compatibility. Full RTL Mode B, five-column order, saved Grid state, scrolling, touch, keyboard and native interactions remain open; the historical GPP 96-case suite retains 28 FAIL and 28 NOT_PROVEN. PR #114 remains draft, unmerged and unreleased.

No GPP, vendor, G-013, Inbox 2, production setting, CI workflow, merge, release, tag or deployment is changed here.
