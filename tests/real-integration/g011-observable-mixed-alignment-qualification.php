<?php
/**
 * G-011 qualification ONLY: bounded mixed-occurrence post-native alignment.
 *
 * Independent oracle: a single human-facing tag must become Jalali; fixed
 * Gregorian and all machine-bearing occurrences stay byte-identical.
 * No author marker, custom author convention, new tag, native rerender,
 * GFCommon replacement, or pre-render mutation by the candidate.
 */
defined( 'ABSPATH' ) || exit;

$fail = static function ( string $msg ): void {
	throw new RuntimeException( 'G011_OBSERVABLE_ALIGNMENT: ' . $msg );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$manifest = json_decode( (string) file_get_contents( $dir . '/g011-confirmation.json' ), true );
if (
	! is_array( $manifest ) || '3.1.1.1' !== GFForms::$version ||
	$manifest['head'] !== getenv( 'WU008_PGR_SHA' ) ||
	'fa_IR' !== determine_locale()
) {
	$fail( 'Exact licensed GF/head/locale unavailable.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
$entry_id = GFAPI::add_entry( array(
	'form_id' => (int) $manifest['form_id'], 'status' => 'active',
	'date_created' => '2026-03-20 20:30:00', '1' => 'G011 mixed qualification',
) );
$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
if ( ! is_array( $form ) || ! is_array( $entry ) ) {
	$fail( 'Actual GF form/entry unavailable.' );
}
$utc = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $entry['date_created'], new DateTimeZone( 'UTC' ) );
$errors = DateTimeImmutable::getLastErrors();
if ( false === $utc || ( is_array( $errors ) && ( $errors['error_count'] || $errors['warning_count'] ) ) ||
	$utc->format( 'Y-m-d H:i:s' ) !== $entry['date_created'] ) {
	$fail( 'G008 strict UTC source invalid.' );
}
$local = $utc->setTimezone( wp_timezone() );
$gregorian = $local->format( 'Y/m/d' );
$jalali = PGR_Jalali_Presentation::format_date(
	(int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' )
);
if ( ! is_string( $jalali ) || $gregorian === $jalali ) {
	$fail( 'No useful calendar conversion oracle.' );
}
$tag = '{date_created:format:Y/m/d}';
$template = '<div class="g011-mixed" data-day="' . $tag . '"><p class="human">Created <strong>' .
	$tag . '</strong> Fixed Gregorian: ' . $gregorian . '</p><p>Archive receipt-' . $tag .
	'.tar.gz and report_' . $tag . '-v2.pdf and ./archive/' . $tag .
	'/detail</p><a href="/download/' . $tag . '?keep=1">Native link</a></div>';

$original = array( 'id' => 'g011-observable-mixed', 'name' => 'G011 Observable Mixed',
	'type' => 'message', 'isDefault' => true, 'isActive' => true,
	'message' => $template, 'disableAutoformat' => true );
$test_form = $form;
$test_form['confirmations'] = array( 'g011-observable-mixed' => $original );
$stored_form_hash = hash( 'sha256', wp_json_encode( $form ) );
$stored_entry_hash = hash( 'sha256', wp_json_encode( $entry ) );

// A conservative post-native candidate: classify every date-tag occurrence
// by source-template syntax. Only text-node, non-path, non-filename spans are
// eligible. Require a single admitted occurrence and a UNIQUE full literal
// alignment on both sides. Ambiguous or changed input returns native bytes.
$align = static function ( $output, $selected_form, $given_entry ) use ( $tag, $gregorian, $jalali, $entry ) {
	$selected = $selected_form['confirmation'] ?? null;
	if ( ! is_string( $output ) || ! is_array( $selected ) ||
		'message' !== ( $selected['type'] ?? null ) ||
		! is_string( $selected['message'] ?? null ) ||
		$given_entry['date_created'] !== $entry['date_created'] ) {
		return array( 'admitted' => false, 'output' => $output, 'reason' => 'ownership_or_source' );
	}
	$raw = $selected['message'];
	preg_match_all( '/\{date_created:format:Y\/m\/d\}/', $raw, $matches, PREG_OFFSET_CAPTURE );
	$eligible = array();
	foreach ( $matches[0] as $match ) {
		$offset = $match[1];
		$left_tag = strrpos( substr( $raw, 0, $offset ), '<' );
		$left_close = strrpos( substr( $raw, 0, $offset ), '>' );
		// An occurrence inside an HTML start tag or attribute is not text.
		if ( false !== $left_tag && ( false === $left_close || $left_tag > $left_close ) ) {
			continue;
		}
		$before = substr( $raw, ( false === $left_close ? 0 : $left_close + 1 ),
			$offset - ( false === $left_close ? 0 : $left_close + 1 ) );
		$right_open = strpos( $raw, '<', $offset + strlen( $tag ) );
		$after = substr( $raw, $offset + strlen( $tag ),
			( false === $right_open ? strlen( $raw ) : $right_open ) - $offset - strlen( $tag ) );
		$left_token = preg_replace( '/^.*\s/s', '', $before );
		$right_token = preg_replace( '/\s.*$/s', '', $after );
		if ( preg_match( '/(?:\/|[._-])$/', $left_token ) ||
			preg_match( '/^(?:\/|[._-]|#|\?)/', $right_token ) ||
			preg_match( '/(?:https?:|www\.|=\/)/i', $left_token ) ) {
			continue;
		}
		$eligible[] = $offset;
	}
	if ( count( $eligible ) !== 1 ) {
		return array( 'admitted' => false, 'output' => $output, 'reason' => 'nonunique_template_ownership' );
	}
	$offset = $eligible[0];
	// Stable adjacent literals are taken from the author template, not
	// invented marker values. They need not follow any author convention.
	$prefix = substr( $raw, max( 0, $offset - 30 ), min( 30, $offset ) );
	$suffix = substr( $raw, $offset + strlen( $tag ), 30 );
	if ( strlen( $prefix ) < 8 || strlen( $suffix ) < 8 ||
		substr_count( $output, $prefix ) !== 1 || substr_count( $output, $suffix ) !== 1 ) {
		return array( 'admitted' => false, 'output' => $output, 'reason' => 'ambiguous_or_missing_anchors' );
	}
	$p = strpos( $output, $prefix );
	$s = strpos( $output, $suffix, $p + strlen( $prefix ) );
	if ( false === $s || $s < $p + strlen( $prefix ) ||
		substr( $output, $p + strlen( $prefix ), $s - $p - strlen( $prefix ) ) !== $gregorian ) {
		return array( 'admitted' => false, 'output' => $output, 'reason' => 'changed_or_nonbijective_value' );
	}
	return array( 'admitted' => true,
		'output' => substr( $output, 0, $p + strlen( $prefix ) ) . esc_html( $jalali ) . substr( $output, $s ),
		'offset' => $p + strlen( $prefix ), 'native_length' => strlen( $gregorian ), 'reason' => 'unique' );
};

$module = get_option( 'pgr_modules' );
$adapter = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
$priority = has_filter( 'gform_confirmation', $adapter );
if ( ! is_array( $module ) || false === $priority ) {
	$fail( 'Missing module/production adapter.' );
}
remove_filter( 'gform_confirmation', $adapter, $priority );
$enabled = $module;
$enabled['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $enabled );

$run = static function ( string $mode, bool $candidate ) use ( $test_form, $entry, $tag, $gregorian, $align ) {
	$input = array();
	$early = static function ( $text ) use ( $mode, $tag, $gregorian ) {
		if ( 'equivalent' === $mode ) {
			return str_replace( $tag, $gregorian, $text );
		}
		if ( 'different' === $mode ) {
			return str_replace( $tag, '2031/11/30', $text );
		}
		if ( 'html_changed' === $mode ) {
			return str_replace( 'Fixed Gregorian:', '<em>Fixed Gregorian:</em>', $text );
		}
		if ( 'duplicate_anchors' === $mode ) {
			return str_replace( ' Fixed Gregorian:', ' Created <strong>2026/03/21</strong> Fixed Gregorian:', $text );
		}
		return $text;
	};
	$spy = static function ( $text, $form ) use ( &$input ) {
		$input[] = array( 'hash' => hash( 'sha256', (string) $text ),
			'selected_type' => $form['confirmation']['type'] ?? null,
			'length' => strlen( (string) $text ) );
		return $text;
	};
	$mapped = null;
	$post = static function ( $output, $form, $entry ) use ( $align, &$mapped ) {
		$mapped = $align( $output, $form, $entry );
		return $mapped['output'];
	};
	add_filter( 'gform_pre_replace_merge_tags', $early, 2, 1 );
	add_filter( 'gform_pre_replace_merge_tags', $spy, 999, 2 );
	if ( $candidate ) {
		add_filter( 'gform_confirmation', $post, 25, 3 );
	}
	try {
		$f = $test_form;
		$out = GFFormDisplay::handle_confirmation( $f, $entry, false );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $early, 2 );
		remove_filter( 'gform_pre_replace_merge_tags', $spy, 999 );
		if ( $candidate ) {
			remove_filter( 'gform_confirmation', $post, 25 );
		}
	}
	return array( 'output' => $out, 'inputs' => $input,
		'callback_count' => count( $input ), 'admitted' => $mapped['admitted'] ?? null,
		'reason' => $mapped['reason'] ?? null, 'offset' => $mapped['offset'] ?? null );
};

$results = array();
try {
	$expected_human = 'Created <strong>' . esc_html( $jalali ) . '</strong>';
	$expected_native = 'Created <strong>' . $gregorian . '</strong>';
	$machine = array( 'data-day="' . $gregorian . '"', 'Fixed Gregorian: ' . $gregorian,
		'receipt-' . $gregorian . '.tar.gz', 'report_' . $gregorian . '-v2.pdf',
		'./archive/' . $gregorian . '/detail', 'href="/download/' . $gregorian . '?keep=1"' );
	foreach ( array( 'normal', 'equivalent', 'different', 'html_changed', 'duplicate_anchors' ) as $mode ) {
		$native = $run( $mode, false );
		$candidate = $run( $mode, true );
		$changed = is_string( $native['output'] ) && is_string( $candidate['output'] ) &&
			$native['output'] !== $candidate['output'];
		$should_convert = in_array( $mode, array( 'normal', 'equivalent' ), true );
		$inputs_equal = $native['inputs'] === $candidate['inputs'] &&
			1 === $native['callback_count'] && 1 === $candidate['callback_count'];
		$expected = $native['output'];
		if ( $should_convert ) {
			if ( 1 !== substr_count( $expected, $expected_native ) ) {
				$fail( 'Independent native oracle cannot locate one human tag: ' . $mode );
			}
			$expected = str_replace( $expected_native, $expected_human, $expected );
		}
		$checks = array(
			'pre_inputs_and_callback_counts_equal' => $inputs_equal,
			'automatic_liveness' => $changed === $should_convert,
			'exact_expected_output' => $candidate['output'] === $expected,
			'candidate_admission' => $candidate['admitted'] === $should_convert,
		);
		if ( $should_convert ) {
			foreach ( $machine as $i => $fragment ) {
				$checks['machine_' . $i . '_native'] = substr_count( $candidate['output'], $fragment ) ===
					substr_count( $native['output'], $fragment );
			}
			if ( null === $candidate['offset'] ) {
				$checks['bounded_span'] = false;
			} else {
				$o = $candidate['offset'];
				$checks['bounded_span'] =
					substr( $candidate['output'], 0, $o ) === substr( $native['output'], 0, $o ) &&
					substr( $candidate['output'], $o + strlen( esc_html( $jalali ) ) ) ===
					substr( $native['output'], $o + strlen( $gregorian ) );
			}
		}
		$results[ $mode ] = array(
			'native_counts' => $native['callback_count'], 'candidate_counts' => $candidate['callback_count'],
			'third_party_inputs_identical' => $inputs_equal,
			'native_hash' => hash( 'sha256', (string) $native['output'] ),
			'candidate_hash' => hash( 'sha256', (string) $candidate['output'] ),
			'converted' => $changed, 'admitted' => $candidate['admitted'],
			'fallback_reason' => $candidate['reason'], 'checks' => $checks,
		);
		$errors = array_keys( array_filter( $checks, static function ( $v ) { return ! $v; } ) );
		if ( $errors ) {
			// Preserve observations before returning a bounded failure.
			$results[ $mode ]['failed_invariants'] = $errors;
			break;
		}
	}
	$stored_form = GFAPI::get_form( (int) $form['id'] );
	$stored_entry = GFAPI::get_entry( $entry_id );
	$persistence = is_array( $stored_form ) && is_array( $stored_entry ) &&
		$stored_form_hash === hash( 'sha256', wp_json_encode( $stored_form ) ) &&
		$stored_entry_hash === hash( 'sha256', wp_json_encode( $stored_entry ) );
	$evidence = array(
		'contract' => 'G011_MIXED_OBSERVABLE_POSTNATIVE_V1',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'host_version' => GFForms::$version,
		'host_sha256' => getenv( 'WU008_GF_SHA256' ),
		'author_marker_required' => false, 'native_processing_calls_from_candidate' => 0,
		'expected_human' => $expected_human, 'expected_machine_fragments' => $machine,
		'form_entry_persistence' => $persistence, 'modes' => $results,
	);
	file_put_contents( $dir . '/g011-observable-mixed-qualification.json',
		wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	if ( ! $persistence ) {
		$fail( 'Form/Entry persistence changed.' );
	}
	foreach ( $results as $mode => $case ) {
		if ( ! empty( $case['failed_invariants'] ) ) {
			$fail( $mode . ': ' . implode( ',', $case['failed_invariants'] ) );
		}
	}
	if ( 5 !== count( $results ) ) {
		$fail( 'Mixed case execution incomplete.' );
	}
	echo "G011 bounded mixed observable alignment controls PASSED\n";
} finally {
	update_option( 'pgr_modules', $module );
	add_filter( 'gform_confirmation', $adapter, $priority, 4 );
}
