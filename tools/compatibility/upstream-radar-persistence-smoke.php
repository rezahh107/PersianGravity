#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/upstream-radar.php';

// Consumes the *live* workflow's scanned candidates, but persists into an
// isolated copy. No PR-triggered job writes canonical reviewed observations.
[$script, $root, $candidateDir] = $argv + array( null, null, null );
if (!is_string($root) || !is_string($candidateDir) || !is_dir($root) || !is_dir($candidateDir)) {
    fwrite(STDERR, "usage: upstream-radar-persistence-smoke.php ISOLATED_ROOT LIVE_CANDIDATES_DIR\n");
    exit(1);
}

$protected = array();
foreach (array('gravityperks/2.3.17.json', 'gravityflow/3.1.1.json', 'gravityflow/3.1.1.1.json') as $file) {
    $path = $root . '/tools/compatibility/upstream-observations/' . $file;
    $protected[$path] = hash_file('sha256', $path);
    if ($protected[$path] === false) {
        throw new RuntimeException('missing protected reviewed record: ' . $file);
    }
}
$seen = array();
$files = glob(rtrim($candidateDir, '/') . '/*/*.json') ?: array();
if ($files === array()) {
    throw new RuntimeException('live radar produced no candidates to exercise');
}
foreach ($files as $path) {
    $candidate = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    PGR_Upstream_Radar::validateObservation($candidate);
    if (basename(dirname($path)) !== $candidate['product_key']
        || basename($path) !== $candidate['detected_upstream_version'] . '.json'
        || isset($seen[$candidate['product_key']])) {
        throw new RuntimeException('ambiguous candidate identity or duplicate: ' . $path);
    }
    $seen[$candidate['product_key']] = true;
    $status = PGR_Upstream_Radar::persistCandidate($candidate, $root);
    PGR_Upstream_Radar::assertPersistenceOutcome($candidate['product_key'], $status);
    if ($candidate['product_key'] === 'gravityperks' && $status !== 'REVIEWED_CURRENT_SOURCE_ATTESTED') {
        throw new RuntimeException('Gravity Perks did not meet exact current-source review');
    }
    fwrite(STDOUT, $candidate['product_key'] . ': ' . $status . " (isolated live candidate)\n");
}
if (!isset($seen['gravityperks'], $seen['gravityflow'])) {
    throw new RuntimeException('Gravity Flow or Gravity Perks live candidate missing');
}
foreach ($protected as $path => $digest) {
    if (hash_file('sha256', $path) !== $digest) {
        throw new RuntimeException('previously reviewed observation was modified: ' . $path);
    }
}
fwrite(STDOUT, "Live persistence boundary verified: reviewed records unchanged\n");
