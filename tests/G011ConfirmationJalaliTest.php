<?php
/**
 * G-011 bounded unit controls. Licensed host behavior is tested by WU008.
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G011ConfirmationJalaliTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_system_dates_modifiers_and_native_boundaries(): void {
		$this->bootstrap();
		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$entry = array( 'date_created' => '2026-03-20 20:30:00', 'date_updated' => 'bad', 'payment_date' => '' );
		$this->assertSame( '۱۴۰۵/۰۱/۰۱', $this->tag( '{date_created:format:Y/m/d}', $entry ) );
		$this->assertSame( '۱۴۰۵-۰۱-۰۱ ۰۰:۰۰', $this->tag( '{date_created:format:Y-m-d H:i}', $entry ) );
		$this->assertSame( 'Y ۱۴۰۵', $this->tag( '{date_created:format:\Y Y}', $entry ) );
		$this->assertSame( '۱۴۰۵/۰۱/۰۱', $this->tag( '{date_created}', $entry ) );
		$this->assertNull( $this->tag( '{date_updated}', $entry ) );
		$this->assertNull( $this->tag( '{payment_date}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:raw}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:timestamp}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:human}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:diff}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:format:W}', $entry ) );
		$this->assertNull( $this->tag( '{entry:date_created}', $entry ) );
		$this->assertNull( $this->tag( '{date_created:format:Y-m-d}', array( 'date_created' => '2124-03-20 00:00:00' ) ) );
		$this->assertSame( $entry, array( 'date_created' => '2026-03-20 20:30:00', 'date_updated' => 'bad', 'payment_date' => '' ) );
	}

	#[RunInSeparateProcess]
	public function test_native_date_field_selectors_do_not_touch_jalali_or_text(): void {
		$this->bootstrap();
		$field = new GF_Field_Date();
		$field->id = 7;
		$field->dateFormat = 'mdy';
		$other = new GF_Field();
		$other->id = 8;
		$other->type = 'pgr_jalali_date';
		$form = array( 'fields' => array( $field, $other ) );
		$entry = array( '7' => '03/21/2026', '8' => '1405-01-01', '9' => '2026-03-21' );
		$this->assertSame( '۱۴۰۵', $this->tag( '{Visit:7:year}', $entry, $form ) );
		$this->assertSame( '۰۱', $this->tag( '{Visit:7:month}', $entry, $form ) );
		$this->assertSame( '۰۱', $this->tag( '{Visit:7:day}', $entry, $form ) );
		$this->assertSame( '۱۴۰۵-۰۱-۰۱', $this->tag( '{Visit:7:ymd_dash}', $entry, $form ) );
		$this->assertSame( '۰۱/۰۱/۱۴۰۵', $this->tag( '{Visit:7:dmy}', $entry, $form ) );
		$this->assertNull( $this->tag( '{Already Jalali:8}', $entry, $form ) );
		$this->assertNull( $this->tag( '{Text:9}', $entry, $form ) );
		$this->assertNull( $this->tag( '{Visit:7:raw}', $entry, $form ) );
		$this->assertNull( $this->tag( '{Visit:7:urlencode}', $entry, $form ) );
	}

	#[RunInSeparateProcess]
	public function test_confirmation_seam_never_reprocesses_redirect_or_english(): void {
		$this->bootstrap();
		$adapter = new PGR_GF_Confirmation_Jalali_Adapter();
		$adapter->hooks();
		$this->assertArrayHasKey( 'gform_confirmation', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_pre_replace_merge_tags', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_merge_tag_filter', $GLOBALS['pgr_test_filters'] );
		$form = array( 'confirmation' => array( 'type' => 'redirect', 'message' => '{date_created}' ) );
		$entry = array( 'date_created' => '2026-03-20 20:30:00' );
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$this->assertSame( array( 'redirect' => 'https://example.test/?d=x' ), PGR_GF_Confirmation_Jalali_Adapter::filter_confirmation( array( 'redirect' => 'https://example.test/?d=x' ), $form, $entry, false ) );
		$form['confirmation']['type'] = 'message';
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$this->assertSame( 'native', PGR_GF_Confirmation_Jalali_Adapter::filter_confirmation( 'native', $form, $entry, true ) );
	}

	private function tag( string $tag, array $entry, array $form = array() ): ?string {
		$method = new ReflectionMethod( 'PGR_GF_Confirmation_Jalali_Adapter', 'present_tag' );
		return $method->invoke( null, $tag, $form, $entry );
	}

	private function bootstrap(): void {
		if ( ! class_exists( 'GFCommon', false ) ) {
			eval( 'class GFCommon {
				public static function get_default_date_format() { return "Y/m/d"; }
				public static function get_default_time_format() { return "H:i"; }
				public static function get_local_timestamp($t) { return $t + wp_timezone()->getOffset(new DateTimeImmutable("@".$t)); }
				public static function parse_date($raw,$fmt) {
					if (!preg_match("~^(\d{1,4})[/.-](\d{1,2})[/.-](\d{1,4})$~",$raw,$m)) return array();
					if (strlen($m[1]) === 4) return array("year"=>$m[1],"month"=>$m[2],"day"=>$m[3]);
					if (substr($fmt,0,3) === "mdy") return array("year"=>$m[3],"month"=>$m[1],"day"=>$m[2]);
					return array("year"=>$m[3],"month"=>$m[2],"day"=>$m[1]);
				}
			}' );
		}
		if ( ! class_exists( 'GF_Field', false ) ) {
			eval( 'class GF_Field { public $id; public $type; }' );
		}
		if ( ! class_exists( 'GF_Field_Date', false ) ) {
			eval( 'class GF_Field_Date extends GF_Field { public $type = "date"; public $dateFormat = "mdy"; }' );
		}
		if ( ! function_exists( 'date_i18n' ) ) {
			eval( 'function date_i18n($fmt,$t,$gmt=false) { return gmdate($fmt,$t); }' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gf-confirmation-jalali-adapter.php';
	}
}
