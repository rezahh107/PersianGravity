#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function replace_once(string $path, string $old, string $new): void {
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Cannot read {$path}.\n");
        exit(1);
    }
    if (substr_count($content, $old) !== 1) {
        fwrite(STDERR, "Patch anchor drifted in {$path}.\n");
        exit(1);
    }
    file_put_contents($path, str_replace($old, $new, $content));
}

$testPath = $root . '/tests/GravityFlowCompatibilityDiagnosticsTest.php';
replace_once(
    $testPath,
    "\t\tdefine( 'ABSPATH', dirname( __DIR__ ) . '/' );\n\t\tdefine( 'GRAVITY_FLOW_VERSION', '3.1.0' );\n\t\trequire_once dirname( __DIR__ ) . '/tests/bootstrap.php';\n\t\trequire_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';",
    "\t\tdefine( 'GRAVITY_FLOW_VERSION', '3.1.0' );"
);

$agentsPath = $root . '/AGENTS.md';
$agentsOld = <<<'MD'
Gravity Flow qualification package authority lives at `tools/compatibility/gravityflow-package.json`. Active qualification consumers must resolve it through the repository helper rather than discovering, substituting, or hard-coding another Gravity Flow package reference.

Required validation categories:
MD;
$agentsNew = <<<'MD'
Gravity Flow qualification package authority lives at `tools/compatibility/gravityflow-package.json`. Active qualification consumers must resolve it through the repository helper rather than discovering, substituting, or hard-coding another Gravity Flow package reference.

Gravity Flow compatibility diagnostics are informational maintenance evidence, not runtime authority. `PGR_Gravity_Flow_Compatibility_Diagnostics` reuses established G-008 capability IDs, accepts only the bounded `AVAILABLE` / `DEGRADED` / `UNAVAILABLE` / `NOT_EVALUATED` states and source-owned `PGR-GFLOW-*` reasons, and exposes only a latest bounded reporting snapshot. The optional cross-request option is non-autoloaded and must never be read by a production adapter to decide activation. Unknown/new Gravity Flow versions are not automatically incompatible; compatibility eligibility changes only through later evidence-backed adapter contract migrations while existing guards remain intact.

Required validation categories:
MD;
replace_once($agentsPath, $agentsOld, $agentsNew);

$architecturePath = $root . '/docs/ARCHITECTURE.md';
$architectureOld = <<<'MD'
## Safe disable
MD;
$architectureNew = <<<'MD'
## G-010 Gravity Flow compatibility diagnostics foundation

`PGR_Gravity_Flow_Compatibility_Diagnostics` is a small reporting/governance component loaded with the existing non-disableable admin infrastructure. WU-02 does not wire current adapters into it and does not alter any existing exact-version, source-fingerprint, caller-chain, locale, source-data, range or conversion gate. Runtime safety decisions remain adapter-local; diagnostics may only observe a decision after the adapter has made it.

The capability allowlist reuses the established G-008 Gravity Flow surface IDs for Inbox, Status, Entry Detail, Timeline/history and inherited Print. It is intentionally not a second source-semantics registry. Observations use exactly four states: `AVAILABLE`, `DEGRADED`, `UNAVAILABLE`, and `NOT_EVALUATED`. Stable `PGR-GFLOW-*` reasons classify contract satisfaction, unqualified host identity/version, unavailable seam/source contract, missing request/caller context, invalid source data, non-applicable module/locale/context, conversion failure, and absence of trustworthy evaluation. Human explanations are source-owned by the reason catalog rather than accepted as free-form request data.

The request-local recorder accepts only a known capability ID, state and reason. It has no generic context/payload parameter and records no user, form, entry, URL, cookie, token, request body, source excerpt or stack trace. Within one request, precedence is deterministic and failure-preserving: `UNAVAILABLE > DEGRADED > AVAILABLE > NOT_EVALUATED`; equal-state later observations replace earlier observations.

System Status is a separate request, so the reporter may keep one small cross-request latest snapshot in the non-autoloaded `pgr_gravityflow_compatibility_latest` option. Schema version 1 stores only `capability_id`, `state`, `reason_id`, the source-owned short summary, `observed_at`, and the observed Gravity Flow version. The complete serialized snapshot is capped at 8192 bytes, observations older than seven days or implausibly future-dated are discarded, and persisted evidence from another currently observed Gravity Flow version is not trusted. Missing, malformed, stale or version-mismatched data renders as `NOT_EVALUATED`, never as incompatible and never as activation authority.

The existing `PGR_Product_Admin` System Status page renders the reporting snapshot read-only. It provides no force-enable or destructive action. WU-02 by itself makes no unknown/new Gravity Flow version compatible; later WU-03/WU-04 must first prove replacement contracts and only then may change adapter guards.

## Safe disable
MD;
replace_once($architecturePath, $architectureOld, $architectureNew);
