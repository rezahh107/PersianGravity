<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';

final class GravityViewSourceAdmissionTest extends TestCase {
	public function test_gravityview_source_remains_admitted_with_bounded_dependency_boundary() {
		$root        = dirname( __DIR__ );
		$records     = pgr_validate_admission( $root );
		$gravityview = $records['gravityview'];
		$products    = require $root . '/includes/localization/products.php';

		$this->assertSame( '3.3.4', $gravityview['observed_source_version'] );
		$this->assertSame( 'af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829', $gravityview['package_sha256'] );
		$this->assertSame( 'NOT_PROVEN', $gravityview['vendor_authenticity'] );
		$this->assertNull( $gravityview['vendor_pot_sha256'] );
		$this->assertFalse( $gravityview['source_pot_consistency']['vendor_pot_present'] );

		$query_filters = $gravityview['domain_boundaries']['gk-query-filters'];
		$this->assertSame( 'gravitykit/query-filters', $query_filters['composer_package'] );
		$this->assertSame( 'BOUNDED_DEPENDENCY_DOMAIN_BOUNDARY_NOT_MERGED_NOT_RUNTIME_ACTIVATED', $query_filters['decision'] );
		$this->assertFalse( $query_filters['runtime_manifested_by_persiangravity'] );
		$this->assertArrayNotHasKey( 'gk-query-filters', $products );
		$this->assertSame( array(), $products['gk-gravityview']['scripts'] );
	}

	public function test_gravityview_recommendation_is_source_bounded_and_zero_js_activation() {
		$root     = dirname( __DIR__ );
		$surfaces = json_decode( file_get_contents( $root . '/tools/i18n/admission/surfaces.json' ), true, 512, JSON_THROW_ON_ERROR );
		$js       = json_decode( file_get_contents( $root . '/tools/i18n/admission/javascript.json' ), true, 512, JSON_THROW_ON_ERROR );
		$rtl      = json_decode( file_get_contents( $root . '/tools/i18n/admission/rtl-bidi.json' ), true, 512, JSON_THROW_ON_ERROR );
		$pick     = $surfaces['recommended_first_surface'];

		$this->assertSame( 'gravityview::frontend_runtime::shortcode:gravityview', $pick['surface_id'] );
		$this->assertSame( array( 'src/Shortcode/GravityViewShortcode.php' ), $pick['source_path_rules'] );
		$this->assertSame( 2, $pick['unique_identity_count'] );
		$this->assertSame( 0, $pick['shared_multi_surface_identity_count'] );
		$this->assertSame( 0, $js['gravityview']['runtime_activated_handles'] );
		$this->assertSame( 2, $js['gravityview']['native_translation_attachment_sites'] );
		$this->assertSame( 'NOT_PRESENT_IN_TARGET_SOURCE', $js['gravityview']['script_modules'] );
		$this->assertSame( 'NOT_RUN', $rtl['gravityview_evidence']['browser_execution'] );
		$this->assertContains( $pick['surface_id'], $rtl['surface_ids'] );
	}
}
