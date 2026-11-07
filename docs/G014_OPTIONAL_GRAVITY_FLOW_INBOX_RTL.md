# G-014 — Optional native Gravity Flow Inbox RTL and read-only status v1

Status: draft on current main; not yet GPP-integrated or released. Independent G-013 Vazir PR #113 is untouched.

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

## Evidence and remaining acceptance

Historical licensed PR #102 WU008 G-014 observations and PR #113 G-013 failure remain historical, scoped to their exact Heads; this branch has not yet demonstrated authentic GPP coexistence. PHPUnit/stub results prove only the PHP contract. True native RTL and GPP two-mode compatibility require separate licensed Gravity Flow 3.1.0 plus both actual plugins, admin/frontend fa_IR/en_US including mixed site/user locales, 1280/390px, OFF→ON→OFF reloads, five columns, header/body scroll, saved state, sort/search/filter/page, resize/drag, live refresh, keyboard/touch, no console/network errors and permission cases.

No GPP, vendor, G-013, Inbox 2, production setting, CI workflow, merge, release, tag or deployment is changed here.
