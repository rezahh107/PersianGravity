<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/tools/i18n/catalog.php';

final class CatalogBuildTest extends TestCase {
    public function test_compilation_is_reproducible_preserves_po_and_excludes_unreviewed_entries() {
        $source = __DIR__ . '/i18n/fixtures/synthetic.po';
        $before = hash_file('sha256', $source);
        $one = pgr_compile_catalog($source, 'gravityforms');
        $two = pgr_compile_catalog($source, 'gravityforms');
        $this->assertSame($before, hash_file('sha256', $source));
        foreach (['mo', 'php', 'json'] as $format) { $this->assertSame($one[$format], $two[$format]); }
        $this->assertSame(['translated' => 3, 'untranslated' => 1, 'fuzzy' => 1], $one['counts']);
        $jed = json_decode($one['json'], true)['locale_data']['gravityforms'];
        $this->assertArrayNotHasKey('Synthetic unreviewed', $jed);
        $this->assertArrayNotHasKey('Synthetic missing', $jed);
        $this->assertSame(['یکی', 'چندتا'], $jed['Synthetic one']);
        $this->assertSame(['زمینه'], $jed["test-context\x04Synthetic context"]);
        $this->assertStringNotContainsString('Synthetic unreviewed', $one['mo']);
        $this->assertStringNotContainsString('Synthetic missing', $one['php']);
    }

    public function test_unverified_production_scaffolds_cannot_claim_coverage_or_handles() {
        $products = require dirname(__DIR__) . '/includes/localization/products.php';
        foreach ($products as $domain => $product) {
            $path = dirname(__DIR__) . '/languages/providers/' . $product['product'];
            $meta = json_decode(file_get_contents($path . '/metadata.json'), true);
            $this->assertSame('PACKAGE_UNAVAILABLE', $meta['provenance']['source_status']);
            $this->assertNull($meta['authoritative_total']);
            $this->assertNull($meta['coverage_percent']);
            $this->assertSame([], $product['scripts']);
            $this->assertFileDoesNotExist($path . '/' . $product['prefix'] . '-fa_IR.mo');
            $this->assertFileDoesNotExist($path . '/' . $product['prefix'] . '-fa_IR.l10n.php');
        }
    }
}
