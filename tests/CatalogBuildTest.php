<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/catalog.php';
require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

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

	public function test_provider_content_states_match_the_validated_aggregate_boundary() {
		$root              = dirname( __DIR__ );
		$products          = require $root . '/includes/localization/products.php';
		$source_admission  = pgr_validate_admission( $root );
		$content_admission = pgr_validate_content_admission( $root, $source_admission, $products );
		$source_statuses   = array(
			'gravityforms'   => 'PACKAGE_INSPECTED_METADATA_ONLY',
			'gravityflow'    => 'PACKAGE_INSPECTED_METADATA_ONLY',
			'gk-gravityview' => 'PACKAGE_UNAVAILABLE',
		);

		foreach ( $products as $domain => $product ) {
			$path      = $root . '/languages/providers/' . $product['product'];
			$meta      = json_decode( file_get_contents( $path . '/metadata.json' ), true );
			$admission = $content_admission[ $product['product'] ] ?? null;
			$mo        = $path . '/' . $product['prefix'] . '-fa_IR.mo';
			$php       = $path . '/' . $product['prefix'] . '-fa_IR.l10n.php';

			$this->assertSame( $source_statuses[ $domain ], $meta['provenance']['source_status'] );
			$this->assertSame( array(), $product['scripts'] );

			if ( null === $admission ) {
				$this->assertSame( 0, $meta['counts_in_committed_po']['translated'] );
				$this->assertArrayNotHasKey( 'content_admission', $meta );
				$this->assertFileDoesNotExist( $mo );
				$this->assertFileDoesNotExist( $php );
				$this->assertSame( array(), glob( $path . '/' . $domain . '-fa_IR-*.json' ) ?: array() );
				continue;
			}

			$aggregate = $admission['aggregate'];
			$this->assertSame( 'CONTENT_ADMITTED_PARTIAL', $admission['content_state'] );
			$this->assertSame( $aggregate['admitted_message_count'], $meta['counts_in_committed_po']['translated'] );
			$this->assertSame( $aggregate['provider_source_sha256'], $meta['provider_po_sha256'] );
			$this->assertSame( 2, $meta['content_admission']['revision'] );
			$this->assertSame( array_map( 'pgr_content_provenance_record', $admission['admissions'] ), $meta['content_admission']['admissions'] );
			$this->assertSame( $aggregate, $meta['content_admission']['aggregate'] );
			$this->assertSame( 0, $aggregate['native_js_handles_activated'] );
			$this->assertSame( 0, $aggregate['js_translation_json_generated'] );
			$this->assertFileExists( $mo );
			$this->assertFileExists( $php );
			$this->assertSame( array(), glob( $path . '/' . $domain . '-fa_IR-*.json' ) ?: array() );
		}
	}
}
