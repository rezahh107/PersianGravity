<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFlowInboxContentAdmissionTest extends TestCase {
	public function test_inbox_content_authority_is_unchanged_inside_the_two_record_union(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];
		$inbox    = array_values(
			array_filter(
				$flow['admissions'],
				static fn( array $record ): bool => 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox' === $record['surface_id']
			)
		)[0];

		$this->assertCount( 2, $flow['admissions'] );
		$this->assertSame( 255, $inbox['admitted_message_count'] );
		$this->assertSame( '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a', $inbox['admitted_keyset_sha256'] );
		$this->assertSame( '6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09', $inbox['admitted_surface_path_index_sha256'] );
		$this->assertSame( '6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f', $inbox['admitted_translation_content_sha256'] );
		$this->assertSame( 'languages/providers/gravityflow/source/records/inbox-fa_IR.po', $inbox['provider_source_path'] );
		$this->assertSame( 'ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570', $inbox['provider_source_sha256'] );
	}

	public function test_gravityflow_aggregate_is_inbox_plus_status_without_javascript_authority(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$flow     = $content['gravityflow'];

		$this->assertSame( 288, $flow['aggregate']['admitted_message_count'] );
		$this->assertSame( '58167ac415f0367a5a64dc098273b81bdca07a38641deaf75fd29ff4be42f363', $flow['aggregate']['admitted_keyset_sha256'] );
		$this->assertSame( '00c79c563019f01f68e0c03c9671e3587779cd6757fb830c53d1c2da4ecb4139', $flow['aggregate']['admitted_translation_content_sha256'] );
		$this->assertSame( '838c2409841c099d17d475e6290ec8fff49ad237331cd527f3e25769d2162267', $flow['aggregate']['provider_source_sha256'] );
		$this->assertSame( array(), $products['gravityflow']['scripts'] );
		$this->assertSame( 0, $flow['aggregate']['native_js_handles_activated'] );
		$this->assertSame( 0, $flow['aggregate']['js_translation_json_generated'] );
		$this->assertSame( '8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.mo' ) );
		$this->assertSame( '7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44', hash_file( 'sha256', $root . '/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php' ) );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityflow/gravityflow-fa_IR-*.json' ) ?: array() );
	}

	public function test_production_manifest_revision_two_contains_exactly_inbox_and_status(): void {
		$root     = dirname( __DIR__ );
		$manifest = pgr_admission_json( $root . '/tools/i18n/admission/content.json' );
		$flow     = array_values(
			array_filter(
				$manifest['admissions'],
				static fn( array $record ): bool => 'gravityflow' === ( $record['product'] ?? null )
			)
		);
		$surfaces = array_column( $flow, 'surface_id' );

		$this->assertSame( 2, $manifest['content_admission_revision'] );
		$this->assertSame(
			array(
				'gravityflow::workflow_runtime::admin_page:gravityflow-inbox',
				'gravityflow::workflow_runtime::admin_page:gravityflow-status',
			),
			$surfaces
		);
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
