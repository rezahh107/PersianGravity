<?php

use Gettext\Loader\StrictPoLoader;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';
require_once dirname( __DIR__ ) . '/tools/i18n/g007-admission.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-perks-rtl.php';

final class G007GravityPerksLocalizationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['pgr_test_inline_styles'] = array();
		$GLOBALS['pgr_test_style_states']  = array();
		$GLOBALS['pgr_test_locale']        = 'en_US';
		$GLOBALS['pgr_test_is_rtl']        = false;
	}

	public function test_exact_g007_products_are_full_reviewed_admissions(): void {
		$root    = dirname( __DIR__ );
		$records = pgr_validate_g007_admission( $root );
		$this->assertSame( array( 'gravityperks', 'gp-file-upload-pro', 'gp-advanced-select' ), array_keys( $records ) );
		$this->assertSame( 83, $records['gravityperks']['canonical_message_count'] );
		$this->assertSame( 39, $records['gp-file-upload-pro']['canonical_message_count'] );
		$this->assertSame( 5, $records['gp-advanced-select']['canonical_message_count'] );
		foreach ( $records as $record ) {
			$this->assertSame( 'CONTENT_ADMITTED_FULL', $record['content_state'] );
			$this->assertSame( $record['canonical_message_count'], $record['admitted_message_count'] );
			$this->assertSame( 0, $record['javascript']['native_translation_handles_activated'] );
		}
	}

	public function test_file_upload_acceptance_strings_are_in_the_runtime_provider(): void {
		$po      = dirname( __DIR__ ) . '/languages/providers/gp-file-upload-pro/source/fa_IR.po';
		$catalog = ( new StrictPoLoader() )->loadFile( $po );
		$values  = array();
		foreach ( $catalog as $entry ) {
			$values[ $entry->getOriginal() ] = $entry->getTranslation();
		}
		$this->assertSame( 'انتخاب فایل‌ها', $values['select files'] );
		$this->assertSame( 'فایل‌ها را اینجا رها کنید', $values['Drop files here'] );
		$this->assertSame( 'یا', $values['or'] );
	}

	public function test_cross_domain_calls_remain_outside_the_three_g007_catalogs(): void {
		$records = pgr_validate_g007_admission( dirname( __DIR__ ) );
		$this->assertSame( array( 'gravity-perks' ), array_keys( $records['gravityperks']['cross_domain_boundaries'] ) );
		$this->assertSame( array( 'gravityperks' ), array_keys( $records['gp-file-upload-pro']['cross_domain_boundaries'] ) );
		$this->assertSame(
			array( 'gp-advanced-phone-field', 'gp-populate-anything', 'gravityperks' ),
			array_keys( $records['gp-advanced-select']['cross_domain_boundaries'] )
		);
	}

	public function test_rtl_adapter_attaches_only_to_exact_registered_vendor_handle(): void {
		if ( ! defined( 'GP_ADVANCED_SELECT_VERSION' ) ) {
			define( 'GP_ADVANCED_SELECT_VERSION', '1.1.21' );
		}
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_is_rtl'] = true;
		$GLOBALS['pgr_test_style_states']['gp-advanced-select-tom-select']['registered'] = true;

		$adapter = new PGR_Gravity_Perks_RTL();
		$adapter->maybe_attach();
		$adapter->maybe_attach();

		$this->assertCount( 1, $GLOBALS['pgr_test_inline_styles'] );
		$this->assertSame( 'gp-advanced-select-tom-select', $GLOBALS['pgr_test_inline_styles'][0]['handle'] );
		$this->assertStringContainsString( '.ts-wrapper.rtl', $GLOBALS['pgr_test_inline_styles'][0]['data'] );
		$this->assertStringContainsString( 'background-position:left .75rem center', $GLOBALS['pgr_test_inline_styles'][0]['data'] );
		$this->assertStringContainsString( 'padding-left:max(var(--ts-pr-min),var(--ts-pr-clear-button) + var(--ts-pr-caret))!important', $GLOBALS['pgr_test_inline_styles'][0]['data'] );
		$this->assertStringContainsString( 'padding-right:var(--ts-pr-min)!important', $GLOBALS['pgr_test_inline_styles'][0]['data'] );
	}

	public function test_rtl_adapter_is_dormant_outside_fa_ir_rtl_or_without_vendor_handle(): void {
		$adapter = new PGR_Gravity_Perks_RTL();
		$adapter->maybe_attach();
		$this->assertSame( array(), $GLOBALS['pgr_test_inline_styles'] );

		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_is_rtl'] = true;
		$adapter->maybe_attach();
		$this->assertSame( array(), $GLOBALS['pgr_test_inline_styles'] );
	}
}
