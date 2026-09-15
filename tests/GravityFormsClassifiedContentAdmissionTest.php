<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFormsClassifiedContentAdmissionTest extends TestCase {
	public function test_six_surface_baseline_and_product_remainder_form_exact_full_union(): void {
		$root = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source = pgr_validate_admission( $root );
		$content = pgr_validate_content_admission( $root, $source, $products );
		$gf = $content['gravityforms'];
		$this->assertCount( 7, $gf['admissions'] );
		$this->assertSame( 'CONTENT_ADMITTED_FULL', $gf['content_state'] );
		$this->assertSame( 4207, $gf['aggregate']['admitted_message_count'] );
		$occurrences = 0; $frequency = array(); $remainders = array();
		foreach ( $gf['admissions'] as $record ) {
			if ( 'PRODUCT_REMAINDER' === ( $record['authority_scope'] ?? null ) ) {
				$remainders[] = $record;
				continue;
			}
			$occurrences += $record['admitted_message_count'];
			$index = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
			foreach ( $index['entries'] as $entry ) { $frequency[ $entry[0] ] = ( $frequency[ $entry[0] ] ?? 0 ) + 1; }
		}
		$this->assertCount( 1, $remainders );
		$this->assertSame( 1759, $remainders[0]['preexisting_accepted_message_count'] );
		$this->assertSame( 'e15f8e80cc711ea7b862be66d3a8a5827f85e0312caae391fcdecbf1a34fdec7', $remainders[0]['preexisting_accepted_keyset_sha256'] );
		$this->assertSame( 'a0b631dfcb88eb497be26086799a8816b7f85f99fd42c62afe6488ee508f9c27', $remainders[0]['preexisting_accepted_translation_content_sha256'] );
		$this->assertSame( 2448, $remainders[0]['admitted_message_count'] );
		$this->assertSame( 4207, $remainders[0]['canonical_message_count'] );
		$this->assertSame( '1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb', $remainders[0]['canonical_keyset_sha256'] );
		$this->assertSame( 1826, $occurrences );
		$this->assertCount( 1759, $frequency );
		$this->assertSame( 55, count( array_filter( $frequency, static fn( $n ) => 1 < $n ) ) );
		$this->assertSame( 67, array_sum( array_map( static fn( $n ) => max( 0, $n - 1 ), $frequency ) ) );
		$this->assertArrayNotHasKey( hash( 'sha256', "\x1fThe URL is not valid.\x1f" ), $frequency );
		$aggregate = pgr_content_load_sparse_po( $root . '/languages/providers/gravityforms/source/fa_IR.po', 'gravityforms', 'fa_IR' );
		$this->assertContains( hash( 'sha256', "\x1fThe URL is not valid.\x1f" ), $aggregate['ids'], true );
		$this->assertNotContains( '2b64a903d2065499fa0248c6199db150bb7d1a43025d2908e8a66e4af9d4ab92', $aggregate['ids'], true );
	}

	public function test_block_semantic_repairs_are_authoritative_and_propagate(): void {
		$root = dirname( __DIR__ );
		$block = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $root . '/languages/providers/gravityforms/source/records/frontend-block-fa_IR.po' );
		$aggregate = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $root . '/languages/providers/gravityforms/source/fa_IR.po' );
		foreach ( array( 'Colors' => 'رنگ‌ها', 'Accent' => 'رنگ تاکیدی' ) as $msgid => $expected ) {
			$this->assertSame( $expected, $this->translation( $block, $msgid ) );
			$this->assertSame( $expected, $this->translation( $aggregate, $msgid ) );
			$this->assertSame( array(), pgr_content_tokens( $msgid ) );
			$this->assertSame( array(), pgr_content_tokens( $expected ) );
		}
	}

	private function translation( $catalog, string $msgid ): string {
		foreach ( $catalog as $entry ) { if ( $entry->getOriginal() === $msgid ) { return (string) $entry->getTranslation(); } }
		throw new RuntimeException( 'Missing translation: ' . $msgid );
	}
}
