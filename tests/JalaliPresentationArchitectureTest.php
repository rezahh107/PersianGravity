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
}
