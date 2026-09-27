#!/usr/bin/env php
<?php

declare(strict_types=1);

final class PGR_Upstream_Radar {
    private const PROFILE_SCHEMA_VERSION = 1;
    private const OBSERVATION_SCHEMA_VERSION = 1;
    private const RESULT_NO_NEW_VERSION = 'NO_NEW_VERSION';
    private const RESULT_NEW_VERSION = 'NEW_VERSION_DETECTED';
    private const CLASSIFICATIONS = [
        'NO_RELEVANT_DOCUMENTED_CHANGE',
        'DOCUMENTED_CONTRACT_CHANGE',
        'POTENTIAL_BETTER_SEAM_FOUND',
        'REQUALIFICATION_RECOMMENDED',
        'PACKAGE_REQUIRED_FOR_PROOF',
        'MODEL_REVIEW_REQUIRED',
    ];

    public static function repositoryRoot(): string {
        return dirname(__DIR__, 2);
    }

    public static function defaultProfilesPath(): string {
        return __DIR__ . '/upstream-radar-profiles.json';
    }

    public static function observationRoot(): string {
        return __DIR__ . '/upstream-observations';
    }

    public static function loadProfiles(?string $path = null): array {
        $path ??= self::defaultProfilesPath();
        $document = self::readJsonObject($path, 'profile document');

        if (($document['schema_version'] ?? null) !== self::PROFILE_SCHEMA_VERSION) {
            throw new RuntimeException('unsupported upstream radar profile schema_version');
        }
        if (!isset($document['products']) || !is_array($document['products']) || !array_is_list($document['products'])) {
            throw new RuntimeException('upstream radar profiles must contain a products list');
        }

        $profiles = [];
        foreach ($document['products'] as $profile) {
            if (!is_array($profile) || array_is_list($profile)) {
                throw new RuntimeException('each upstream radar profile must be an object');
            }
            self::validateProfile($profile);
            $key = $profile['key'];
            if (isset($profiles[$key])) {
                throw new RuntimeException("duplicate upstream radar profile: {$key}");
            }
            $profiles[$key] = $profile;
        }

        return $profiles;
    }

    public static function validateProfile(array $profile): void {
        foreach ([ 'key', 'name', 'baseline', 'release_source', 'capabilities', 'observation_namespace' ] as $required) {
            if (!array_key_exists($required, $profile)) {
                throw new RuntimeException("upstream radar profile missing {$required}");
            }
        }

        if (!is_string($profile['key']) || preg_match('/^[a-z][a-z0-9-]*$/', $profile['key']) !== 1) {
            throw new RuntimeException('upstream radar profile key is invalid');
        }
        if (!is_string($profile['name']) || trim($profile['name']) === '') {
            throw new RuntimeException("{$profile['key']}: profile name is invalid");
        }
        if (!is_array($profile['baseline']) || array_is_list($profile['baseline'])) {
            throw new RuntimeException("{$profile['key']}: baseline resolver must be an object");
        }
        if (array_key_exists('version', $profile['baseline'])) {
            throw new RuntimeException("{$profile['key']}: profile must not duplicate a baseline version literal");
        }
        foreach ([ 'type', 'path' ] as $required) {
            if (!isset($profile['baseline'][$required]) || !is_string($profile['baseline'][$required])) {
                throw new RuntimeException("{$profile['key']}: baseline resolver missing {$required}");
            }
        }
        if (!in_array($profile['baseline']['type'], [ 'admission_baseline', 'gravityflow_package', 'g007_admission' ], true)) {
            throw new RuntimeException("{$profile['key']}: unsupported baseline resolver");
        }
        self::validateRepositoryRelativePath($profile['baseline']['path'], "{$profile['key']}: baseline path");

        if (!is_array($profile['release_source']) || array_is_list($profile['release_source'])) {
            throw new RuntimeException("{$profile['key']}: release_source must be an object");
        }
        foreach ([ 'url', 'version_regex' ] as $required) {
            if (!isset($profile['release_source'][$required]) || !is_string($profile['release_source'][$required]) || $profile['release_source'][$required] === '') {
                throw new RuntimeException("{$profile['key']}: release_source missing {$required}");
            }
        }
        self::validateOfficialHttpsUrl($profile['release_source']['url'], "{$profile['key']}: release source");
        self::validateRegex($profile['release_source']['version_regex'], "{$profile['key']}: version regex");

        $docs = $profile['documentation_sources'] ?? [];
        if (!is_array($docs) || !array_is_list($docs)) {
            throw new RuntimeException("{$profile['key']}: documentation_sources must be a list");
        }
        foreach ($docs as $url) {
            if (!is_string($url)) {
                throw new RuntimeException("{$profile['key']}: documentation source must be a string");
            }
            self::validateOfficialHttpsUrl($url, "{$profile['key']}: documentation source");
        }

        if (!is_array($profile['capabilities']) || !array_is_list($profile['capabilities']) || $profile['capabilities'] === []) {
            throw new RuntimeException("{$profile['key']}: capabilities must be a non-empty list");
        }
        foreach ($profile['capabilities'] as $capability) {
            if (!is_string($capability) || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $capability) !== 1) {
                throw new RuntimeException("{$profile['key']}: invalid capability identifier");
            }
        }
        if ($profile['observation_namespace'] !== $profile['key']) {
            throw new RuntimeException("{$profile['key']}: observation namespace must equal product key");
        }
    }

    public static function resolveBaseline(array $profile, ?string $root = null): array {
        $root ??= self::repositoryRoot();
        $resolver = $profile['baseline'];
        $path = self::joinRepositoryPath($root, $resolver['path']);

        if ($resolver['type'] === 'gravityflow_package') {
            require_once __DIR__ . '/gravityflow-package.php';
            if (!class_exists('GravityFlowPackageAuthority')) {
                throw new RuntimeException('Gravity Flow package authority helper is unavailable');
            }
            $package = GravityFlowPackageAuthority::load($path);
            return [
                'version' => $package['version'],
                'authority_kind' => $package['authority'],
                'authority_path' => $resolver['path'],
                'authority_product' => $package['product'],
            ];
        }

        $document = self::readJsonObject($path, "{$profile['key']} baseline authority");
        $productKey = $resolver['product'] ?? $profile['key'];
        if (!is_string($productKey) || $productKey === '') {
            throw new RuntimeException("{$profile['key']}: baseline product selector is invalid");
        }
        $products = $document['products'] ?? null;
        if (!is_array($products) || !array_is_list($products)) {
            throw new RuntimeException("{$profile['key']}: baseline authority has no products list");
        }
        foreach ($products as $product) {
            if (!is_array($product) || ($product['product'] ?? null) !== $productKey) {
                continue;
            }
            $version = $product['target_version'] ?? null;
            self::assertStableVersion($version, "{$profile['key']} baseline version");
            return [
                'version' => $version,
                'authority_kind' => (string) ($product['project_source_authority'] ?? strtoupper($resolver['type'])),
                'authority_path' => $resolver['path'],
                'authority_product' => $productKey,
            ];
        }

        throw new RuntimeException("{$profile['key']}: product not found in baseline authority");
    }

    public static function compareVersions(string $latest, string $baseline): string {
        self::assertStableVersion($latest, 'upstream version');
        self::assertStableVersion($baseline, 'baseline version');
        $comparison = version_compare($latest, $baseline);
        if ($comparison === 0) {
            return self::RESULT_NO_NEW_VERSION;
        }
        if ($comparison > 0) {
            return self::RESULT_NEW_VERSION;
        }
        throw new RuntimeException("official upstream version {$latest} is older than project baseline {$baseline}; source is stale or ambiguous");
    }

    public static function parseLatestVersion(array $profile, string $body): array {
        if (strlen($body) < 20) {
            throw new RuntimeException("{$profile['key']}: official source response is unexpectedly short");
        }
        $pattern = $profile['release_source']['version_regex'];
        $matched = preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE);
        if ($matched !== 1 || !isset($matches[1][0], $matches[0][1])) {
            throw new RuntimeException("{$profile['key']}: official source structure did not match the governed version parser");
        }
        $version = $matches[1][0];
        self::assertStableVersion($version, "{$profile['key']} detected upstream version");

        $start = (int) $matches[0][1];
        $nextOffset = null;
        $nextMatched = preg_match($pattern, $body, $nextMatches, PREG_OFFSET_CAPTURE, $start + strlen($matches[0][0]));
        if ($nextMatched === 1 && isset($nextMatches[0][1])) {
            $nextOffset = (int) $nextMatches[0][1];
        }
        $maxLength = 20000;
        $length = $nextOffset === null ? $maxLength : min($maxLength, max(0, $nextOffset - $start));
        $releaseBlock = substr($body, $start, $length);
        if ($releaseBlock === false || $releaseBlock === '') {
            throw new RuntimeException("{$profile['key']}: could not isolate release evidence block");
        }

        return [ 'version' => $version, 'release_block' => $releaseBlock ];
    }

    public static function extractDocumentedIdentifiers(string $text): array {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pattern = '/\b(?:gform_[a-z0-9_\/-]+|gravityflow_[a-z0-9_\/-]+|gravityview\/[a-z0-9_\/-]+|gk\/gravityview\/[a-z0-9_\/-]+|gp[a-z0-9_]*_[a-z0-9_]+)\b/i';
        preg_match_all($pattern, $plain, $matches);
        $identifiers = array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
        sort($identifiers, SORT_STRING);
        return array_slice($identifiers, 0, 40);
    }

    public static function validateObservation(array $observation): void {
        foreach ([
            'schema_version', 'product_key', 'product_name', 'detected_upstream_version', 'project_baseline',
            'evidence_state', 'package_state', 'checked_at', 'official_sources', 'classifications',
            'capability_observations', 'model_review_required', 'package_required_for_proof', 'review_state',
        ] as $required) {
            if (!array_key_exists($required, $observation)) {
                throw new RuntimeException("observation missing {$required}");
            }
        }
        if ($observation['schema_version'] !== self::OBSERVATION_SCHEMA_VERSION) {
            throw new RuntimeException('unsupported observation schema_version');
        }
        if (!is_string($observation['product_key']) || preg_match('/^[a-z][a-z0-9-]*$/', $observation['product_key']) !== 1) {
            throw new RuntimeException('observation product_key is invalid');
        }
        self::assertStableVersion($observation['detected_upstream_version'], 'observation detected version');
        if (!is_array($observation['project_baseline']) || array_is_list($observation['project_baseline'])) {
            throw new RuntimeException('observation project_baseline must be an object');
        }
        foreach ([ 'version', 'authority_kind', 'authority_path' ] as $required) {
            if (!isset($observation['project_baseline'][$required]) || !is_string($observation['project_baseline'][$required])) {
                throw new RuntimeException("observation project_baseline missing {$required}");
            }
        }
        self::assertStableVersion($observation['project_baseline']['version'], 'observation baseline version');
        self::validateRepositoryRelativePath($observation['project_baseline']['authority_path'], 'observation baseline authority_path');
        if ($observation['evidence_state'] !== 'DETECTED_ONLY') {
            throw new RuntimeException('docs-only observation evidence_state must be DETECTED_ONLY');
        }
        if (!in_array($observation['package_state'], [ 'NOT_SUPPLIED_FOR_DETECTED_VERSION', 'SUPPLIED_NOT_TESTED', 'TESTED_NOT_QUALIFIED' ], true)) {
            throw new RuntimeException('observation package_state is invalid');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $observation['checked_at']) !== 1) {
            throw new RuntimeException('observation checked_at must be canonical UTC');
        }
        if (!is_array($observation['official_sources']) || !array_is_list($observation['official_sources']) || $observation['official_sources'] === []) {
            throw new RuntimeException('observation official_sources must be a non-empty list');
        }
        foreach ($observation['official_sources'] as $source) {
            if (!is_array($source) || array_is_list($source) || !isset($source['url'], $source['role'])) {
                throw new RuntimeException('observation official source is invalid');
            }
            self::validateOfficialHttpsUrl((string) $source['url'], 'observation official source');
            if (isset($source['sha256']) && (preg_match('/^[a-f0-9]{64}$/', (string) $source['sha256']) !== 1)) {
                throw new RuntimeException('observation official source sha256 is invalid');
            }
        }
        if (!is_array($observation['classifications']) || !array_is_list($observation['classifications'])) {
            throw new RuntimeException('observation classifications must be a list');
        }
        if (count($observation['classifications']) !== count(array_unique($observation['classifications']))) {
            throw new RuntimeException('observation classifications must be unique');
        }
        foreach ($observation['classifications'] as $classification) {
            if (!in_array($classification, self::CLASSIFICATIONS, true)) {
                throw new RuntimeException("unsupported observation classification: {$classification}");
            }
        }
        if (
            in_array('NO_RELEVANT_DOCUMENTED_CHANGE', $observation['classifications'], true) &&
            in_array('DOCUMENTED_CONTRACT_CHANGE', $observation['classifications'], true)
        ) {
            throw new RuntimeException('observation cannot claim both no relevant documented change and documented contract change');
        }
        if (!in_array('PACKAGE_REQUIRED_FOR_PROOF', $observation['classifications'], true) || $observation['package_required_for_proof'] !== true) {
            throw new RuntimeException('observation must preserve PACKAGE_REQUIRED_FOR_PROOF boundary');
        }
        if (!is_bool($observation['model_review_required'])) {
            throw new RuntimeException('observation model_review_required must be boolean');
        }
        if (!in_array($observation['review_state'], [ 'MODEL_REVIEW_REQUIRED', 'MODEL_REVIEWED' ], true)) {
            throw new RuntimeException('observation review_state is invalid');
        }
        if ($observation['review_state'] === 'MODEL_REVIEW_REQUIRED' && $observation['model_review_required'] !== true) {
            throw new RuntimeException('unreviewed observation must remain MODEL_REVIEW_REQUIRED');
        }
        if ($observation['review_state'] === 'MODEL_REVIEWED' && $observation['model_review_required'] !== false) {
            throw new RuntimeException('reviewed observation must clear model_review_required');
        }
        if (!is_array($observation['capability_observations']) || !array_is_list($observation['capability_observations'])) {
            throw new RuntimeException('observation capability_observations must be a list');
        }
        foreach ($observation['capability_observations'] as $item) {
            if (!is_array($item) || array_is_list($item) || !isset($item['capability'], $item['classification'], $item['summary'])) {
                throw new RuntimeException('capability observation is invalid');
            }
            if (!in_array($item['classification'], self::CLASSIFICATIONS, true)) {
                throw new RuntimeException('capability observation classification is invalid');
            }
            if ($item['classification'] === 'POTENTIAL_BETTER_SEAM_FOUND') {
                $evidence = $item['supporting_official_evidence'] ?? null;
                if (!is_array($evidence) || !array_is_list($evidence) || $evidence === []) {
                    throw new RuntimeException('POTENTIAL_BETTER_SEAM_FOUND requires explicit supporting_official_evidence');
                }
                foreach ($evidence as $reference) {
                    if (!is_string($reference) || trim($reference) === '') {
                        throw new RuntimeException('better-seam supporting evidence must be a non-empty official identifier or reference');
                    }
                }
            }
        }
        if (in_array('POTENTIAL_BETTER_SEAM_FOUND', $observation['classifications'], true)) {
            $hasSupportedLead = false;
            foreach ($observation['capability_observations'] as $item) {
                if (($item['classification'] ?? null) === 'POTENTIAL_BETTER_SEAM_FOUND' && !empty($item['supporting_official_evidence'])) {
                    $hasSupportedLead = true;
                    break;
                }
            }
            if (!$hasSupportedLead) {
                throw new RuntimeException('top-level POTENTIAL_BETTER_SEAM_FOUND requires a supported capability observation');
            }
        }
    }

    public static function scanProfile(array $profile, callable $fetcher, ?string $root = null, ?string $checkedAt = null): array {
        $root ??= self::repositoryRoot();
        $checkedAt ??= gmdate('Y-m-d\TH:i:s\Z');
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $checkedAt) !== 1) {
            throw new RuntimeException('checked-at must be canonical UTC');
        }
        $baseline = self::resolveBaseline($profile, $root);
        $cache = [];
        $get = static function (string $url) use ($fetcher, &$cache): string {
            if (!array_key_exists($url, $cache)) {
                $body = $fetcher($url);
                if (!is_string($body)) {
                    throw new RuntimeException("fetcher returned non-string response for {$url}");
                }
                $cache[$url] = $body;
            }
            return $cache[$url];
        };

        $releaseUrl = $profile['release_source']['url'];
        $releaseBody = $get($releaseUrl);
        $parsed = self::parseLatestVersion($profile, $releaseBody);
        $result = self::compareVersions($parsed['version'], $baseline['version']);

        $sourceRecords = [[
            'url' => $releaseUrl,
            'role' => 'stable-release-and-changelog',
            'sha256' => hash('sha256', $releaseBody),
        ]];
        $signalText = $parsed['release_block'];
        foreach ($profile['documentation_sources'] ?? [] as $docsUrl) {
            if ($docsUrl === $releaseUrl) {
                continue;
            }
            $docsBody = $get($docsUrl);
            $sourceRecords[] = [
                'url' => $docsUrl,
                'role' => 'official-documentation',
                'sha256' => hash('sha256', $docsBody),
            ];
            $signalText .= "\n" . substr($docsBody, 0, 30000);
        }

        $identifiers = self::extractDocumentedIdentifiers($signalText);
        $observation = null;
        if ($result === self::RESULT_NEW_VERSION) {
            $observation = [
                'schema_version' => self::OBSERVATION_SCHEMA_VERSION,
                'product_key' => $profile['key'],
                'product_name' => $profile['name'],
                'detected_upstream_version' => $parsed['version'],
                'project_baseline' => $baseline,
                'evidence_state' => 'DETECTED_ONLY',
                'package_state' => 'NOT_SUPPLIED_FOR_DETECTED_VERSION',
                'checked_at' => $checkedAt,
                'official_sources' => $sourceRecords,
                'classifications' => [ 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' ],
                'capability_observations' => [[
                    'capability' => 'profile.' . $profile['key'],
                    'classification' => 'MODEL_REVIEW_REQUIRED',
                    'summary' => 'Deterministic automation detected a newer stable release and bounded official identifiers. Semantic impact requires model/Owner review before any compatibility conclusion.',
                    'documented_identifiers' => $identifiers,
                ]],
                'model_review_required' => true,
                'package_required_for_proof' => true,
                'review_state' => 'MODEL_REVIEW_REQUIRED',
                'automation_generated' => true,
            ];
            self::validateObservation($observation);
        }

        return [
            'product_key' => $profile['key'],
            'product_name' => $profile['name'],
            'baseline' => $baseline,
            'latest_upstream_version' => $parsed['version'],
            'result' => $result,
            'official_sources' => $sourceRecords,
            'documented_identifiers' => $identifiers,
            'observation_candidate' => $observation,
        ];
    }

    public static function persistCandidate(array $candidate, ?string $root = null): string {
        self::validateObservation($candidate);
        $root ??= self::repositoryRoot();
        $observationRoot = self::joinRepositoryPath($root, 'tools/compatibility/upstream-observations');
        $directory = $observationRoot . '/' . $candidate['product_key'];
        $path = $directory . '/' . $candidate['detected_upstream_version'] . '.json';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("cannot create observation directory: {$directory}");
        }

        if (!file_exists($path)) {
            self::writeJson($path, $candidate);
            return 'CREATED';
        }

        $existing = self::readJsonObject($path, 'existing observation');
        self::validateObservation($existing);
        if (($existing['review_state'] ?? null) === 'MODEL_REVIEWED' || empty($existing['automation_generated'])) {
            $existingFingerprints = array_filter(self::sourceFingerprints($existing['official_sources']));
            if ($existingFingerprints === []) {
                return 'REVIEWED_DEDUPLICATED';
            }
            $candidateFingerprints = self::sourceFingerprints($candidate['official_sources']);
            foreach ($existingFingerprints as $url => $fingerprint) {
                if (($candidateFingerprints[$url] ?? null) !== $fingerprint) {
                    return 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED';
                }
            }
            return 'REVIEWED_DEDUPLICATED';
        }

        $existingFingerprints = self::sourceFingerprints($existing['official_sources']);
        $candidateFingerprints = self::sourceFingerprints($candidate['official_sources']);
        if ($existingFingerprints === $candidateFingerprints && ($existing['capability_observations'][0]['documented_identifiers'] ?? []) === ($candidate['capability_observations'][0]['documented_identifiers'] ?? [])) {
            return 'AUTOMATED_DEDUPLICATED';
        }
        self::writeJson($path, $candidate);
        return 'AUTOMATED_UPDATED';
    }

    public static function validateObservationDirectory(?string $root = null): int {
        $root ??= self::repositoryRoot();
        $observationRoot = self::joinRepositoryPath($root, 'tools/compatibility/upstream-observations');
        if (!is_dir($observationRoot)) {
            return 0;
        }
        $count = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($observationRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }
            $observation = self::readJsonObject($file->getPathname(), 'observation');
            self::validateObservation($observation);
            ++$count;
        }
        return $count;
    }

    public static function httpFetcher(string $url): string {
        self::validateOfficialHttpsUrl($url, 'fetch URL');
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => false,
                'user_agent' => 'Mozilla/5.0 (compatible; PersianGravity-Upstream-Radar/1.0; +https://github.com/rezahh107/PersianGravity)',
                'header' => "Accept: text/html,application/rss+xml,application/xml;q=0.9,*/*;q=0.1\r\nAccept-Encoding: identity\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $error = error_get_last();
            throw new RuntimeException('official source retrieval failed for ' . $url . ($error ? ': ' . $error['message'] : ''));
        }
        if (strlen($body) < 20) {
            throw new RuntimeException("official source returned an unexpectedly short response: {$url}");
        }
        return $body;
    }

    public static function renderSummary(array $results): string {
        $lines = [ '# Upstream compatibility opportunity radar', '', '| Product | Baseline | Latest official stable | Result | Proof boundary |', '| --- | --- | --- | --- | --- |' ];
        foreach ($results as $result) {
            $lines[] = sprintf(
                '| %s | `%s` | `%s` | `%s` | `PACKAGE_REQUIRED_FOR_PROOF` |',
                $result['product_name'],
                $result['baseline']['version'],
                $result['latest_upstream_version'],
                $result['result']
            );
        }
        $lines[] = '';
        $lines[] = 'Documentation/release detection is informational only. DETECTED / DOCUMENTED != OWNER_SUPPLIED != TESTED != QUALIFIED != ADMITTED.';
        return implode("\n", $lines) . "\n";
    }

    private static function validateOfficialHttpsUrl(string $url, string $label): void {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new RuntimeException("{$label} must be an HTTPS URL");
        }
        $host = strtolower((string) $parts['host']);
        $allowed = [ 'docs.gravityforms.com', 'docs.gravityflow.io', 'www.gravitykit.com', 'gravitykit.com', 'gravitywiz.com', 'www.gravitywiz.com' ];
        if (!in_array($host, $allowed, true)) {
            throw new RuntimeException("{$label} host is not an approved first-party authority: {$host}");
        }
    }

    private static function validateRegex(string $pattern, string $label): void {
        set_error_handler(static function (): bool { return true; });
        try {
            $result = preg_match($pattern, '3.1.2');
        } finally {
            restore_error_handler();
        }
        if ($result === false) {
            throw new RuntimeException("{$label} is not a valid regular expression");
        }
    }

    private static function assertStableVersion(mixed $version, string $label): void {
        if (!is_string($version) || preg_match('/^(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*)){2,3}$/', $version) !== 1) {
            throw new RuntimeException("{$label} is not a supported stable numeric version");
        }
    }

    private static function validateRepositoryRelativePath(string $path, string $label): void {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('~(?:^|/)\.\.(?:/|$)~', $path) === 1) {
            throw new RuntimeException("{$label} is not a safe repository-relative path");
        }
    }

    private static function joinRepositoryPath(string $root, string $relative): string {
        self::validateRepositoryRelativePath($relative, 'repository path');
        return rtrim($root, '/') . '/' . $relative;
    }

    private static function readJsonObject(string $path, string $label): array {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("cannot read {$label}: {$path}");
        }
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("{$label} is not valid JSON: " . $exception->getMessage(), 0, $exception);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException("{$label} root must be a JSON object");
        }
        return $data;
    }

    private static function writeJson(string $path, array $data): void {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($path, $encoded) === false) {
            throw new RuntimeException("cannot write observation: {$path}");
        }
    }

    private static function sourceFingerprints(array $sources): array {
        $fingerprints = [];
        foreach ($sources as $source) {
            $fingerprints[(string) ($source['url'] ?? '')] = (string) ($source['sha256'] ?? '');
        }
        ksort($fingerprints, SORT_STRING);
        return $fingerprints;
    }
}

function pgrUpstreamRadarMain(array $argv): int {
    $command = $argv[1] ?? 'validate';
    $options = [];
    foreach (array_slice($argv, 2) as $argument) {
        if (!str_starts_with($argument, '--')) {
            fwrite(STDERR, "upstream-radar: unsupported positional argument: {$argument}\n");
            return 1;
        }
        $pair = explode('=', substr($argument, 2), 2);
        $options[$pair[0]] = $pair[1] ?? true;
    }

    try {
        $profiles = PGR_Upstream_Radar::loadProfiles();
        if ($command === 'validate') {
            $count = PGR_Upstream_Radar::validateObservationDirectory();
            fwrite(STDOUT, 'Upstream radar profiles OK: ' . count($profiles) . "; observations OK: {$count}\n");
            return 0;
        }
        if ($command !== 'scan') {
            throw new RuntimeException("unsupported command: {$command}");
        }

        $output = $options['output'] ?? sys_get_temp_dir() . '/pgr-upstream-radar';
        if (!is_string($output) || $output === '') {
            throw new RuntimeException('output path is invalid');
        }
        if (!is_dir($output) && !mkdir($output, 0777, true) && !is_dir($output)) {
            throw new RuntimeException("cannot create output directory: {$output}");
        }
        $checkedAt = isset($options['checked-at']) && is_string($options['checked-at']) ? $options['checked-at'] : null;
        $persist = isset($options['persist-new']);

        $results = [];
        $failures = [];
        foreach ($profiles as $key => $profile) {
            try {
                $result = PGR_Upstream_Radar::scanProfile($profile, [ PGR_Upstream_Radar::class, 'httpFetcher' ], null, $checkedAt);
                if ($result['observation_candidate'] !== null) {
                    $candidateDir = rtrim($output, '/') . '/candidates/' . $key;
                    if (!is_dir($candidateDir) && !mkdir($candidateDir, 0777, true) && !is_dir($candidateDir)) {
                        throw new RuntimeException("cannot create candidate directory: {$candidateDir}");
                    }
                    $candidatePath = $candidateDir . '/' . $result['latest_upstream_version'] . '.json';
                    file_put_contents($candidatePath, json_encode($result['observation_candidate'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
                    if ($persist) {
                        $result['persistence'] = PGR_Upstream_Radar::persistCandidate($result['observation_candidate']);
                    }
                }
                $results[] = $result;
            } catch (Throwable $exception) {
                $failures[$key] = $exception->getMessage();
            }
        }

        $summary = [
            'schema_version' => 1,
            'checked_at' => $checkedAt ?? gmdate('Y-m-d\TH:i:s\Z'),
            'results' => $results,
            'failures' => $failures,
            'evidence_ceiling' => 'DETECTED / DOCUMENTED != OWNER_SUPPLIED != TESTED != QUALIFIED != ADMITTED',
        ];
        file_put_contents(rtrim($output, '/') . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        file_put_contents(rtrim($output, '/') . '/summary.md', PGR_Upstream_Radar::renderSummary($results));
        fwrite(STDOUT, PGR_Upstream_Radar::renderSummary($results));

        if ($failures !== []) {
            foreach ($failures as $key => $message) {
                fwrite(STDERR, "upstream-radar[{$key}]: {$message}\n");
            }
            return 1;
        }
        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'upstream-radar: ' . $exception->getMessage() . "\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(pgrUpstreamRadarMain($argv));
}
