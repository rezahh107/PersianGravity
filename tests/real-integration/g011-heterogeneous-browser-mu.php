<?php
/**
 * Disposable browser seam for the previously qualified heterogeneous
 * candidate. Copied only to WU008's disposable MU directory.
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/g011-heterogeneous-candidate.php';
final class WU008_G011_Heterogeneous_Browser {
	private static $old_priority = false;
	private static $trace = array();
	private static $shared = array();
	private static $mutation_count = 0;
	private static $native_input = null;
	private static $aligned = null;
	private static $selected = null;

	private static function target( $form ): bool {
		return is_array( $form ) &&
			(int) ( $form['id'] ?? 0 ) === (int) get_option( 'wu008_g011_het_browser_form_id', -1 );
	}
	public static function boot(): void {
		add_filter( 'gform_pre_replace_merge_tags', array( __CLASS__, 'shared_pre' ), 999, 2 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'enter' ), 1, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'third_party' ), 10, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'tie_before' ), 20, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'candidate' ), 20, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'tie_after' ), 20, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'after' ), 30, 4 );
	}
	public static function shared_pre( $text, $form ) {
		if ( self::target( $form ) ) {
			self::$shared[] = hash( 'sha256', (string) $text );
		}
		return $text;
	}
	public static function enter( $html, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) { return $html; }
		self::$trace = array( '1-enter' );
		self::$mutation_count = 0;
		self::$native_input = null;
		self::$aligned = null;
		self::$selected = $form['confirmation']['id'] ?? null;
		$hook = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
		self::$old_priority = has_filter( 'gform_confirmation', $hook );
		if ( 20 !== self::$old_priority ) {
			throw new RuntimeException( 'G011 hetero browser: production priority drift' );
		}
		remove_filter( 'gform_confirmation', $hook, self::$old_priority );
		return $html;
	}
	public static function third_party( $html, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) { return $html; }
		self::$trace[] = '10-third-party';
		self::$mutation_count++;
		return '<span class="external-prefix">THIRD_PARTY_VISIBLE</span>' . $html;
	}
	public static function tie_before( $html, $form, $entry, $ajax ) {
		if ( self::target( $form ) ) {
			self::$trace[] = '20-before';
			self::$native_input = $html;
		}
		return $html;
	}
	public static function candidate( $html, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) { return $html; }
		self::$trace[] = '20-candidate';
		self::$aligned = WU008_G011_Heterogeneous_Alignment::align( $html, $form, $entry );
		return self::$aligned['output'];
	}
	public static function tie_after( $html, $form, $entry, $ajax ) {
		if ( self::target( $form ) ) {
			self::$trace[] = '20-after';
			self::$trace[] = str_contains( (string) $html, '۱۴۰۵/' ) ? '20-after-jalali' : '20-after-other';
		}
		return $html;
	}
	public static function after( $html, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) { return $html; }
		try {
			self::$trace[] = '30-post';
			$raw = $entry['date_created'] ?? null;
			$source = is_string( $raw ) ? DateTimeImmutable::createFromFormat(
				'!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' )
			) : false;
			if ( ! $source instanceof DateTimeImmutable ||
				$source->format( 'Y-m-d H:i:s' ) !== $raw ) {
				throw new RuntimeException( 'G011 browser: invalid UTC Entry' );
			}
			$local = $source->setTimezone( wp_timezone() );
			$jalali = PGR_Jalali_Presentation::format_date(
				(int) $local->format( 'Y' ),
				(int) $local->format( 'n' ),
				(int) $local->format( 'j' )
			);
			$spans = self::$aligned['spans'] ?? array();
			$expected = '';
			$cursor = 0;
			foreach ( $spans as $span ) {
				$expected .= substr( self::$native_input, $cursor, $span['start'] - $cursor )
					. esc_html( $span['jalali'] );
				$cursor = $span['end'];
			}
			$expected .= substr( self::$native_input, $cursor );
			$stored = GFAPI::get_entry( (int) $entry['id'] );
			$checks = array(
				'host_version' => GFForms::$version === '3.1.1.1',
				'module_and_locale' => PGR_Module_Registry::is_enabled( 'jalali_presentation' ) &&
					determine_locale() === 'fa_IR',
				'selected_text' => ( $form['confirmation']['type'] ?? null ) === 'message',
				'three_distinct_spans' => is_array( $spans ) && count( $spans ) === 3 &&
					count( array_unique( array_column( $spans, 'native' ) ) ) === 3,
				'byte_exact_unowned' => is_string( self::$native_input ) && $expected === $html,
				'single_shared_processing' => count( self::$shared ) === 1,
				'third_party_once' => self::$mutation_count === 1,
				'tie_order' => self::$trace === array(
					'1-enter', '10-third-party', '20-before', '20-candidate',
					'20-after', '20-after-jalali', '30-post',
				),
				'entry_business_values' => is_array( $stored ) &&
					(string) $stored['date_created'] === (string) $entry['date_created'] &&
					(string) $stored['form_id'] === (string) $entry['form_id'] &&
					(string) $stored['1'] === (string) $entry['1'] &&
					(string) $stored['2'] === (string) $entry['2'],
			);
			$case = (string) ( $entry['1'] ?? '' );
			$doc = array(
				'head' => getenv( 'WU008_PGR_SHA' ),
				'host' => GFForms::$version,
				'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
				'entry_id' => (int) $entry['id'],
				'form_id' => (int) $entry['form_id'],
				'selected_confirmation' => self::$selected,
				'ajax' => (bool) $ajax,
				'raw_utc' => $raw,
				'wp_timezone' => wp_timezone_string(),
				'local_gregorian' => $local->format( 'Y/m/d' ),
				'expected_jalali' => $jalali,
				'field_2' => $entry['2'],
				'candidate_reason' => self::$aligned['reason'] ?? null,
				'candidate_admitted' => self::$aligned['admitted'] ?? false,
				'spans' => $spans,
				'callback_trace' => self::$trace,
				'shared_merge_callback_count' => count( self::$shared ),
				'third_party_mutations' => self::$mutation_count,
				'native_before_sha256' => hash( 'sha256', (string) self::$native_input ),
				'candidate_output_sha256' => hash( 'sha256', (string) $html ),
				'checks' => $checks,
				'failed_checks' => array_keys( array_filter(
					$checks, static function ( $ok ) { return ! $ok; }
				) ),
			);
			$dir = getenv( 'WU008_ARTIFACT_DIR' );
			file_put_contents( $dir . '/g011-hetero-browser-' . sanitize_file_name( $case ) . '.json',
				wp_json_encode( $doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			if ( $doc['failed_checks'] ) {
				throw new RuntimeException( 'G011 hetero browser: ' . implode( ',', $doc['failed_checks'] ) );
			}
		} finally {
			$hook = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
			if ( false !== self::$old_priority ) {
				add_filter( 'gform_confirmation', $hook, self::$old_priority, 4 );
			}
			self::$old_priority = false;
		}
		return $html;
	}
}
WU008_G011_Heterogeneous_Browser::boot();