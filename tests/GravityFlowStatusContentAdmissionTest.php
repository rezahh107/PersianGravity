<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowStatusContentAdmissionTest extends TestCase {
	private array $temporary_roots = array();

	protected function tearDown(): void {
		foreach ( $this->temporary_roots as $root ) {
			$this->removeTree( $root );
		}
		$this->temporary_roots = array();
	}

	public function test_status_record_is_exactly_bound_to_the_registered_surface_and_reviewed_baseline(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$status   = $this->statusRecord( $content['gravityflow']['admissions'] );
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
		$this->assertSame( '9d82982c5117ed5e2988e2958dc21ae8b4bd6003453a63b2f8ce38d033271958', $status['provider_source_sha256'] );
		$this->assertSame( $index_ids, $provider['ids'] );
	}

	public function test_shared_inbox_status_identities_are_identical_and_deduplicate_to_288(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$records  = $content['gravityflow']['admissions'];
		$inbox    = $this->recordForSurface( $records, 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox' );
		$status   = $this->statusRecord( $records );
		$inbox_po = pgr_content_load_sparse_po( $root . '/' . $inbox['provider_source_path'], 'gravityflow', 'fa_IR' );
		$status_po = pgr_content_load_sparse_po( $root . '/' . $status['provider_source_path'], 'gravityflow', 'fa_IR' );
		$shared   = array_values( array_intersect( $inbox_po['ids'], $status_po['ids'] ) );

		$this->assertCount( 10, $shared );
		foreach ( $shared as $identity ) {
			$this->assertSame( $inbox_po['translation_rows'][ $identity ], $status_po['translation_rows'][ $identity ] );
		}
		$this->assertSame( 288, $content['gravityflow']['aggregate']['admitted_message_count'] );
		$this->assertSame( 255 + 43 - 10, $content['gravityflow']['aggregate']['admitted_message_count'] );
	}

	public function test_status_manifest_fingerprint_tampering_fails_closed(): void {
		$fields = array(
			'admitted_keyset_sha256',
			'admitted_surface_path_index_sha256',
			'admitted_translation_content_sha256',
			'surface_evidence_index_sha256',
			'provider_source_sha256',
		);

		foreach ( $fields as $field ) {
			$temp     = $this->productionFixture();
			$manifest = pgr_admission_json( $temp . '/tools/i18n/admission/content.json' );
			$status_i = $this->statusRecordIndex( $manifest['admissions'] );
			$manifest['admissions'][ $status_i ][ $field ] = str_repeat( '0', 64 );
			$this->writeJson( $temp . '/tools/i18n/admission/content.json', $manifest );

			try {
				$this->validateFixture( $temp );
				$this->fail( 'Status tampering unexpectedly validated for ' . $field );
			} catch ( RuntimeException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function test_status_evidence_path_tampering_fails_closed_even_with_updated_file_hash(): void {
		$temp     = $this->productionFixture();
		$manifest = pgr_admission_json( $temp . '/tools/i18n/admission/content.json' );
		$status_i = $this->statusRecordIndex( $manifest['admissions'] );
		$record   = &$manifest['admissions'][ $status_i ];
		$path     = $temp . '/' . $record['surface_evidence_index_path'];
		$index    = pgr_admission_json( $path );
		$index['paths'][0] = 'includes/pages/class-reports.php';
		$this->writeJsonCompact( $path, $index );
		$record['surface_evidence_index_sha256'] = hash_file( 'sha256', $path );
		$this->writeJson( $temp . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Out-of-surface source evidence path' );
		$this->validateFixture( $temp );
	}

	public function test_removing_status_record_restores_inbox_only_authority(): void {
		$temp     = $this->productionFixture();
		$manifest = pgr_admission_json( $temp . '/tools/i18n/admission/content.json' );
		$manifest['admissions'] = array_values(
			array_filter(
				$manifest['admissions'],
				static fn( array $record ): bool => 'gravityflow::workflow_runtime::admin_page:gravityflow-status' !== $record['surface_id']
			)
		);
		$this->writeJson( $temp . '/tools/i18n/admission/content.json', $manifest );

		$source_dir = $temp . '/languages/providers/gravityflow/source';
		$this->assertTrue( copy( $source_dir . '/records/inbox-fa_IR.po', $source_dir . '/fa_IR.po' ) );
		$provenance = pgr_admission_json( $source_dir . '/provenance.json' );
		$provenance['content_admissions'] = array( $this->provenanceProjection( $manifest['admissions'][0] ) );
		$provenance['content_aggregate'] = array(
			'admitted_message_count'              => 255,
			'admitted_keyset_sha256'              => '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a',
			'admitted_translation_content_sha256' => '6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f',
			'provider_source_path'                 => 'languages/providers/gravityflow/source/fa_IR.po',
			'provider_source_sha256'               => hash_file( 'sha256', $source_dir . '/fa_IR.po' ),
		);
		$this->writeJsonCompact( $source_dir . '/provenance.json', $provenance );

		$content = $this->validateFixture( $temp );
		$this->assertCount( 1, $content['gravityflow']['admissions'] );
		$this->assertSame( 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', $content['gravityflow']['admissions'][0]['surface_id'] );
		$this->assertSame( 255, $content['gravityflow']['aggregate']['admitted_message_count'] );
	}

	private function productionFixture(): string {
		$source_root = dirname( __DIR__ );
		$temp        = sys_get_temp_dir() . '/pgr-status-admission-' . bin2hex( random_bytes( 8 ) );
		$paths       = array(
			'tools/i18n/admission/content.json',
			'tools/i18n/admission/surfaces.json',
			'tools/i18n/admission/gravityflow-inbox-index.json',
			'tools/i18n/admission/gravityflow-status-index.json',
			'languages/providers/gravityflow/source/fa_IR.po',
			'languages/providers/gravityflow/source/provenance.json',
			'languages/providers/gravityflow/source/records/inbox-fa_IR.po',
			'languages/providers/gravityflow/source/records/status-fa_IR.po',
		);

		foreach ( $paths as $path ) {
			$target = $temp . '/' . $path;
			$this->assertTrue( is_dir( dirname( $target ) ) || mkdir( dirname( $target ), 0777, true ) );
			$this->assertTrue( copy( $source_root . '/' . $path, $target ) );
		}
		$this->temporary_roots[] = $temp;
		return $temp;
	}

	private function validateFixture( string $root ): array {
		$source_root = dirname( __DIR__ );
		$products    = require $source_root . '/includes/localization/products.php';
		$source      = pgr_validate_admission( $source_root );
		return pgr_validate_content_admission( $root, $source, $products );
	}

	private function statusRecord( array $records ): array {
		return $this->recordForSurface( $records, 'gravityflow::workflow_runtime::admin_page:gravityflow-status' );
	}

	private function recordForSurface( array $records, string $surface ): array {
		foreach ( $records as $record ) {
			if ( $surface === $record['surface_id'] ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing expected production admission: ' . $surface );
	}

	private function statusRecordIndex( array $records ): int {
		foreach ( $records as $index => $record ) {
			if ( 'gravityflow::workflow_runtime::admin_page:gravityflow-status' === $record['surface_id'] ) {
				return $index;
			}
		}
		throw new RuntimeException( 'Missing Status admission' );
	}

	private function provenanceProjection( array $record ): array {
		$fields = array(
			'product',
			'domain',
			'locale',
			'target_version',
			'content_state',
			'reviewed_source_po_sha256',
			'reviewed_source_message_count',
			'surface_id',
			'admitted_message_count',
			'admitted_keyset_sha256',
			'admitted_surface_path_index_sha256',
			'admitted_translation_content_sha256',
			'surface_evidence_index_path',
			'surface_evidence_index_sha256',
			'provider_source_path',
			'provider_source_sha256',
		);
		$result = array();
		foreach ( $fields as $field ) {
			$result[ $field ] = $record[ $field ];
		}
		return $result;
	}

	private function writeJson( string $path, array $data ): void {
		$this->assertNotFalse(
			file_put_contents(
				$path,
				json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n"
			)
		);
	}

	private function writeJsonCompact( string $path, array $data ): void {
		$this->assertNotFalse(
			file_put_contents(
				$path,
				json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n"
			)
		);
	}

	private function removeTree( string $root ): void {
		if ( ! is_dir( $root ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $root );
	}
}
