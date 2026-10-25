#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/upstream-radar.php';

// Replay the *same live scan results* through first-run and repeat-run
// persistence in an isolated copy. PR jobs never write canonical observations.
[$script, $firstRunRoot, $committedRoot, $candidateDir] = $argv + array( null, null, null, null );
if (!is_string($firstRunRoot) || !is_string($committedRoot) || !is_string($candidateDir)
    || !is_dir($firstRunRoot) || !is_dir($committedRoot) || !is_dir($candidateDir)
    || realpath($firstRunRoot) === realpath($committedRoot)) {
    fwrite(STDERR, "usage: upstream-radar-persistence-smoke.php FIRST_RUN_ROOT COMMITTED_ROOT LIVE_CANDIDATES_DIR\n");
    exit(1);
}

/** Snapshot every observation and source-review byte, not a fixed list of historical filenames. */
function pgrRadarSmokeEvidenceHashes(string $root): array {
    $base = rtrim($root, '/') . '/tools/compatibility/';
    $hashes = array();
    foreach (array('upstream-observations', 'upstream-source-reviews') as $directory) {
        $path = $base . $directory;
        if (!is_dir($path)) {
            throw new RuntimeException('missing isolated evidence directory: ' . $directory);
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException('unreadable isolated evidence: ' . $file->getPathname());
            }
            $hashes[substr($file->getPathname(), strlen($base))] = $hash;
        }
    }
    ksort($hashes);
    return $hashes;
}

/** Only the explicitly tested automated candidate paths may differ between snapshots. */
function pgrRadarSmokeAssertOtherEvidenceUnchanged(array $before, array $after, array $candidates): void {
    foreach ($candidates as $key => $candidate) {
        if ($key !== 'gravityperks') {
            $relative = 'upstream-observations/' . $key . '/' . $candidate['detected_upstream_version'] . '.json';
            unset($before[$relative], $after[$relative]);
        }
    }
    if ($before !== $after) {
        throw new RuntimeException('unrelated, historical, reviewed or source-review evidence changed in isolation');
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

$pristine = pgrRadarSmokeEvidenceHashes($committedRoot);
if (pgrRadarSmokeEvidenceHashes($firstRunRoot) !== $pristine) {
    throw new RuntimeException('first-run and committed fixtures did not start from identical evidence');
}

$wasCommitted = array();
foreach ($seen as $key => $candidate) {
    if ($key === 'gravityperks') {
        continue; // The reviewed Gravity Perks observation is never a creation fixture.
    }
    $relative = 'tools/compatibility/upstream-observations/' . $key . '/' . $candidate['detected_upstream_version'] . '.json';
    $committedPath = rtrim($committedRoot, '/') . '/' . $relative;
    $firstPath = rtrim($firstRunRoot, '/') . '/' . $relative;
    $wasCommitted[$key] = is_file($committedPath);
    if (!$wasCommitted[$key]) {
        continue;
    }
    $existing = json_decode((string) file_get_contents($firstPath), true, 512, JSON_THROW_ON_ERROR);
    PGR_Upstream_Radar::validateObservation($existing);
    if (($existing['product_key'] ?? null) !== $key
        || ($existing['detected_upstream_version'] ?? null) !== $candidate['detected_upstream_version']
        || ($existing['review_state'] ?? null) !== 'MODEL_REVIEW_REQUIRED'
        || ($existing['automation_generated'] ?? false) !== true) {
        throw new RuntimeException($key . ': candidate cannot be removed from first-run fixture without discarding reviewed/unrelated evidence');
    }
    if (!unlink($firstPath)) {
        throw new RuntimeException('cannot prepare isolated automatic candidate absence: ' . $key);
    }
}

$firstBaseline = pgrRadarSmokeEvidenceHashes($firstRunRoot);
$profiles = PGR_Upstream_Radar::loadProfiles();
$first = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $firstRunRoot);
foreach ($seen as $key => $candidate) {
    $status = $first[$key] ?? null;
    $expected = $key === 'gravityperks' ? 'REVIEWED_CURRENT_SOURCE_ATTESTED' : 'CREATED';
    if ($status !== $expected) {
        throw new RuntimeException($key . ': expected first-run ' . $expected . ', got ' . $status);
    }
    fwrite(STDOUT, $key . ': ' . $status . " (isolated first run)\n");
}
$repeat = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $firstRunRoot);
foreach ($seen as $key => $candidate) {
    $expected = $key === 'gravityperks' ? 'REVIEWED_CURRENT_SOURCE_ATTESTED' : 'AUTOMATED_DEDUPLICATED';
    if (($repeat[$key] ?? null) !== $expected) {
        throw new RuntimeException($key . ': unexpected repeat-run persistence outcome');
    }
}
pgrRadarSmokeAssertOtherEvidenceUnchanged($firstBaseline, pgrRadarSmokeEvidenceHashes($firstRunRoot), $seen);

// A separate complete committed-state fixture must exercise existing records,
// not a tree where the candidates were removed and then recreated.
$committed = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $committedRoot);
foreach ($seen as $key => $candidate) {
    $status = $committed[$key] ?? null;
    if ($key === 'gravityperks') {
        if ($status !== 'REVIEWED_CURRENT_SOURCE_ATTESTED') {
            throw new RuntimeException('Gravity Perks changed source or lost current-source attestation');
        }
        continue;
    }
    if ($wasCommitted[$key]) {
        if (!in_array($status, array('AUTOMATED_DEDUPLICATED', 'AUTOMATED_UPDATED'), true)) {
            throw new RuntimeException($key . ': existing automated candidate was not safely deduplicated/updated: ' . $status);
        }
    } elseif ($status !== 'CREATED') {
        throw new RuntimeException($key . ': absent committed candidate was not created: ' . $status);
    }
    if ($status === 'AUTOMATED_UPDATED' || $status === 'CREATED') {
        $path = rtrim($committedRoot, '/') . '/tools/compatibility/upstream-observations/' . $key . '/' . $candidate['detected_upstream_version'] . '.json';
        $stored = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if ($stored !== $candidate) {
            throw new RuntimeException($key . ': automatic candidate update did not persist only the validated scan record');
        }
    }
    fwrite(STDOUT, $key . ': ' . $status . " (isolated committed state)\n");
}
$committedRepeat = PGR_Upstream_Radar::persistValidatedResults($summary, $profiles, $committedRoot);
foreach ($seen as $key => $candidate) {
    $expected = $key === 'gravityperks' ? 'REVIEWED_CURRENT_SOURCE_ATTESTED' : 'AUTOMATED_DEDUPLICATED';
    if (($committedRepeat[$key] ?? null) !== $expected) {
        throw new RuntimeException($key . ': committed-state repeat was not safely deduplicated');
    }
}
pgrRadarSmokeAssertOtherEvidenceUnchanged($pristine, pgrRadarSmokeEvidenceHashes($committedRoot), $seen);
fwrite(STDOUT, "Live persistence first/repeat/committed-state verified: all reviewed and unrelated evidence unchanged\n");
