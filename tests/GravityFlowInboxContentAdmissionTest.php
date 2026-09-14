<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowInboxContentAdmissionTest extends TestCase {
	public function test_inbox_content_authority_is_preserved_inside_the_seven_surface_union(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];
		$inbox    = $this->recordForSurface(
			$flow['admissions'],
			'gravityflow::workflow_runtime::admin_page:gravityflow-inbox'
		);

		$this->assertCount( 7, $flow['admissions'] );
		$this->assertSame( 255, $inbox['admitted_message_count'] );
		$this->assertSame( '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a', $inbox['admitted_keyset_sha256'] );
		$this->assertSame( '6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09', $inbox['admitted_surface_path_index_sha256'] );
		$this->assertSame( '6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f', $inbox['admitted_translation_content_sha256'] );
		$this->assertSame( '7eb6a9203d194995cd1de8e7cbb91d83b935dd591247506c386f0145bb731e0f', $inbox['surface_evidence_index_sha256'] );
		$this->assertSame( 'languages/providers/gravityflow/source/records/inbox-fa_IR.po', $inbox['provider_source_path'] );
		$this->assertSame( 'ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570', $inbox['provider_source_sha256'] );
	}

	public function test_gravityflow_aggregate_is_the_exact_classified_union_without_javascript_authority(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];

		$this->assertSame( 732, $flow['aggregate']['admitted_message_count'] );
		$this->assertSame( 'a1367158915f7e3369e138cdd5c2b1474cd75b8c0372f3a674440d2c78025a12', $flow['aggregate']['admitted_keyset_sha256'] );
		$this->assertSame( '66d251b35c04a2bbd24138f1d99fc006689a615e47e82ed679cd998881982826', $flow['aggregate']['admitted_translation_content_sha256'] );
		$this->assertSame( 'bff3f53338558fd9c62e19a6dc8161f39c61a8cdb42d807acc1fe6693868fa1a', $flow['aggregate']['provider_source_sha256'] );
		$this->assertSame( array(), $products['gravityflow']['scripts'] );
		$this->assertSame( 0, $flow['aggregate']['native_js_handles_activated'] );
		$this->assertSame( 0, $flow['aggregate']['js_translation_json_generated'] );
		$this->assertSame( '247224d3385df3fbbeda0bb2177d649858b808d10586b7ecac8da90a5424615c', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.mo' ) );
		$this->assertSame( '580e658d61be7190fb53db1fe7c9f1cf15ba5210ef8aa7fe8c4bf5432aa262d0', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php' ) );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityflow/gravityflow-fa_IR-*.json' ) ?: array() );
	}

	public function test_product_identity_and_valid_source_admission_without_content_records_grant_no_content_authority(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$temp     = sys_get_temp_dir() . '/pgr-content-admission-' . bin2hex( random_bytes( 8 ) );
		$dir      = $temp . '/tools/i18n/admission';

		$this->assertArrayHasKey( 'gravityflow', $products );
		$this->assertArrayHasKey( 'gravityflow', $source );
		$this->assertTrue( mkdir( $dir, 0777, true ) );
		$this->assertTrue( copy( $root . '/tools/i18n/admission/surfaces.json', $dir . '/surfaces.json' ) );

		$manifest               = pgr_admission_json( $root . '/tools/i18n/admission/content.json' );
		$manifest['admissions'] = array();
		$this->assertNotFalse(
			file_put_contents(
				$dir . '/content.json',
				json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n"
			)
		);

		try {
			$this->assertSame( array(), pgr_validate_content_admission( $temp, $source, $products ) );
		} finally {
			@unlink( $dir . '/content.json' );
			@unlink( $dir . '/surfaces.json' );
			@rmdir( $dir );
			@rmdir( $temp . '/tools/i18n' );
			@rmdir( $temp . '/tools' );
			@rmdir( $temp );
		}
	}

	private function recordForSurface( array $records, string $surface ): array {
		foreach ( $records as $record ) {
			if ( $surface === $record['surface_id'] ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing expected production admission: ' . $surface );
	}
}
