<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowInboxContentAdmissionTest extends TestCase {
	public function test_inbox_partial_content_admission_is_hash_bound_and_sparse(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );

		$this->assertSame( 255, $content['gravityflow']['computed']['admitted_message_count'] );
		$this->assertSame( '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a', $content['gravityflow']['computed']['admitted_keyset_sha256'] );
		$this->assertSame( 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', $content['gravityflow']['surface_id'] );
		$this->assertSame( array(), $products['gravityflow']['scripts'] );
		$this->assertSame( 0, $content['gravityflow']['native_js_handles_activated'] );
		$this->assertSame( 0, $content['gravityflow']['js_translation_json_generated'] );
		$this->assertFileExists( $root . '/languages/providers/gravityflow/gravityflow-fa_IR.mo' );
		$this->assertFileExists( $root . '/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php' );
		$this->assertFileDoesNotExist( $root . '/languages/providers/gravityflow/gravityflow-fa_IR-gravityflow_inbox.json' );
	}

	public function test_gravityflow_product_identity_does_not_authorize_runtime_content_without_validated_admission(): void {
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
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Content admission must contain exactly the approved Gravity Flow record' );
			pgr_validate_content_admission( $temp, $source, $products );
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
