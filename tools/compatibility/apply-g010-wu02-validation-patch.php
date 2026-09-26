#!/usr/bin/env php
<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/docs/VALIDATION.md';
$content = file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "Cannot read docs/VALIDATION.md.\n");
    exit(1);
}
$marker = '## G-010 / WU-02 — Compatibility Diagnostics Foundation';
if (str_contains($content, $marker)) {
    fwrite(STDERR, "WU-02 validation section already exists.\n");
    exit(1);
}
$section = <<<'MD'

## G-010 / WU-02 — Compatibility Diagnostics Foundation

Scope: reporting/governance infrastructure only. The change adds the bounded Gravity Flow diagnostics catalog/recorder/latest reporting snapshot and reuses the existing System Status page. Current Flow adapter eligibility, exact-version/source/caller-chain gates, package authority and presentation behavior are intentionally unchanged.

Executed pre-PR implementation evidence:

- source Head `3eba01fe31046b98b0108a1ffd213cd0d07cb0d4`;
- CI run `36272863126` — SUCCESS;
- quality job — SUCCESS, including shipped-PHP syntax, WordPress Coding Standards, PHPCompatibility, JavaScript tests, pinned WordPress Core localization contracts, provider integrity/drift and runtime-integrity guard;
- PHPUnit jobs on PHP 8.2, 8.3, 8.4 and 8.5 — SUCCESS, including diagnostics governance, System Status reporting, persistence fail-closed behavior, WU-01 package-authority protection and existing adapter guard assertions.

At this pre-PR checkpoint, PR-triggered Artifact Install Smoke, G006 Gravity Forms Runtime, G008 Jalali Presentation Runtime and WU008 Licensed Real Integration are **NOT EXECUTED for WU-02**. They must be evaluated on the final PR Head if path filters trigger them. Green unit/CI evidence does not itself prove authentic licensed-host/browser behavior.
MD;
file_put_contents($path, rtrim($content) . "\n" . $section . "\n");
