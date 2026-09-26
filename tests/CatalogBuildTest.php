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
			'gk-gravityview' => 'PACKAGE_INSPECTED_METADATA_ONLY',
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

			$aggregate         = $admission['aggregate'];
			$is_full           = in_array( $product['product'], array( 'gravityforms', 'gravityflow', 'gravityview' ), true );
			$expected_state    = $is_full ? 'CONTENT_ADMITTED_FULL' : 'CONTENT_ADMITTED_PARTIAL';
			$expected_revision = $is_full ? 3 : 2;
			$this->assertSame( $expected_state, $admission['content_state'] );
			$runtime_count = $aggregate['runtime_provider_message_count'] ?? $aggregate['admitted_message_count'];
			$this->assertSame( $runtime_count, $meta['counts_in_committed_po']['translated'] );
			$this->assertSame( $aggregate['provider_source_sha256'], $meta['provider_po_sha256'] );
			$this->assertSame( $expected_revision, $meta['content_admission']['revision'] );
			$this->assertSame( $expected_state, $meta['content_admission']['state'] );
			$this->assertSame( array_map( 'pgr_content_provenance_record', $admission['admissions'] ), $meta['content_admission']['admissions'] );
			$this->assertSame( $aggregate, $meta['content_admission']['aggregate'] );
			$this->assertSame( 0, $aggregate['native_js_handles_activated'] );
			$this->assertSame( 0, $aggregate['js_translation_json_generated'] );
			$this->assertFileExists( $mo );
			$this->assertFileExists( $php );
			$this->assertSame( array(), glob( $path . '/' . $domain . '-fa_IR-*.json' ) ?: array() );

			if ( $is_full ) {
				$remainders = array_values(
					array_filter(
						$admission['admissions'],
						static fn( $record ) => 'PRODUCT_REMAINDER' === ( $record['authority_scope'] ?? null )
					)
				);
				$expected_by_product = array(
					'gravityforms' => array( 'records' => 7, 'preexisting' => 1759, 'remainder' => 2448, 'total' => 4207, 'runtime' => 4207 ),
					'gravityflow'  => array( 'records' => 8, 'preexisting' => 732, 'remainder' => 366, 'total' => 1098, 'runtime' => 1098 ),
					'gravityview'  => array( 'records' => 7, 'preexisting' => 461, 'remainder' => 2666, 'total' => 3127, 'runtime' => 3126 ),
				);
				$expected_full = $expected_by_product[ $product['product'] ];
				$this->assertCount( $expected_full['records'], $admission['admissions'] );
				$this->assertCount( 1, $remainders );
				$this->assertSame( $expected_full['preexisting'], $remainders[0]['preexisting_accepted_message_count'] );
				$this->assertSame( $expected_full['remainder'], $remainders[0]['admitted_message_count'] );
				$this->assertSame( $expected_full['total'], $aggregate['admitted_message_count'] );
				$this->assertSame( $expected_full['total'], $meta['authoritative_total'] );
				$this->assertSame( 100, $meta['coverage_percent'] );
				$this->assertSame( 'FULL_TRANSLATION_CONTENT_ACCEPTED', $meta['content_status'] );
				$this->assertSame( array( 'translated' => $expected_full['runtime'], 'untranslated' => 0, 'fuzzy' => 0 ), $meta['counts_in_committed_po'] );
				if ( $expected_full['runtime'] !== $expected_full['total'] ) {
					$this->assertSame( $expected_full['runtime'], $aggregate['runtime_provider_message_count'] );
					$this->assertSame( $expected_full['total'] - $expected_full['runtime'], $aggregate['runtime_projection_alias_count'] );
				}
			}
		}
	}
}
