<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowInboxContentAdmissionTest extends TestCase {
	public function test_inbox_partial_content_admission_is_hash_bound_sparse_and_aggregated(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];

		$this->assertCount( 1, $flow['admissions'] );
		$this->assertSame( 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', $flow['admissions'][0]['surface_id'] );
		$this->assertSame( 255, $flow['aggregate']['admitted_message_count'] );
		$this->assertSame( '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a', $flow['aggregate']['admitted_keyset_sha256'] );
		$this->assertSame( '6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f', $flow['aggregate']['admitted_translation_content_sha256'] );
		$this->assertSame( 'ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570', $flow['aggregate']['provider_source_sha256'] );
		$this->assertSame( array(), $products['gravityflow']['scripts'] );
		$this->assertSame( 0, $flow['aggregate']['native_js_handles_activated'] );
		$this->assertSame( 0, $flow['aggregate']['js_translation_json_generated'] );
		$this->assertSame( 'ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570', hash_file( 'sha256', $root . '/languages/providers/gravityflow/source/fa_IR.po' ) );
		$this->assertSame( 'cefef16940557a5b4586972d5abeb3be3f0b2f17eba8a53db2f14f30c543d04b', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.mo' ) );
		$this->assertSame( '731d54d4a612533fe614293328c9912cd59b267cd49585416a4c6aaaeb48f983', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php' ) );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityflow/gravityflow-fa_IR-*.json' ) ?: array() );
	}

	public function test_production_manifest_revision_two_still_contains_only_the_existing_inbox_admission(): void {
		$root     = dirname( __DIR__ );
		$manifest = pgr_admission_json( $root . '/tools/i18n/admission/content.json' );

		$this->assertSame( 2, $manifest['content_admission_revision'] );
		$this->assertCount( 1, $manifest['admissions'] );
		$this->assertSame( 'gravityflow', $manifest['admissions'][0]['product'] );
		$this->assertSame( 'gravityflow', $manifest['admissions'][0]['domain'] );
		$this->assertSame( '3.1.0', $manifest['admissions'][0]['target_version'] );
		$this->assertSame( 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', $manifest['admissions'][0]['surface_id'] );
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
}
