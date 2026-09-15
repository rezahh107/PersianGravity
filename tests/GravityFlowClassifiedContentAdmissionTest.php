<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowClassifiedContentAdmissionTest extends TestCase {
	public function test_seven_surface_counts_historical_union_and_full_remainder_boundary_are_exact(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];
		$expected = array(
			'gravityflow::workflow_runtime::shortcode:gravityflow'                    => 15,
			'gravityflow::workflow_runtime::admin_page:gravityflow-inbox'             => 255,
			'gravityflow::workflow_runtime::admin_page:gravityflow-status'            => 43,
			'gravityflow::workflow_runtime::admin_page:gravityflow-reports'           => 20,
			'gravityflow::admin_builder::form_settings:gravityflow'                   => 391,
			'gravityflow::settings_integrations::admin_page:gravityflow_settings'     => 44,
			'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow' => 0,
		);
		$actual            = array();
		$frequency         = array();
		$occurrences       = 0;
		$surface_records   = array_values( array_filter( $flow['admissions'], static fn( array $record ): bool => isset( $record['surface_id'] ) ) );
		$remainder_records = array_values( array_filter( $flow['admissions'], static fn( array $record ): bool => 'PRODUCT_REMAINDER' === ( $record['authority_scope'] ?? null ) ) );

		foreach ( $surface_records as $record ) {
			$actual[ $record['surface_id'] ] = $record['admitted_message_count'];
			$occurrences += $record['admitted_message_count'];
			$index = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
			foreach ( $index['entries'] as $entry ) {
				$frequency[ $entry[0] ] = ( $frequency[ $entry[0] ] ?? 0 ) + 1;
			}
		}

		$this->assertCount( 8, $flow['admissions'] );
		$this->assertCount( 7, $surface_records );
		$this->assertCount( 1, $remainder_records );
		ksort( $expected, SORT_STRING );
		ksort( $actual, SORT_STRING );
		$this->assertSame( $expected, $actual );
		$this->assertSame( 768, $occurrences );
		$this->assertSame( 732, count( $frequency ) );
		$this->assertSame( 30, count( array_filter( $frequency, static fn( int $count ): bool => 1 < $count ) ) );
		$this->assertSame( 36, array_sum( array_map( static fn( int $count ): int => max( 0, $count - 1 ), $frequency ) ) );
		$this->assertSame( 732, $remainder_records[0]['preexisting_accepted_message_count'] );
		$this->assertSame( 'a1367158915f7e3369e138cdd5c2b1474cd75b8c0372f3a674440d2c78025a12', $remainder_records[0]['preexisting_accepted_keyset_sha256'] );
		$this->assertSame( '66d251b35c04a2bbd24138f1d99fc006689a615e47e82ed679cd998881982826', $remainder_records[0]['preexisting_accepted_translation_content_sha256'] );
		$this->assertSame( 366, $remainder_records[0]['admitted_message_count'] );
		$this->assertSame( 1098, $flow['aggregate']['admitted_message_count'] );
		$this->assertSame( 0, 1098 - $flow['aggregate']['admitted_message_count'] );
		$this->assertSame( '612c1ded4350b766c35b9abda22fcc213f91a1cc77e9f78f5fe11caa26244903', $flow['aggregate']['admitted_keyset_sha256'] );
	}

	public function test_all_overlaps_have_identical_translation_rows_and_conflicts_remain_fail_closed(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$rows_by_identity = array();
		$surface_records  = array_values( array_filter( $content['gravityflow']['admissions'], static fn( array $record ): bool => isset( $record['surface_id'] ) ) );

		foreach ( $surface_records as $record ) {
			$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityflow', 'fa_IR' );
			foreach ( $provider['translation_rows'] as $identity => $row ) {
				if ( isset( $rows_by_identity[ $identity ] ) ) {
					$this->assertSame( $rows_by_identity[ $identity ], $row );
				} else {
					$rows_by_identity[ $identity ] = $row;
				}
			}
		}

		$this->assertCount( 732, $rows_by_identity );

		$remainder = array_values( array_filter( $content['gravityflow']['admissions'], static fn( array $record ): bool => 'PRODUCT_REMAINDER' === ( $record['authority_scope'] ?? null ) ) );
		$this->assertCount( 1, $remainder );
		$remainder_po = pgr_content_load_sparse_po( $root . '/' . $remainder[0]['provider_source_path'], 'gravityflow', 'fa_IR' );
		$this->assertCount( 366, $remainder_po['ids'] );
		$this->assertSame( array(), array_values( array_intersect( array_keys( $rows_by_identity ), $remainder_po['ids'] ) ) );
		$this->assertCount( 1098, array_unique( array_merge( array_keys( $rows_by_identity ), $remainder_po['ids'] ), SORT_STRING ) );
	}

	public function test_zero_identity_accepted_surface_is_an_independent_auditable_record(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface(
			$content['gravityflow']['admissions'],
			'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow'
		);
		$empty_hash = hash( 'sha256', '' );
		$index      = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
		$provider   = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityflow', 'fa_IR' );

		$this->assertSame( 0, $record['admitted_message_count'] );
		$this->assertSame( $empty_hash, $record['admitted_keyset_sha256'] );
		$this->assertSame( $empty_hash, $record['admitted_surface_path_index_sha256'] );
		$this->assertSame( $empty_hash, $record['admitted_translation_content_sha256'] );
		$this->assertSame( array(), $index['paths'] );
		$this->assertSame( array(), $index['entries'] );
		$this->assertSame( array(), $provider['ids'] );
	}

	public function test_former_non_admitted_source_identity_is_now_admitted_and_gravityforms_full_state_is_preserved(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$aggregate = pgr_content_load_sparse_po(
			$root . '/languages/providers/gravityflow/source/fa_IR.po',
			'gravityflow',
			'fa_IR'
		);
		$formerly_non_admitted = hash(
			'sha256',
			"\x1fAllow the Reports shortcode to display workflow reports to all registered and anonymous users.\x1f"
		);

		$this->assertContains( $formerly_non_admitted, $aggregate['ids'], true );
		$this->assertSame( 1098, count( $aggregate['ids'] ) );
		$this->assertSame( 'CONTENT_ADMITTED_FULL', $content['gravityflow']['content_state'] );
		$this->assertSame( 4207, $content['gravityforms']['aggregate']['admitted_message_count'] );
		$this->assertSame( 'CONTENT_ADMITTED_FULL', $content['gravityforms']['content_state'] );
		$this->assertCount( 7, $content['gravityforms']['admissions'] );
		$this->assertSame( array(), $products['gravityflow']['scripts'] );
	}

	private function recordForSurface( array $records, string $surface ): array {
		foreach ( $records as $record ) {
			if ( $surface === ( $record['surface_id'] ?? null ) ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing expected production admission: ' . $surface );
	}
}
