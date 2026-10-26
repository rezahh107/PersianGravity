<?php
/**
 * Disposable G-011 two-occurrence alignment qualification (NOT production).
 * Existing Gravity Forms render/callback ownership stays untouched.
 */
defined( 'ABSPATH' ) || exit;
$fail = static function ( string $why ): void {
	throw new RuntimeException( 'G011_MULTI_ALIGN: ' . $why );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$manifest = json_decode( (string) file_get_contents( $dir . '/g011-confirmation.json' ), true );
if ( ! is_array( $manifest ) || '3.1.1.1' !== GFForms::$version ||
	getenv( 'WU008_PGR_SHA' ) !== ( $manifest['head'] ?? null ) ||
	'fa_IR' !== determine_locale() ||
	'542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' )
) {
	$fail( 'Exact licensed host, package, PR Head or locale mismatch.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
$entry_id = GFAPI::add_entry( array(
	'form_id' => (int) $manifest['form_id'], 'status' => 'active',
	'date_created' => '2026-03-20 20:30:00', '1' => 'G011 two human occurrences',
) );
$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
if ( ! is_array( $form ) || ! is_array( $entry ) ) {
	$fail( 'Unable to retrieve real GF form and disposable Entry.' );
}
$raw_date = $entry['date_created'] ?? null;
$utc = is_string( $raw_date ) ?
	DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw_date, new DateTimeZone( 'UTC' ) ) : false;
$errors = DateTimeImmutable::getLastErrors();
if ( false === $utc || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ||
	$utc->format( 'Y-m-d H:i:s' ) !== $raw_date ) {
	$fail( 'Authoritative G-008 UTC Entry date is invalid.' );
}
$local = $utc->setTimezone( wp_timezone() );
$gregorian = $local->format( 'Y/m/d' );
$jalali = PGR_Jalali_Presentation::format_date(
	(int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' )
);
if ( ! is_string( $jalali ) || $gregorian === $jalali ) {
	$fail( 'Date value not admitted by G-008 facade.' );
}
$tag = '{date_created:format:Y/m/d}';
$template = '<div class="multi-g011" data-day="' . $tag . '">' .
	'<p class="human-a">Submitted on <strong>' . $tag .
	'</strong> (fixed G=' . $gregorian . ').</p>' .
	'<p class="machine">receipt-' . $tag . '.tar.gz report_' . $tag .
	'-v2.pdf ../record/' . $tag . '/detail</p>' .
	'<a href="/download/' . $tag . '?keep=1">File</a>' .
	'<p class="human-b">Checked at <em>' . $tag .
	'</em> (fixed G=' . $gregorian . ').</p></div>';
$selected = array(
	'id' => 'g011-multi-qual', 'name' => 'G011 Two Occurrences',
	'type' => 'message', 'isDefault' => true, 'isActive' => true,
	'disableAutoformat' => true, 'message' => $template,
);
$test_form = $form;
$test_form['confirmations'] = array( 'g011-multi-qual' => $selected );
$form_hash = hash( 'sha256', wp_json_encode( $form ) );
$entry_hash = hash( 'sha256', wp_json_encode( $entry ) );

// Deliberately bounded scanner: text-node vs tag and immediately attached
// path/filename syntax. Do not treat this as a general HTML parser.
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
		$left = substr( $raw, max( 0, $template_offset - 44 ), min( 44, $template_offset ) );
		$right = substr( $raw, $template_offset + strlen( $tag ), 44 );
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

// No candidate hook is installed before the shared native rendering filters.
$original_options = get_option( 'pgr_modules' );
$production_hook = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
$priority = has_filter( 'gform_confirmation', $production_hook );
if ( ! is_array( $original_options ) || ! isset( $original_options['states'] ) ||
	false === $priority ) {
	$fail( 'Cannot isolate production adapter or module state.' );
}
remove_filter( 'gform_confirmation', $production_hook, $priority );
$enabled = $original_options;
$enabled['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $enabled );

$run = static function ( string $mode, bool $candidate ) use (
	$test_form, $entry, $tag, $gregorian, $align
): array {
	$traces = array();
	$pre = static function ( $text ) use ( $mode, $tag, $gregorian ) {
		if ( 'equivalent' === $mode ) {
			return str_replace( $tag, $gregorian, $text );
		}
		if ( 'different' === $mode ) {
			return str_replace( $tag, '2031/11/30', $text );
		}
		if ( 'changed_anchor' === $mode ) {
			return str_replace( 'Checked at <em>', '<i>Checked at</i> <em>', $text );
		}
		if ( 'duplicate_anchor' === $mode ) {
			$needle = '<p class="human-a">Submitted on <strong>';
			return str_replace( $needle, $needle . $gregorian .
				'</strong> (fixed G=' . $gregorian . ').</p>' . $needle, $text );
		}
		return $text;
	};
	$observe = static function ( $text, $observed_form ) use ( &$traces ) {
		$traces[] = array( 'hash' => hash( 'sha256', (string) $text ),
			'length' => strlen( (string) $text ),
			'type' => $observed_form['confirmation']['type'] ?? null );
		return $text;
	};
	$mutate_post = static function ( $native ) use ( $mode, $gregorian ) {
		if ( ! is_string( $native ) ) {
			return $native;
		}
		$a = '~(<p class="human-a">.*?</p>)(.*?)(<p class="human-b">.*?</p>)~s';
		if ( 'reordered' === $mode ) {
			return preg_replace( $a, '$3$2$1', $native, 1 );
		}
		if ( 'removed' === $mode ) {
			return preg_replace( '~<p class="human-b">.*?</p>~s', '', $native, 1 );
		}
		if ( 'overlap_attempt' === $mode ) {
			// Move the first human's suffix beyond the second occurrence,
			// forcing a crossing/invalid candidate interval.
			$first_suffix = '</strong> (fixed G=' . $gregorian . ').</p>';
			return str_replace( $first_suffix, '</strong>', $native ) .
				$first_suffix;
		}
		return $native;
	};
	$observed = null;
	$candidate_hook = static function ( $native, $form, $entry ) use ( &$observed, $align ) {
		$observed = $align( $native, $form, $entry );
		return $observed['output'];
	};
	add_filter( 'gform_pre_replace_merge_tags', $pre, 1 );
	add_filter( 'gform_pre_replace_merge_tags', $observe, 999, 2 );
	add_filter( 'gform_confirmation', $mutate_post, 5, 1 );
	if ( $candidate ) {
		add_filter( 'gform_confirmation', $candidate_hook, 25, 3 );
	}
	try {
		$f = $test_form;
		$output = GFFormDisplay::handle_confirmation( $f, $entry, false );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $pre, 1 );
		remove_filter( 'gform_pre_replace_merge_tags', $observe, 999 );
		remove_filter( 'gform_confirmation', $mutate_post, 5 );
		if ( $candidate ) {
			remove_filter( 'gform_confirmation', $candidate_hook, 25 );
		}
	}
	return array( 'output' => $output, 'trace' => $traces,
		'admitted' => $observed['admitted'] ?? null,
		'spans' => $observed['spans'] ?? array(),
		'reason' => $observed['reason'] ?? null );
};

$cases = array();
try {
	// Independent oracle fixed from template knowledge, before ANY candidate
	// execution. The two human labels are separate and machine tokens retain
	// the same native date string.
	$expect_native = array(
		'Submitted on <strong>' . $gregorian . '</strong>',
		'Checked at <em>' . $gregorian . '</em>',
	);
	$expect_jalali = array(
		'Submitted on <strong>' . esc_html( $jalali ) . '</strong>',
		'Checked at <em>' . esc_html( $jalali ) . '</em>',
	);
	$machine = array(
		'data-day="' . $gregorian . '"',
		'fixed G=' . $gregorian,
		'receipt-' . $gregorian . '.tar.gz',
		'report_' . $gregorian . '-v2.pdf',
		'../record/' . $gregorian . '/detail',
		'href="/download/' . $gregorian . '?keep=1"',
	);
	foreach ( array(
		'normal', 'equivalent', 'different', 'changed_anchor',
		'duplicate_anchor', 'reordered', 'removed', 'overlap_attempt',
	) as $mode ) {
		$native = $run( $mode, false );
		// Independent expected bytes built solely from matched native control.
		$expected = $native['output'];
		$positive = 'normal' === $mode || 'equivalent' === $mode;
		if ( $positive ) {
			foreach ( $expect_native as $i => $needle ) {
				if ( substr_count( $expected, $needle ) !== 1 ) {
					$fail( 'Native oracle did not uniquely contain human label ' . $i );
				}
				$expected = str_replace( $needle, $expect_jalali[$i], $expected );
			}
		}
		$candidate = $run( $mode, true );
		$counts = count( $native['trace'] ) === 1 &&
			count( $candidate['trace'] ) === 1 &&
			$native['trace'] === $candidate['trace'];
		$checks = array(
			'callback_count_and_input_identity' => $counts,
			'exact_oracle_output' => $candidate['output'] === $expected,
			'expected_admission' => $candidate['admitted'] === $positive,
			'positive_liveness_or_native_fallback' =>
				( $candidate['output'] !== $native['output'] ) === $positive,
			'complete_mapping' => $positive ? count( $candidate['spans'] ) === 2 :
				count( $candidate['spans'] ) === 0,
		);
		if ( $positive ) {
			foreach ( $machine as $i => $part ) {
				$checks['machine_' . $i] =
					substr_count( $native['output'], $part ) ===
					substr_count( $candidate['output'], $part );
			}
			// Byte-exact guard against changes outside both admitted spans.
			$spans = $candidate['spans'];
			if ( count( $spans ) === 2 ) {
				$unchanged = substr( $native['output'], 0, $spans[0]['start'] ) ===
					substr( $candidate['output'], 0, $spans[0]['start'] );
				$cursor_native = $spans[0]['end'];
				$cursor_presented = $spans[0]['start'] + strlen( esc_html( $jalali ) );
				$middle_length = $spans[1]['start'] - $cursor_native;
				$unchanged = $unchanged && $middle_length >= 0 &&
					substr( $native['output'], $cursor_native, $middle_length ) ===
					substr( $candidate['output'], $cursor_presented, $middle_length );
				$presented_second_end = $cursor_presented + $middle_length + strlen( esc_html( $jalali ) );
				$unchanged = $unchanged &&
					substr( $native['output'], $spans[1]['end'] ) ===
					substr( $candidate['output'], $presented_second_end );
				$checks['outside_spans_byte_exact'] = $unchanged;
				$checks['ordered_nonoverlapping'] =
					$spans[0]['start'] < $spans[0]['end'] &&
					$spans[0]['end'] <= $spans[1]['start'] &&
					$spans[1]['start'] < $spans[1]['end'];
			} else {
				$checks['outside_spans_byte_exact'] = false;
			}
		}
		$bad = array_keys( array_filter( $checks, static function ( $v ) { return ! $v; } ) );
		$cases[$mode] = array(
			'status' => empty( $bad ) ? 'PASS' : 'FAIL',
			'admitted' => $candidate['admitted'],
			'mapping_reason' => $candidate['reason'],
			'input_and_count_equal' => $counts,
			'native_callback_count' => count( $native['trace'] ),
			'candidate_callback_count' => count( $candidate['trace'] ),
			'spans' => $candidate['spans'],
			'checks' => $checks,
			'failed_invariants' => $bad,
			'native_sha256' => hash( 'sha256', (string) $native['output'] ),
			'candidate_sha256' => hash( 'sha256', (string) $candidate['output'] ),
		);
		if ( $bad ) {
			break; // Decisive bounded falsifier: don't grow the algorithm.
		}
	}
	$stored_form = GFAPI::get_form( (int) $form['id'] );
	$stored_entry = GFAPI::get_entry( $entry_id );
	$persistence = is_array( $stored_form ) && is_array( $stored_entry ) &&
		hash( 'sha256', wp_json_encode( $stored_form ) ) === $form_hash &&
		hash( 'sha256', wp_json_encode( $stored_entry ) ) === $entry_hash;
	$evidence = array(
		'contract' => 'G011_TWO_HUMAN_SAME_DATE_POST_NATIVE_V1',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'host_version' => GFForms::$version,
		'host_sha256' => getenv( 'WU008_GF_SHA256' ),
		'expected_native_human' => $expect_native,
		'expected_jalali_human' => $expect_jalali,
		'machine_literal_fragments' => $machine,
		'candidate_native_renders' => 0,
		'candidate_shared_merge_tag_invocations' => 0,
		'original_form_entry_unchanged' => $persistence,
		'cases' => $cases,
	);
	file_put_contents( $dir . '/g011-multi-occurrence-alignment.json',
		wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	if ( ! $persistence ) {
		$fail( 'Persisted Form/Entry drift.' );
	}
	foreach ( $cases as $name => $case ) {
		if ( 'PASS' !== $case['status'] ) {
			$fail( 'Decisive ' . $name . ': ' . implode( ',', $case['failed_invariants'] ) );
		}
	}
	if ( count( $cases ) !== 8 ) {
		$fail( 'Incomplete bounded multi-occurrence qualification.' );
	}
	echo "G011 two independent human occurrences: bounded observable PASS\n";
} finally {
	update_option( 'pgr_modules', $original_options );
	add_filter( 'gform_confirmation', $production_hook, $priority, 4 );
}
