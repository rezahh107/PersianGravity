<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityViewJalaliPresentationTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_both_exact_frontend_fields_use_authoritative_utc_entry_values(): void {
		$this->bootstrap_adapter( '3.3.4' );
		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';

		$entry = array(
			'id'           => 9,
			'date_created' => '2026-03-20 20:29:00',
			'date_updated' => '2026-03-22 20:31:00',
		);
		$context = $this->context( 'date_created', $entry );
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۴/۱۲/۲۹، ۲۳:۵۹',
			$adapter->filter_date_created_output( '2026-03-20 23:59:00', $context )
		);

		$context = $this->context( 'date_updated', $entry );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۳، ۰۰:۰۱',
			$adapter->filter_date_updated_output( '2026-03-23 00:01:00', $context )
		);
		$this->assertSame( $entry, $context->entry->as_entry() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_wrong_field_missing_context_and_malformed_raw_values_fail_native(): void {
		$this->bootstrap_adapter( '3.3.4' );
		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();

		$this->assertSame(
			'native-created',
			$adapter->filter_date_created_output(
				'native-created',
				$this->context( 'date_updated', array( 'id' => 9, 'date_created' => '2026-03-20 20:29:00' ) )
			)
		);
		$this->assertSame(
			'native-updated',
			$adapter->filter_date_updated_output(
				'native-updated',
				$this->context( 'date_created', array( 'id' => 9, 'date_updated' => '2026-03-22 20:31:00' ) )
			)
		);
		$this->assertSame( 'native-null', $adapter->filter_date_created_output( 'native-null', null ) );
		$this->assertSame(
			'native-malformed',
			$adapter->filter_date_created_output(
				'native-malformed',
				$this->context( 'date_created', array( 'id' => 9, 'date_created' => 'not-a-date' ) )
			)
		);
		$this->assertSame(
			'native-missing',
			$adapter->filter_date_updated_output(
				'native-missing',
				$this->context( 'date_updated', array( 'id' => 9 ) )
			)
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_non_persian_admin_and_unsupported_version_fail_native(): void {
		$this->bootstrap_adapter( '3.3.4' );
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$context = $this->context(
			'date_created',
			array( 'id' => 9, 'date_created' => '2026-03-20 20:29:00' )
		);

		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();
		$this->assertSame( 'native-en', $adapter->filter_date_created_output( 'native-en', $context ) );

		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_is_admin'] = true;
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();
		$this->assertSame( 'native-admin', $adapter->filter_date_created_output( 'native-admin', $context ) );

		$GLOBALS['pgr_test_is_admin'] = false;
		$this->write_host_plugin( '3.3.5' );
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();
		$this->assertSame( 'native-drift', $adapter->filter_date_created_output( 'native-drift', $context ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_module_disabled_and_missing_facade_fail_native(): void {
		$this->bootstrap_adapter( '3.3.4', false, false );
		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$context = $this->context(
			'date_created',
			array( 'id' => 9, 'date_created' => '2026-03-20 20:29:00' )
		);
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();

		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
		$this->assertFalse( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertSame( 'native', $adapter->filter_date_created_output( 'native', $context ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_hooks_and_source_keep_the_production_boundary_bounded(): void {
		$this->bootstrap_adapter( '3.3.4' );
		$adapter = new PGR_GravityView_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityview/template/field/date_created/output', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'gravityview/template/field/date_updated/output', $GLOBALS['pgr_test_filters'] );
		$this->assertCount( 2, $GLOBALS['pgr_test_filters'] );

		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravityview-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "HOST_PLUGIN_BASENAME = 'gravityview/gravityview.php'", $source );
		$this->assertStringContainsString( 'get_file_data(', $source );
		$this->assertStringContainsString( 'PGR_Jalali_Presentation::format_datetime', $source );
		$this->assertStringNotContainsString( "'3.3.4'", $source );
		$this->assertStringNotContainsString( 'wu008_gv_qualification_', $source );
		$this->assertStringNotContainsString( 'data-raw', $source );
		$this->assertStringNotContainsString( 'data-native', $source );
		$this->assertStringNotContainsString( '<span', $source );
		$this->assertStringNotContainsString( 'wp_date(', $source );
		$this->assertStringNotContainsString( 'preg_replace', $source );
	}

	private function bootstrap_adapter( string $host_version, bool $module_enabled = true, bool $load_facade = true ): void {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/pgr-gv-adapter-' . getmypid() );
		}
		if ( ! function_exists( 'is_admin' ) ) {
			function is_admin() {
				return ! empty( $GLOBALS['pgr_test_is_admin'] );
			}
		}
		if ( ! function_exists( 'get_file_data' ) ) {
			function get_file_data( $file, $headers, $context = '' ) {
				unset( $context );
				$content = (string) file_get_contents( $file );
				$result  = array();
				foreach ( $headers as $key => $label ) {
					$result[ $key ] = preg_match( '/^[\\t *\/#@]*' . preg_quote( $label, '/' ) . ':\\s*(.+)$/mi', $content, $match )
						? trim( $match[1] )
						: '';
				}
				return $result;
			}
		}

		$GLOBALS['pgr_test_is_admin'] = false;
		$GLOBALS['pgr_test_options']['pgr_modules'] = array(
			'schema_version' => 1,
			'states'         => array(
				'national_id'         => false,
				'jalali_date'         => false,
				'jalali_presentation' => $module_enabled,
				'iranian_address'     => false,
				'digit_normalization' => false,
				'iranian_currency'    => false,
				'structured_scanner'  => false,
			),
		);

		$this->write_host_plugin( $host_version );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		if ( $load_facade ) {
			require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
			require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravityview-jalali-presentation-adapter.php';
	}

	private function write_host_plugin( string $version ): void {
		$root = WP_PLUGIN_DIR . '/gravityview';
		if ( ! is_dir( $root ) ) {
			mkdir( $root, 0777, true );
		}
		file_put_contents(
			$root . '/gravityview.php',
			"<?php\n/**\n * Plugin Name: GravityView\n * Version: " . $version . "\n */\n"
		);
	}

	private function context( string $field_id, array $entry ): object {
		$field       = new stdClass();
		$field->type = $field_id;
		$field->ID   = $field_id;

		$entry_object = new class( $entry ) {
			private $entry;

			public function __construct( $entry ) {
				$this->entry = $entry;
			}

			public function as_entry() {
				return $this->entry;
			}
		};

		return (object) array(
			'field' => $field,
			'view'  => new stdClass(),
			'entry' => $entry_object,
		);
	}
}
