<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFormsClassifiedContentAdmissionTest extends TestCase {
	public function test_six_surface_union_and_non_admitted_boundary_are_exact(): void {
		$root = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source = pgr_validate_admission( $root );
		$content = pgr_validate_content_admission( $root, $source, $products );
		$gf = $content['gravityforms'];
		$this->assertCount( 6, $gf['admissions'] );
		$this->assertSame( 1759, $gf['aggregate']['admitted_message_count'] );
		$this->assertSame( 'e15f8e80cc711ea7b862be66d3a8a5827f85e0312caae391fcdecbf1a34fdec7', $gf['aggregate']['admitted_keyset_sha256'] );
		$occurrences = 0; $frequency = array();
		foreach ( $gf['admissions'] as $record ) {
			$occurrences += $record['admitted_message_count'];
			$index = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
			foreach ( $index['entries'] as $entry ) { $frequency[ $entry[0] ] = ( $frequency[ $entry[0] ] ?? 0 ) + 1; }
		}
		$this->assertSame( 1826, $occurrences );
		$this->assertSame( 55, count( array_filter( $frequency, static fn( $n ) => 1 < $n ) ) );
		$this->assertSame( 67, array_sum( array_map( static fn( $n ) => max( 0, $n - 1 ), $frequency ) ) );
		$this->assertSame( 2448, 4207 - $gf['aggregate']['admitted_message_count'] );
		$this->assertArrayNotHasKey( hash( 'sha256', "\x1fThe URL is not valid.\x1f" ), $frequency );
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
