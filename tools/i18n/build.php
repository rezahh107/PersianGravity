<?php
/** Deterministic build/check. Canonical PO is only read, never purged or rewritten. */
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/catalog.php';
define('ABSPATH', dirname(__DIR__, 2) . '/');
$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--write', '--check'], true)) { throw new RuntimeException('Use --check or --write'); }
$products = require ABSPATH . 'includes/localization/products.php';
$root = ABSPATH . 'languages/providers';
$expected = [];
foreach ($products as $domain => $product) {
    $dir = $root . '/' . $product['product'];
    $source = $dir . '/source';
    $provenance = json_decode(file_get_contents($source . '/provenance.json'), true, 512, JSON_THROW_ON_ERROR);
    $built = pgr_compile_catalog($source . '/fa_IR.po', $domain);
    $counts = $built['counts'];
    $known = array_sum($counts);
    $verified = $provenance['source_status'] === 'VERIFIED';
    if ($product['target_version'] !== $provenance['target_product_version']) { throw new RuntimeException('Target version drift'); }
    if (!$verified && ($known !== 0 || $product['scripts'] !== [])) {
        throw new RuntimeException('Unverified source must not ship invented strings or handles: ' . $domain);
    }
    if ($verified) {
        if (!is_file($source . '/source.pot') || hash_file('sha256', $source . '/source.pot') !== $provenance['source_pot_sha256'] ||
            $provenance['source_product_version'] !== $product['target_version'] || !preg_match('/^[a-f0-9]{64}$/D', $provenance['source_package_sha256'] ?? '')) {
            throw new RuntimeException('Missing source POT/package provenance: ' . $domain);
        }
        $pot = (new Gettext\Loader\StrictPoLoader())->loadFile($source . '/source.pot');
        $po = (new Gettext\Loader\StrictPoLoader())->loadFile($source . '/fa_IR.po');
        $keys = static function ($entries) {
            $result = [];
            foreach ($entries as $entry) {
                if (!$entry->isDisabled()) { $result[] = [$entry->getContext(), $entry->getOriginal(), $entry->getPlural()]; }
            }
            sort($result);
            return $result;
        };
        if ($keys($pot) !== $keys($po)) { throw new RuntimeException('PO source census does not match authoritative POT: ' . $domain); }
    }
    $artifacts = [];
    // Header-only scaffolds must remain dormant: no registry/loader interception.
    if ($counts['translated'] > 0) {
        $base = $product['prefix'] . '-fa_IR';
        $artifacts[$base . '.mo'] = $built['mo'];
        $artifacts[$base . '.l10n.php'] = $built['php'];
    }
    foreach ($product['scripts'] as $handle => $record) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $handle) || empty($record['references']) || empty($record['evidence']) ||
            ($record['source_version'] ?? '') !== $product['target_version']) {
            throw new RuntimeException('Unverified script record: ' . $handle);
        }
        // Match source references explicitly; never broadcast a whole product PO to every handle.
        $subset = clone $built['catalog'];
        foreach ($subset as $entry) {
            $paths = array_keys(iterator_to_array($entry->getReferences()));
            if (!array_intersect($paths, $record['references'])) { $entry->disable(); }
        }
        $temp_po = tempnam(sys_get_temp_dir(), 'pgr-po-');
        try {
            (new Gettext\Generator\PoGenerator())->generateFile($subset, $temp_po);
            $script = pgr_compile_catalog($temp_po, $domain);
        } finally { unlink($temp_po); }
        $artifacts[$domain . '-fa_IR-' . $handle . '.json'] = $script['json'];
    }
    $hashes = [];
    foreach ($artifacts as $name => $bytes) { $hashes[$name] = hash('sha256', $bytes); }
    $metadata = [
        'product' => $product['product'], 'domain' => $domain, 'locale' => 'fa_IR',
        'provenance' => $provenance, 'provider_po_sha256' => hash_file('sha256', $source . '/fa_IR.po'),
        'counts_in_committed_po' => $counts, 'authoritative_total' => $verified ? $known : null,
        'coverage_percent' => $verified && $known > 0 ? round(100 * $counts['translated'] / $known, 2) : null,
        'content_status' => $verified ? 'PARTIAL_TRANSLATION_CONTENT' : 'SOURCE_UNAVAILABLE_EMPTY_SCAFFOLD',
        'validated_script_handles' => array_keys($product['scripts']), 'artifact_sha256' => (object) $hashes,
        'vendor_surface_drift_check' => 'NOT_EXECUTED_PACKAGE_UNAVAILABLE',
        'generator' => 'gettext/gettext 5.7.3 + tools/i18n/catalog.php',
    ];
    $artifacts['metadata.json'] = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    foreach ($artifacts as $name => $bytes) { $expected[$dir . '/' . $name] = $bytes; }
}
$actual = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && !str_contains($file->getPathname(), '/source/') && $file->getFilename() !== 'README.md') { $actual[] = $file->getPathname(); }
}
foreach (array_diff($actual, array_keys($expected)) as $extra) { throw new RuntimeException('Unapproved/orphan artifact: ' . $extra); }
foreach ($expected as $path => $bytes) {
    if ($mode === '--write') { file_put_contents($path, $bytes); continue; }
    $temp = tempnam(sys_get_temp_dir(), 'pgr-artifact-');
    try {
        file_put_contents($temp, $bytes);
        if (!is_file($path) || hash_file('sha256', $temp) !== hash_file('sha256', $path)) { throw new RuntimeException('Artifact drift: ' . $path); }
    } finally { unlink($temp); }
}
echo 'Catalog ' . $mode . ': PASS; production source/handles remain explicitly unavailable.' . PHP_EOL;
