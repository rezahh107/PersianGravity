#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/upstream-radar.php';

// Replay the *same live scan results* through first-run and repeat-run
// persistence in an isolated copy. PR jobs never write canonical observations.
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

$summaryPath = dirname(rtrim($candidateDir, '/')) . '/summary.json';
$summary = json_decode((string) file_get_contents($summaryPath), true, 512, JSON_THROW_ON_ERROR);
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
    $seen[$candidate['product_key']] = $candidate;
}

$scanned = array();
foreach ($summary['results'] ?? array() as $result) {
    if ($result['observation_candidate'] === null) {
        continue;
    }
    $key = $result['product_key'];
    if (!isset($seen[$key]) || $seen[$key] !== $result['observation_candidate']) {
        throw new RuntimeException('live candidate file differs from scanned summary: ' . $key);
    }
    $scanned[$key] = true;
}
if (count($scanned) !== count($seen) || !isset($seen['gravityperks'], $seen['gravityflow'])) {
    throw new RuntimeException('incomplete or unmatched live candidates');
}

$profiles = PGR_Upstream_Radar::loadProfiles();
$first = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $root);
foreach ($seen as $key => $candidate) {
    $status = $first[$key] ?? null;
    if ($key === 'gravityperks' && $status !== 'REVIEWED_CURRENT_SOURCE_ATTESTED') {
        throw new RuntimeException('Gravity Perks did not meet exact current-source review');
    }
    if ($key !== 'gravityperks' && $status !== 'CREATED') {
        throw new RuntimeException($key . ': expected first-run creation, got ' . $status);
    }
    fwrite(STDOUT, $key . ': ' . $status . " (isolated first run)\n");
}

$repeat = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $root);
foreach ($seen as $key => $candidate) {
    $expected = $key === 'gravityperks' ? 'REVIEWED_CURRENT_SOURCE_ATTESTED' : 'AUTOMATED_DEDUPLICATED';
    if (($repeat[$key] ?? null) !== $expected) {
        throw new RuntimeException($key . ': unexpected repeat-run persistence outcome');
    }
}
foreach ($protected as $path => $digest) {
    if (hash_file('sha256', $path) !== $digest) {
        throw new RuntimeException('previously reviewed observation was modified: ' . $path);
    }
}
fwrite(STDOUT, "Live persistence first/repeat run verified: reviewed records unchanged\n");
