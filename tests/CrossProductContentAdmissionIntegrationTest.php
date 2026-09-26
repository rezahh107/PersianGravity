<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class CrossProductContentAdmissionIntegrationTest extends TestCase {
	public function test_integrated_manifest_contains_only_the_accepted_records(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$manifest = pgr_admission_json( $root . '/tools/i18n/admission/content.json' );
		$expected = array(
			'gravityforms' => array(
				'domain'       => 'gravityforms',
				'records'      => 7,
				'union'        => 4207,
				'source'       => 4207,
				'non_admitted' => 0,
			),
			'gravityflow' => array(
				'domain'       => 'gravityflow',
				'records'      => 8,
				'union'        => 1098,
				'source'       => 1098,
				'non_admitted' => 0,
			),
			'gravityview' => array(
				'domain'       => 'gk-gravityview',
				'records'      => 7,
				'union'        => 3127,
				'source'       => 3127,
				'non_admitted' => 0,
			),
		);

		$this->assertCount( 22, $manifest['admissions'] );
		$this->assertSame(
			array( 'gk-gravityview', 'gravityflow', 'gravityforms' ),
			$this->sortedKeys( $products )
		);

		$record_identities = array();
		$total_records     = 0;
		foreach ( $manifest['admissions'] as $record ) {
			$this->assertArrayHasKey( $record['product'], $expected );
			$this->assertSame( $expected[ $record['product'] ]['domain'], $record['domain'] );
			$record_identities[] = pgr_content_record_identity( $record );
		}
		$this->assertCount( 22, array_unique( $record_identities, SORT_STRING ) );

		foreach ( $expected as $product => $contract ) {
			$this->assertArrayHasKey( $product, $content );
			$this->assertCount( $contract['records'], $content[ $product ]['admissions'] );
			$this->assertSame( $contract['union'], $content[ $product ]['aggregate']['admitted_message_count'] );
			$this->assertSame( $contract['source'], $source[ $product ]['canonical_message_count'] );
			$this->assertSame(
				$contract['non_admitted'],
				$source[ $product ]['canonical_message_count'] - $content[ $product ]['aggregate']['admitted_message_count']
			);

			$aggregate = pgr_content_load_sparse_po(
				$root . '/' . $content[ $product ]['aggregate']['provider_source_path'],
				$contract['domain'],
				'fa_IR'
			);
			$runtime_count = $content[ $product ]['aggregate']['runtime_provider_message_count'] ?? $contract['union'];
			$this->assertCount( $runtime_count, $aggregate['ids'] );
			$total_records += count( $content[ $product ]['admissions'] );
		}
		$this->assertSame( 22, $total_records );
	}

	public function test_full_product_boundaries_and_js_authority_remain_bounded(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$outside  = array(
			'gravityforms' => array( 'gravityforms', 'PersianGravity Gravity Forms out-of-census fallback probe.' ),
			'gravityflow'  => array( 'gravityflow', 'PersianGravity Gravity Flow out-of-census fallback probe.' ),
			'gravityview'  => array( 'gk-gravityview', 'PersianGravity GravityView out-of-census fallback probe.' ),
		);

		foreach ( $outside as $product => $case ) {
			$aggregate = pgr_content_load_sparse_po(
				$root . '/' . $content[ $product ]['aggregate']['provider_source_path'],
				$case[0],
				'fa_IR'
			);
			$identity = hash( 'sha256', "\x1f" . $case[1] . "\x1f" );
			$this->assertNotContains( $identity, $aggregate['ids'], true );
			$this->assertSame( array(), $products[ $case[0] ]['scripts'] );
			$this->assertSame( 0, $content[ $product ]['aggregate']['native_js_handles_activated'] );
			$this->assertSame( 0, $content[ $product ]['aggregate']['js_translation_json_generated'] );
			$this->assertSame(
				array(),
				glob( $root . '/languages/providers/' . $product . '/*-fa_IR-*.json' ) ?: array()
			);
		}

		$this->assertArrayNotHasKey( 'gk-query-filters', $products );
		$this->assertArrayNotHasKey( 'action-scheduler', $products );
		$this->assertFalse( $source['gravityview']['domain_boundaries']['gk-query-filters']['runtime_manifested_by_persiangravity'] );
		$this->assertSame(
			'BOUNDED_DEPENDENCY_DOMAIN_BOUNDARY_NOT_MERGED_NOT_RUNTIME_ACTIVATED',
			$source['gravityview']['domain_boundaries']['gk-query-filters']['decision']
		);
		$this->assertSame(
			'EXCLUDED_FROM_GRAVITYVIEW_PROVIDER_BOUNDARY',
			$source['gravityview']['domain_boundaries']['action-scheduler']['decision']
		);
	}

	private function sortedKeys( array $values ): array {
		$keys = array_keys( $values );
		sort( $keys, SORT_STRING );
		return $keys;
	}
}
