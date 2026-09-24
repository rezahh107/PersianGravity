<?php

use PHPUnit\Framework\TestCase;

final class JalaliPresentationArchitectureTest extends TestCase {

	public function test_pure_converter_has_no_wordpress_gravity_forms_timezone_or_product_policy_dependency(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php' );

		$this->assertStringContainsString( 'Borkowski', $source );
		$this->assertStringContainsString( '7ff10a0a4145c84a6911e87bfacf40ddf51a2adc', $source );
		$this->assertStringNotContainsString( 'wp_timezone', $source );
		$this->assertStringNotContainsString( 'gform_', $source );
		$this->assertStringNotContainsString( 'GFAPI', $source );
		$this->assertStringNotContainsString( 'DateTimeZone', $source );
		$this->assertStringNotContainsString( 'PGR_Persian_Date', $source );
		$this->assertStringNotContainsString( 'pgr_jalali_date', $source );
		$this->assertStringNotContainsString( 'VALIDATED_PRODUCT_', $source );
		$this->assertStringNotContainsString( '2820', $source );
	}

	public function test_product_range_timezone_and_fallback_policy_live_in_typed_facade(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php' );
		$this->assertStringContainsString( 'DateTimeInterface', $source );
		$this->assertStringContainsString( 'VALIDATED_PRODUCT_MIN', $source );
		$this->assertStringContainsString( 'VALIDATED_PRODUCT_MAX', $source );
		$this->assertStringContainsString( 'wp_timezone', $source );
	}

	public function test_adapter_is_bounded_to_entries_list_date_created_display_only(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gf-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "add_filter( 'gform_entries_field_value'", $source );
		$this->assertStringContainsString( "'date_created'", $source );
		$this->assertStringContainsString( "new DateTimeZone( 'UTC' )", $source );
		$this->assertStringNotContainsString( 'gform_save_field_value', $source );
		$this->assertStringNotContainsString( 'GFAPI::update', $source );
		$this->assertStringNotContainsString( 'gravityflow', strtolower( $source ) );
		$this->assertStringNotContainsString( 'wp_date(', $source );
		$this->assertStringNotContainsString( 'date_i18n(', $source );
	}

	public function test_exact_runtime_derives_installed_plugin_version_from_release_authority(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/g008-jalali-presentation-runtime.yml' );

		$this->assertSame( 1, substr_count( $workflow, 'expected_pgr_version="$(php tools/release/release-tool.php current)"' ) );
		$this->assertStringContainsString(
			'test "$(php "$G008_WP_CLI" plugin get persian-gravityforms --field=version --path="$G008_WP_PATH")" = "$expected_pgr_version"',
			$workflow
		);
		$this->assertDoesNotMatchRegularExpression(
			'/plugin get persian-gravityforms --field=version[^\r\n]*\)" = "[0-9]+\.[0-9]+\.[0-9]+"/',
			$workflow
		);
	}

	public function test_g006_runtime_derives_persiangravity_version_from_release_authority_without_weakening_host_pins(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/g006-gravityforms-runtime.yml' );
		$harness  = file_get_contents( dirname( __DIR__ ) . '/tests/real-integration/g006-gravityforms-runtime.php' );

		$this->assertStringContainsString( "'tools/release/**'", $workflow );
		$this->assertSame( 1, substr_count( $workflow, 'expected_pgr_version="$(php tools/release/release-tool.php current)"' ) );
		$this->assertStringContainsString(
			'printf \'G006_PGR_EXPECTED_VERSION=%s\\n\' "$expected_pgr_version" >> "$GITHUB_ENV"',
			$workflow
		);
		$this->assertStringContainsString(
			'test "$(php "$G006_GF_WP_CLI" plugin get persian-gravityforms --field=version --path="$G006_GF_WP_PATH")" = "$G006_PGR_EXPECTED_VERSION"',
			$workflow
		);
		$this->assertDoesNotMatchRegularExpression(
			'/plugin get persian-gravityforms --field=version[^\r\n]*\)" = "[0-9]+\.[0-9]+\.[0-9]+"/',
			$workflow
		);

		$this->assertStringContainsString( '$expected_pgr_version = getenv( \'G006_PGR_EXPECTED_VERSION\' );', $harness );
		$this->assertStringContainsString( '$expected_pgr_version === $versions[\'persiangravity\']', $harness );
		$this->assertDoesNotMatchRegularExpression(
			"/'[0-9]+\\.[0-9]+\\.[0-9]+'\\s*===\\s*\\$versions\\['persiangravity'\\]/",
			$harness
		);

		$this->assertStringContainsString( 'G006_GF_GF_VERSION: 3.1.1.1', $workflow );
		$this->assertStringContainsString( 'G006_GF_FLOW_VERSION: 3.1.0', $workflow );
		$this->assertStringContainsString( 'G006_GF_GF_SHA256: 542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b', $workflow );
		$this->assertStringContainsString( 'G006_GF_FLOW_SHA256: ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404', $workflow );
	}
}
