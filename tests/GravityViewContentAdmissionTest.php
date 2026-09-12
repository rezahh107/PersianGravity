<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityViewContentAdmissionTest extends TestCase {
	private const SURFACE = 'gravityview::frontend_runtime::shortcode:gravityview';

	public function test_shortcode_record_is_exactly_bound_to_source_and_final_wu002_baseline(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface( $content['gravityview']['admissions'] );
		$index    = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gk-gravityview', 'fa_IR' );
		$index_ids = array_column( $index['entries'], 0 );
		sort( $index_ids, SORT_STRING );

		$this->assertSame( '2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca', $record['reviewed_source_po_sha256'] );
		$this->assertSame( 'af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829', $record['source_package_sha256'] );
		$this->assertNull( $record['vendor_pot_sha256'] );
		$this->assertSame( 3127, $record['reviewed_source_message_count'] );
		$this->assertSame( array( 'src/Shortcode/GravityViewShortcode.php' ), $index['source_path_rules'] );
		$this->assertSame( array( 'src/Shortcode/GravityViewShortcode.php' ), $index['paths'] );
		$this->assertSame(
			array(
				'3fe848f1b629acdcb7bfd703d8a9787cf579eabb1fc3db0c1ba7bae368ed86de',
				'8d69b8871a337eb30ca4da453d62b8fbae894f7859cbdae3129485f8ded2588c',
			),
			$index_ids
		);
		$this->assertSame( 2, $record['admitted_message_count'] );
		$this->assertSame( '061ddb2324a950ef0e64f9c252cec438403031c7a07a5c235dee5e71594729db', $record['admitted_keyset_sha256'] );
		$this->assertSame( '881e5750f77fbd1fedb4677d2e0775488ee9ac19ee42679e46ab6c045fe2668c', $record['admitted_surface_path_index_sha256'] );
		$this->assertSame( '293073a2b4d1a49b8c5becb59fe1f00f5c83e5b578b8b27de57749792368d13d', $record['admitted_translation_content_sha256'] );
		$this->assertSame( $record['surface_evidence_index_sha256'], hash_file( 'sha256', $root . '/' . $record['surface_evidence_index_path'] ) );
		$this->assertSame( $record['provider_source_sha256'], hash_file( 'sha256', $root . '/' . $record['provider_source_path'] ) );
		$this->assertSame( $index_ids, $provider['ids'] );
	}

	public function test_shortcode_translations_are_the_locked_final_wu002_values(): void {
		$root    = dirname( __DIR__ );
		$catalog = ( new Gettext\Loader\StrictPoLoader() )->loadFile(
			$root . '/languages/providers/gravityview/source/records/frontend-shortcode-fa_IR.po'
		);
		$expected = array(
			'This View is in the Trash. %1$sClick to restore the View%2$s.' => 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.',
			'Are you sure you want to restore this View? It will immediately be removed from the trash and set to draft status.' => 'آیا مطمئنید می‌خواهید این نما را بازیابی کنید؟ بلافاصله از زباله‌دان خارج و روی وضعیت draft قرار می‌گیرد.',
		);
		$actual = array();
		foreach ( $catalog as $entry ) {
			$actual[ $entry->getOriginal() ] = $entry->getTranslation();
		}

		$this->assertCount( 2, $actual );
		$this->assertSame( $expected, $actual );
		$this->assertStringContainsString( '%1$s', $actual['This View is in the Trash. %1$sClick to restore the View%2$s.'] );
		$this->assertStringContainsString( '%2$s', $actual['This View is in the Trash. %1$sClick to restore the View%2$s.'] );
	}

	public function test_gravityview_aggregate_is_exactly_the_first_bounded_record_and_js_stays_empty(): void {
		$root      = dirname( __DIR__ );
		$products  = require $root . '/includes/localization/products.php';
		$source    = pgr_validate_admission( $root );
		$content   = pgr_validate_content_admission( $root, $source, $products );
		$aggregate = $content['gravityview']['aggregate'];

		$this->assertCount( 1, $content['gravityview']['admissions'] );
		$this->assertSame( 2, $aggregate['admitted_message_count'] );
		$this->assertSame( '061ddb2324a950ef0e64f9c252cec438403031c7a07a5c235dee5e71594729db', $aggregate['admitted_keyset_sha256'] );
		$this->assertSame( '293073a2b4d1a49b8c5becb59fe1f00f5c83e5b578b8b27de57749792368d13d', $aggregate['admitted_translation_content_sha256'] );
		$this->assertSame( array(), $products['gk-gravityview']['scripts'] );
		$this->assertSame( 0, $aggregate['native_js_handles_activated'] );
		$this->assertSame( 0, $aggregate['js_translation_json_generated'] );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityview/gk-gravityview-fa_IR-*.json' ) ?: array() );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityview/gravityview-fa_IR-*.json' ) ?: array() );
	}

	public function test_non_admitted_gravityview_identity_and_dependency_domain_remain_outside_provider_authority(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface( $content['gravityview']['admissions'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gk-gravityview', 'fa_IR' );

		$this->assertContains( '3fe848f1b629acdcb7bfd703d8a9787cf579eabb1fc3db0c1ba7bae368ed86de', $provider['ids'] );
		$this->assertContains( '8d69b8871a337eb30ca4da453d62b8fbae894f7859cbdae3129485f8ded2588c', $provider['ids'] );
		$this->assertNotContains( '1b166b56b6ad41dc0be4659392124a92ad489331fe6c8283d8998fc895809d4a', $provider['ids'] );
		$this->assertSame( 2, count( $provider['ids'] ) );
		$this->assertFalse( $source['gravityview']['domain_boundaries']['gk-query-filters']['runtime_manifested_by_persiangravity'] );
		$this->assertSame( 'BOUNDED_DEPENDENCY_DOMAIN_BOUNDARY_NOT_MERGED_NOT_RUNTIME_ACTIVATED', $source['gravityview']['domain_boundaries']['gk-query-filters']['decision'] );
	}

	public function test_vendor_pot_null_is_accepted_only_when_the_validated_source_record_is_also_null(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );

		$this->assertNull( $source['gravityview']['vendor_pot_sha256'] );
		$this->assertFalse( $source['gravityview']['source_pot_consistency']['vendor_pot_present'] );
		$this->assertSame( 'NOT_APPLICABLE_VENDOR_POT_ABSENT', $source['gravityview']['source_pot_consistency']['comparison'] );
		$this->assertArrayHasKey( 'gravityview', pgr_validate_content_admission( $root, $source, $products ) );

		$source['gravityview']['vendor_pot_sha256'] = str_repeat( 'a', 64 );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'not bound to validated source admission' );
		pgr_validate_content_admission( $root, $source, $products );
	}

	private function recordForSurface( array $records ): array {
		foreach ( $records as $record ) {
			if ( self::SURFACE === $record['surface_id'] ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing GravityView frontend shortcode admission' );
	}
}
