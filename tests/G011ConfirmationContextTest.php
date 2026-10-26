<?php
/**
 * G-011 context admission/falsification: mocked host oracle, not licensed GF.
 *
 * @package PersianGravityForms
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G011ConfirmationContextTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_identical_tag_is_converted_only_in_human_visible_occurrences(): void {
		$this->bootstrap_context();
		$tag = '{date_created:format:Y/m/d}';
		$template = '<div class="keep"><a href="/?date=' . $tag . '&amp;keep=1" data-day="' . $tag .
			'"><strong>Shown ' . $tag . '</strong></a> and ' . $tag . ' <em>unchanged markup</em></div>';
		$native = $this->native( $template );
		$result = $this->present( $template );

		$this->assertSame(
			'<div class="keep"><a href="/?date=2026/03/21&amp;keep=1" data-day="2026/03/21"><strong>Shown ۱۴۰۵/۰۱/۰۱</strong></a> and ۱۴۰۵/۰۱/۰۱ <em>unchanged markup</em></div>',
			$result
		);
		$this->assertSame( '2026/03/21', $this->attribute( $result, 'data-day' ) );
		$this->assertSame( '/?date=2026/03/21&amp;keep=1', $this->attribute( $result, 'href' ) );
		$this->assertSame( $this->attribute( $native, 'href' ), $this->attribute( $result, 'href' ) );
		$this->assertSame( $this->attribute( $native, 'data-day' ), $this->attribute( $result, 'data-day' ) );

		// This is the exact defect class of the old global strtr(markerization):
		// every occurrence, including href/data-*, would have been converted.
		$global_substitution = str_replace( '2026/03/21', '۱۴۰۵/۰۱/۰۱', $native );
		$this->assertNotSame( $this->attribute( $native, 'href' ), $this->attribute( $global_substitution, 'href' ) );
		$this->assertNotSame( $result, $global_substitution );
	}

	#[RunInSeparateProcess]
	public function test_url_script_style_code_comment_and_hidden_content_remain_native(): void {
		$this->bootstrap_context();
		$tag = '{date_created:format:Y/m/d}';
		$template = 'Date ' . $tag . ' https://example.test/?day=' . $tag .
			'&id=1 <script>if (a < b) { const date="' . $tag . '"; }</script>' .
			'<style>.x::after {content:"' . $tag . '";}</style><code>' . $tag . '</code>' .
			'<pre>' . $tag . '</pre><!-- ' . $tag . ' -->' .
			'<span hidden>' . $tag . '</span><span aria-hidden="true">' . $tag . '</span>' .
			'<span style="display:none">' . $tag . '</span><custom-tag>' . $tag . '</custom-tag>' .
			'<span>Human ' . $tag . '</span> {"created":"' . $tag . '"} Human ' . $tag;
		$expected = str_replace( array( 'Date 2026/03/21', 'Human 2026/03/21' ), array( 'Date ۱۴۰۵/۰۱/۰۱', 'Human ۱۴۰۵/۰۱/۰۱' ), $this->native( $template ) );
		$this->assertSame( $expected, $this->present( $template ) );
		$this->assertStringContainsString( 'https://example.test/?day=2026/03/21&id=1', $expected );
	}

	#[RunInSeparateProcess]
	public function test_ambiguous_markup_and_native_roundtrip_drift_fail_closed(): void {
		$this->bootstrap_context();
		$tag = '{date_created:format:Y/m/d}';
		foreach ( array(
			'<a href="' . $tag . '><strong>Visible ' . $tag . '</strong>',
			'<div><span>Visible ' . $tag . '</div>',
			'<script/>Visible ' . $tag,
			'Prefix <not a tag Visible ' . $tag,
		) as $malformed ) {
			$this->assertSame( $this->native( $malformed ), $this->present( $malformed ) );
		}
		$valid = '<p>Visible ' . $tag . '</p>';
		$GLOBALS['pgr_g011_test_render_drift'] = '<!-- a host callback mutated markup -->';
		$this->assertSame( $this->native( $valid ), $this->present( $valid ) );
	}

	private function native( string $template ): string {
		return GFCommon::replace_variables( $template );
	}

	private function present( string $template ): string {
		$form  = array( 'confirmation' => array( 'type' => 'message', 'message' => $template, 'disableAutoformat' => true ), 'fields' => array() );
		$entry = array( 'date_created' => '2026-03-20 20:30:00' );
		return PGR_GF_Confirmation_Jalali_Adapter::filter_confirmation( $this->native( $template ), $form, $entry, false );
	}

	private function attribute( string $html, string $name ): string {
		$this->assertSame( 1, preg_match( '/\b' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html, $match ) );
		return $match[1];
	}

	private function bootstrap_context(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$GLOBALS['pgr_test_options']['pgr_modules'] = array( 'schema_version' => 1, 'states' => array( 'jalali_presentation' => true ) );
		$GLOBALS['pgr_g011_test_render_drift'] = '';
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			eval( 'function wp_generate_uuid4() { static $i=0; return sprintf("00000000-0000-4000-8000-%012d",++$i); }' );
		}
		if ( ! class_exists( 'GFCommon', false ) ) {
			eval( 'class GFCommon {
				public static function replace_variables($text, ...$unused) {
					return str_replace("{date_created:format:Y/m/d}", "2026/03/21", $text);
				}
				public static function get_default_date_format() { return "Y/m/d"; }
				public static function get_local_timestamp($time) { return $time + 12600; }
			}' );
		}
		if ( ! class_exists( 'GFFormDisplay', false ) ) {
			eval( 'class GFFormDisplay {
				public static function get_confirmation_message($selected, $form, $entry) {
					return GFCommon::replace_variables($selected["message"]) . $GLOBALS["pgr_g011_test_render_drift"];
				}
			}' );
		}
		if ( ! function_exists( 'date_i18n' ) ) {
			eval( 'function date_i18n($format,$time,$gmt=false) { return gmdate($format,$time); }' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gf-confirmation-jalali-adapter.php';
	}
}
