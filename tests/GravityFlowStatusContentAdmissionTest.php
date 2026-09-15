<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowStatusContentAdmissionTest extends TestCase {
	public function test_status_record_is_exactly_preserved_from_the_accepted_authority(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$status   = $this->recordForSurface(
			$content['gravityflow']['admissions'],
			'gravityflow::workflow_runtime::admin_page:gravityflow-status'
		);
		$index    = pgr_admission_json( $root . '/' . $status['surface_evidence_index_path'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $status['provider_source_path'], 'gravityflow', 'fa_IR' );
		$index_ids = array_column( $index['entries'], 0 );
		sort( $index_ids, SORT_STRING );

		$this->assertSame( 'c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9', $status['reviewed_source_po_sha256'] );
		$this->assertSame( 1098, $status['reviewed_source_message_count'] );
		$this->assertSame( array( 'includes/pages/class-status.php' ), $index['source_path_rules'] );
		$this->assertSame( array( 'includes/pages/class-status.php' ), $index['paths'] );
		$this->assertSame( 43, $status['admitted_message_count'] );
		$this->assertSame( '08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d', $status['admitted_keyset_sha256'] );
		$this->assertSame( '0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed', $status['admitted_surface_path_index_sha256'] );
		$this->assertSame( '32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7', $status['admitted_translation_content_sha256'] );
		$this->assertSame( 'c3583bfb2695095dcd0b65baa78dfb3233e976124dd0a18ba3bd9af1d322dd9e', $status['surface_evidence_index_sha256'] );
		$this->assertSame( 'c160913904bf991b29274bfbda3615a1a12049c9b97b81f7b687ac9e5d0fd718', $status['provider_source_sha256'] );
		$this->assertSame( $status['provider_source_sha256'], hash_file( 'sha256', $root . '/' . $status['provider_source_path'] ) );
		$this->assertSame( $index_ids, $provider['ids'] );
	}

	public function test_inbox_status_overlap_remains_ten_identical_identities_inside_the_larger_union(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$records  = $content['gravityflow']['admissions'];
		$inbox    = $this->recordForSurface( $records, 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox' );
		$status   = $this->recordForSurface( $records, 'gravityflow::workflow_runtime::admin_page:gravityflow-status' );
		$inbox_po = pgr_content_load_sparse_po( $root . '/' . $inbox['provider_source_path'], 'gravityflow', 'fa_IR' );
		$status_po = pgr_content_load_sparse_po( $root . '/' . $status['provider_source_path'], 'gravityflow', 'fa_IR' );
		$shared   = array_values( array_intersect( $inbox_po['ids'], $status_po['ids'] ) );

		$this->assertCount( 10, $shared );
		foreach ( $shared as $identity ) {
			$this->assertSame( $inbox_po['translation_rows'][ $identity ], $status_po['translation_rows'][ $identity ] );
		}
		$this->assertSame( 288, count( array_unique( array_merge( $inbox_po['ids'], $status_po['ids'] ) ) ) );
		$this->assertSame( 1098, $content['gravityflow']['aggregate']['admitted_message_count'] );
		$this->assertSame( 'CONTENT_ADMITTED_FULL', $content['gravityflow']['content_state'] );
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
