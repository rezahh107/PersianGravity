<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/catalog.php';

final class CatalogBuildTest extends TestCase {
	public function test_compilation_is_reproducible_preserves_po_and_excludes_unreviewed_entries() {
		$source = __DIR__ . '/i18n/fixtures/synthetic.po';
		$before = hash_file( 'sha256', $source );
		$one    = pgr_compile_catalog( $source, 'gravityforms' );
		$two    = pgr_compile_catalog( $source, 'gravityforms' );
		$this->assertSame( $before, hash_file( 'sha256', $source ) );
		foreach ( array( 'mo', 'php', 'json' ) as $format ) {
			$this->assertSame( $one[ $format ], $two[ $format ] );
		}
		$this->assertSame( array( 'translated' => 3, 'untranslated' => 1, 'fuzzy' => 1 ), $one['counts'] );
		$jed = json_decode( $one['json'], true )['locale_data']['gravityforms'];
		$this->assertArrayNotHasKey( 'Synthetic unreviewed', $jed );
		$this->assertArrayNotHasKey( 'Synthetic missing', $jed );
		$this->assertSame( array( 'یکی', 'چندتا' ), $jed['Synthetic one'] );
		$this->assertSame( array( 'زمینه' ), $jed[ "test-context\x04Synthetic context" ] );
		$this->assertStringNotContainsString( 'Synthetic unreviewed', $one['mo'] );
		$this->assertStringNotContainsString( 'Synthetic missing', $one['php'] );
	}

	public function test_provider_content_states_match_the_admission_boundary() {
		$products = require dirname( __DIR__ ) . '/includes/localization/products.php';
		$expected = array(
			'gravityforms'   => array( 'source_status' => 'PACKAGE_INSPECTED_METADATA_ONLY', 'translated' => 0, 'runtime' => false ),
			'gravityflow'    => array( 'source_status' => 'PACKAGE_INSPECTED_METADATA_ONLY', 'translated' => 255, 'runtime' => true ),
			'gk-gravityview' => array( 'source_status' => 'PACKAGE_UNAVAILABLE', 'translated' => 0, 'runtime' => false ),
		);
		foreach ( $products as $domain => $product ) {
			$path = dirname( __DIR__ ) . '/languages/providers/' . $product['product'];
			$meta = json_decode( file_get_contents( $path . '/metadata.json' ), true );
			$this->assertSame( $expected[ $domain ]['source_status'], $meta['provenance']['source_status'] );
			$this->assertSame( $expected[ $domain ]['translated'], $meta['counts_in_committed_po']['translated'] );
			$this->assertSame( array(), $product['scripts'] );
			$mo  = $path . '/' . $product['prefix'] . '-fa_IR.mo';
			$php = $path . '/' . $product['prefix'] . '-fa_IR.l10n.php';
			if ( $expected[ $domain ]['runtime'] ) {
				$this->assertFileExists( $mo );
				$this->assertFileExists( $php );
			} else {
				$this->assertFileDoesNotExist( $mo );
				$this->assertFileDoesNotExist( $php );
			}
		}
	}
}
