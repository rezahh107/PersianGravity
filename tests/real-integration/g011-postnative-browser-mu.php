<?php
/**
 * TEST-ONLY G-011 browser-boundary probe. Installed as MU plugin only in WU008.
 * No production plugin content, author markers or new Merge Tags.
 */
defined( 'ABSPATH' ) || exit;
final class WU008_G011_Browser_Alignment {
	private static $state = null;
	private static $removed_priority = false;
	private static $pre_trace = array();
	private static $side_effect_calls = 0;

	public static function boot(): void {
		add_filter( 'gform_pre_replace_merge_tags', array( __CLASS__, 'third_party_pre' ), 2, 2 );
		add_filter( 'gform_pre_replace_merge_tags', array( __CLASS__, 'observe_pre' ), 999, 2 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'enter' ), 1, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'third_party_post' ), 5, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'capture_native' ), 24, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'candidate' ), 25, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'record_and_restore' ), 30, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'submission_trace' ), 999, 2 );
	}
	private static function config(): array {
		$cfg = get_option( 'wu008_g011_browser_qual_cfg', array() );
		return is_array( $cfg ) ? $cfg : array();
	}
	private static function target( $form ): bool {
		$cfg = self::config();
		return is_array( $form ) && isset( $form['id'] ) &&
			(int) $form['id'] === (int) ( $cfg['form_id'] ?? -1 );
	}
	private static function mode( $entry = null ): string {
		if ( is_array( $entry ) && is_string( $entry['2'] ?? null ) ) {
			return sanitize_key( $entry['2'] );
		}
		$posted = $_POST['input_2'] ?? '';
		return is_string( $posted ) ? sanitize_key( wp_unslash( $posted ) ) : '';
	}
	public static function submission_trace( $entry, $form ): void {
		if ( ! self::target( $form ) || ! is_array( $entry ) ) {
			return;
		}
		$mode = self::mode( $entry );
		$dir = getenv( 'WU008_ARTIFACT_DIR' );
		if ( ! is_string( $dir ) || ! is_dir( $dir ) ) {
			return;
		}
		$details = array(
			'mode' => $mode, 'entry_id' => (int) ( $entry['id'] ?? 0 ),
			'form_id' => (int) ( $form['id'] ?? 0 ),
			'confirmation_trace_exists' =>
				is_file( $dir . '/g011-browserqual-' . sanitize_file_name( $mode ) . '.json' ),
			'hook_registered' =>
				false !== has_filter( 'gform_confirmation', array( __CLASS__, 'enter' ) ),
			'locale' => determine_locale(),
			'module_enabled' => PGR_Module_Registry::is_enabled( 'jalali_presentation' ),
			'pre_callback_calls' => self::$side_effect_calls,
			'expected' => self::expected_from_entry( $entry ),
			'persisted_form_sha256' => hash( 'sha256', wp_json_encode(
				GFAPI::get_form( (int) $form['id'] ) ) ),
			'persisted_entry_sha256' => hash( 'sha256', wp_json_encode(
				GFAPI::get_entry( (int) $entry['id'] ) ) ),
		);
		file_put_contents( $dir . '/g011-browserqual-submitted-' . sanitize_file_name( $mode ) . '.json',
			wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
	public static function third_party_pre( $text, $form ) {
		if ( ! self::target( $form ) || ! is_string( $text ) ) {
			return $text;
		}
		self::$side_effect_calls++;
		$mode = self::mode();
		if ( str_contains( $mode, 'changed_date' ) ) {
			return str_replace( '{date_created:format:Y/m/d}', '2031/11/30', $text );
		}
		if ( str_contains( $mode, 'changed_html' ) ) {
			return str_replace( 'Checked at <em>', '<i>Checked at</i> <em>', $text );
		}
		return $text;
	}
	public static function observe_pre( $text, $form ) {
		if ( self::target( $form ) ) {
			self::$pre_trace[] = array(
				'sha256' => hash( 'sha256', (string) $text ),
				'length' => strlen( (string) $text ),
			);
		}
		return $text;
	}
	public static function enter( $output, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) {
			return $output;
		}
		self::$state = null;
		self::$pre_trace = array_values( self::$pre_trace );
		$adapter = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
		$priority = has_filter( 'gform_confirmation', $adapter );
		self::$removed_priority = $priority;
		if ( false !== $priority ) {
			remove_filter( 'gform_confirmation', $adapter, $priority );
		}
		return $output;
	}
	public static function third_party_post( $output, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) || ! is_string( $output ) ||
			! str_contains( self::mode( $entry ), 'ambiguous' ) ) {
			return $output;
		}
		$needle = '<p class="human-a">Submitted on <strong>';
		$replacement = '<p class="human-a">Submitted on <strong>2031/11/30</strong></p>' . $needle;
		return str_replace( $needle, $replacement, $output );
	}
	private static function expected_from_entry( array $entry ): array {
		$raw = $entry['date_created'] ?? '';
		$utc = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' )
		);
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $utc ||
			( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ||
			$utc->format( 'Y-m-d H:i:s' ) !== $raw ) {
			throw new RuntimeException( 'G011 browser: invalid authoritative UTC Entry date' );
		}
		$local = $utc->setTimezone( wp_timezone() );
		return array(
			'raw_utc' => $raw,
			'gregorian' => $local->format( 'Y/m/d' ),
			'jalali' => PGR_Jalali_Presentation::format_date(
				(int) $local->format( 'Y' ),
				(int) $local->format( 'n' ),
				(int) $local->format( 'j' )
			),
			'timezone' => wp_timezone_string(),
		);
	}
	public static function capture_native( $output, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) {
			return $output;
		}
		$cfg = self::config();
		$id = (int) ( $entry['id'] ?? 0 );
		$persisted_form = GFAPI::get_form( (int) $form['id'] );
		$persisted_entry = GFAPI::get_entry( $id );
		self::$state = array(
			'mode' => self::mode( $entry ),
			'form_id' => (int) $form['id'],
			'entry_id' => $id,
			'selected_id' => (string) ( $form['confirmation']['id'] ?? '' ),
			'default_selected' =>
				(string) ( $form['confirmation']['id'] ?? '' ) === (string) ( $cfg['default_id'] ?? '' ),
			'selected_type' => (string) ( $form['confirmation']['type'] ?? '' ),
			'ajax' => (bool) $ajax,
			'native' => $output,
			'expected' => self::expected_from_entry( $entry ),
			'pre_trace' => self::$pre_trace,
			'third_party_pre_side_effect_calls' => self::$side_effect_calls,
			'persisted_form_sha256' => hash( 'sha256', wp_json_encode( $persisted_form ) ),
			'persisted_entry_sha256' => hash( 'sha256', wp_json_encode( $persisted_entry ) ),
			'candidate' => null,
		);
		return $output;
	}
	private static function qualify( $native, $selected_form, $entry ): array {
		$source = self::expected_from_entry( $entry );
		$raw_date = $entry['date_created'];
		$gregorian = $source['gregorian'];
		$jalali = $source['jalali'];
		$tag = '{date_created:format:Y/m/d}';
		// The following exact classifier and aligner are reused verbatim from
		// g011-multi-occurrence-alignment-qualification.php at PR head
		// c7030f8491ef732b9f7cdc54925fd3473944e9ce.
$classify = static function ( string $raw ) use ( $tag ): array {
	preg_match_all( '/' . preg_quote( $tag, '/' ) . '/', $raw, $matches, PREG_OFFSET_CAPTURE );
	$eligible = array();
	foreach ( $matches[0] as $hit ) {
		$offset = $hit[1];
		$before = substr( $raw, 0, $offset );
		$lt = strrpos( $before, '<' );
		$gt = strrpos( $before, '>' );
		if ( false !== $lt && ( false === $gt || $lt > $gt ) ) {
			continue;
		}
		$start = false === $gt ? 0 : $gt + 1;
		$left = substr( $raw, $start, $offset - $start );
		$next = strpos( $raw, '<', $offset + strlen( $tag ) );
		$right = substr( $raw, $offset + strlen( $tag ),
			( false === $next ? strlen( $raw ) : $next ) - $offset - strlen( $tag ) );
		$left_token = preg_replace( '/^.*\s/s', '', $left );
		$right_token = preg_replace( '/\s.*$/s', '', $right );
		if ( preg_match( '/[\/._-]$/', $left_token ) ||
			preg_match( '/^[\/._#?]/', $right_token ) ||
			preg_match( '/(?:https?:|www\.)/i', $left_token ) ) {
			continue;
		}
		$eligible[] = $offset;
	}
	return array( 'eligible' => $eligible, 'all' => count( $matches[0] ) );
};

// Candidate uses only original selected template + rendered output + raw Entry.
// Every eligible tag must map to one uniquely bracketed Gregorian output span.
// A missing/duplicated/reordered/overlapping span rejects the WHOLE candidate.
$align = static function ( $native, $selected_form, $given_entry ) use (
	$classify, $raw_date, $gregorian, $jalali, $tag
): array {
	$base = array( 'admitted' => false, 'output' => $native, 'spans' => array() );
	$confirmation = $selected_form['confirmation'] ?? null;
	if ( ! is_string( $native ) || ! is_array( $confirmation ) ||
		'message' !== ( $confirmation['type'] ?? null ) ||
		! is_string( $confirmation['message'] ?? null ) ||
		( $given_entry['date_created'] ?? null ) !== $raw_date ) {
		return $base + array( 'reason' => 'missing_selected_ownership' );
	}
	$raw = $confirmation['message'];
	$classified = $classify( $raw );
	if ( 7 !== $classified['all'] || 2 !== count( $classified['eligible'] ) ) {
		return $base + array( 'reason' => 'incomplete_occurrence_classifier' );
	}
	$spans = array();
	$previous_end = 0;
	foreach ( $classified['eligible'] as $template_offset ) {
		$left = substr( $raw, max( 0, $template_offset - 34 ), min( 34, $template_offset ) );
		$right = substr( $raw, $template_offset + strlen( $tag ), 31 );
		if ( strlen( $left ) < 12 || strlen( $right ) < 12 ||
			substr_count( $native, $left ) !== 1 || substr_count( $native, $right ) !== 1 ) {
			return $base + array( 'reason' => 'missing_or_nonunique_anchors' );
		}
		$left_at = strpos( $native, $left );
		$start = $left_at + strlen( $left );
		$right_at = strpos( $native, $right );
		if ( false === $right_at || $right_at < $start ||
			substr( $native, $start, $right_at - $start ) !== $gregorian ) {
			return $base + array( 'reason' => 'changed_value_or_crossing_span' );
		}
		$end = $right_at;
		if ( $start < $previous_end || $end < $start ) {
			return $base + array( 'reason' => 'reordered_or_overlapping_spans' );
		}
		$spans[] = array( 'template_offset' => $template_offset, 'start' => $start, 'end' => $end );
		$previous_end = $end;
	}
	if ( 2 !== count( $spans ) ) {
		return $base + array( 'reason' => 'incomplete_mapping' );
	}
	// Immutable-slice reconstruction; every byte outside exactly these two
	// output spans is copied unchanged in original order.
	$output = '';
	$cursor = 0;
	foreach ( $spans as $span ) {
		$output .= substr( $native, $cursor, $span['start'] - $cursor ) . esc_html( $jalali );
		$cursor = $span['end'];
	}
	$output .= substr( $native, $cursor );
	return array( 'admitted' => true, 'output' => $output, 'spans' => $spans,
		'reason' => 'two_unique_ordered_nonoverlapping_spans' );
};



		return $align( $native, $selected_form, $entry );
	}
	public static function candidate( $output, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) || ! is_array( self::$state ) ) {
			return $output;
		}
		$cfg = self::config();
		$mode = self::mode( $entry );
		$active = 'message' === ( $form['confirmation']['type'] ?? '' ) &&
			(string) ( $form['confirmation']['id'] ?? '' ) ===
			(string) ( $cfg['default_id'] ?? '' ) &&
			'fa_IR' === determine_locale() &&
			PGR_Module_Registry::is_enabled( 'jalali_presentation' ) &&
			! str_starts_with( $mode, 'native_' ) && 'conditional' !== $mode;
		self::$state['candidate_eligible'] = $active;
		if ( ! $active ) {
			self::$state['candidate'] = array(
				'admitted' => false, 'output' => $output, 'spans' => array(),
				'reason' => 'native_control_or_ineligible',
			);
			return $output;
		}
		$result = self::qualify( $output, $form, $entry );
		self::$state['candidate'] = $result;
		return $result['output'];
	}
	public static function record_and_restore( $output, $form, $entry, $ajax ) {
		if ( ! self::target( $form ) ) {
			return $output;
		}
		try {
			if ( ! is_array( self::$state ) ) {
				throw new RuntimeException( 'G011: native capture missing' );
			}
			$s = self::$state;
			$base = $s['native'];
			$result = $s['candidate'];
			$spans = is_array( $result['spans'] ?? null ) ? $result['spans'] : array();
			$expected_j = esc_html( $s['expected']['jalali'] );
			$rebuilt = '';
			$cursor = 0;
			foreach ( $spans as $span ) {
				if ( $span['start'] < $cursor ) {
					throw new RuntimeException( 'G011 browser: unordered admitted span' );
				}
				$rebuilt .= substr( $base, $cursor, $span['start'] - $cursor ) . $expected_j;
				$cursor = $span['end'];
			}
			$rebuilt .= substr( $base, $cursor );
			$doc = array(
				'head' => getenv( 'WU008_PGR_SHA' ),
				'host_version' => GFForms::$version,
				'host_sha256' => getenv( 'WU008_GF_SHA256' ),
				'mode' => $s['mode'], 'form_id' => $s['form_id'],
				'entry_id' => $s['entry_id'],
				'selected_id' => $s['selected_id'],
				'default_selected' => $s['default_selected'],
				'selected_type' => $s['selected_type'],
				'ajax' => $s['ajax'],
				'utc' => $s['expected']['raw_utc'],
				'timezone' => $s['expected']['timezone'],
				'gregorian' => $s['expected']['gregorian'],
				'jalali' => $s['expected']['jalali'],
				'native_sha256' => hash( 'sha256', (string) $base ),
				'output_sha256' => hash( 'sha256', (string) $output ),
				'candidate_eligible' => $s['candidate_eligible'] ?? false,
				'admitted' => $result['admitted'] ?? false,
				'reason' => $result['reason'] ?? '',
				'spans' => $spans,
				'native_callback_count' => count( $s['pre_trace'] ),
				'native_callback_inputs' => $s['pre_trace'],
				'third_party_pre_side_effect_calls' => $s['third_party_pre_side_effect_calls'],
				'outside_spans_byte_exact' => $rebuilt === $output,
				'native_fallback_byte_exact' => $base === $output,
				'form_snapshot_sha256' => $s['persisted_form_sha256'],
				'entry_snapshot_sha256' => $s['persisted_entry_sha256'],
				'persisted_form_same_in_callback' =>
					$s['persisted_form_sha256'] === hash( 'sha256', wp_json_encode(
						GFAPI::get_form( $s['form_id'] ) ) ),
				'persisted_entry_same_in_callback' =>
					$s['persisted_entry_sha256'] === hash( 'sha256', wp_json_encode(
						GFAPI::get_entry( $s['entry_id'] ) ) ),
			);
			$dir = getenv( 'WU008_ARTIFACT_DIR' );
			if ( ! is_dir( $dir ) ) {
				throw new RuntimeException( 'G011 browser: missing artifacts directory' );
			}
			$path = $dir . '/g011-browserqual-' . sanitize_file_name( $s['mode'] ) . '.json';
			file_put_contents( $path, wp_json_encode(
				$doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) );
		} finally {
			$adapter = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
			if ( false !== self::$removed_priority ) {
				add_filter( 'gform_confirmation', $adapter, self::$removed_priority, 4 );
			}
			self::$removed_priority = false;
		}
		return $output;
	}
}
add_action( 'init', array( 'WU008_G011_Browser_Alignment', 'boot' ), 1 );
