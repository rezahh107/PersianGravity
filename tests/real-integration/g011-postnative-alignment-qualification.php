<?php
/**
 * G-011 exact-host read-only post-render alignment qualification ONLY.
 *
 * A minimal positively live, structurally unique template/output alignment
 * is challenged with two observationally identical native outputs whose
 * date bytes have different provenance. This is a deliberate falsifier,
 * not a production implementation or a general-purpose alignment algorithm.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;

$fail = static function ( string $reason ): void {
	throw new RuntimeException( 'G011_POSTRENDER_ALIGN: ' . $reason );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$manifest = is_string( $dir ) ? json_decode( (string) file_get_contents( $dir . '/g011-confirmation.json' ), true ) : null;
if (
	! is_array( $manifest ) ||
	'3.1.1.1' !== GFForms::$version ||
	getenv( 'WU008_PGR_SHA' ) !== ( $manifest['head'] ?? null ) ||
	'fa_IR' !== determine_locale()
) {
	$fail( 'Host, Head, or locale mismatch.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
if ( ! is_array( $form ) ) {
	$fail( 'Authentic GF form unavailable.' );
}
$entry_id = GFAPI::add_entry(
	array(
		'form_id'      => (int) $form['id'],
		'status'       => 'active',
		'date_created' => '2026-03-20 20:30:00',
		'1'            => 'postrender qualification',
	)
);
$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
if ( ! is_array( $entry ) ) {
	$fail( 'Could not get disposable authentic GF Entry.' );
}
$tag = '{date_created:format:Y/m/d}';
$source = DateTimeImmutable::createFromFormat(
	'!Y-m-d H:i:s', $entry['date_created'], new DateTimeZone( 'UTC' )
);
$errors = DateTimeImmutable::getLastErrors();
if (
	false === $source ||
	( is_array( $errors ) && ( $errors['error_count'] || $errors['warning_count'] ) ) ||
	$source->format( 'Y-m-d H:i:s' ) !== $entry['date_created']
) {
	$fail( 'No strictly valid G008 system-date source.' );
}
$local = $source->setTimezone( wp_timezone() );
$native_date = $local->format( 'Y/m/d' );
$jalali_date = PGR_Jalali_Presentation::format_date(
	(int) $local->format( 'Y' ),
	(int) $local->format( 'm' ),
	(int) $local->format( 'd' )
);
if ( ! is_string( $jalali_date ) || '' === $jalali_date || $jalali_date === $native_date ) {
	$fail( 'G008 presentation facade did not convert a valid system date.' );
}
$template = '<strong>Created=' . $tag . '</strong> literal ' . $native_date;
$conf = array(
	'id'                => 'g011-postrender-qual',
	'name'              => 'Postrender alignment qualification',
	'isActive'          => true,
	'isDefault'         => true,
	'type'              => 'message',
	'message'           => $template,
	'disableAutoformat' => true,
);
$test_form = $form;
$test_form['confirmations'] = array( $conf['id'] => $conf );
$persisted_form_hash = hash( 'sha256', wp_json_encode( $form ) );
$persisted_entry_hash = hash( 'sha256', wp_json_encode( $entry ) );

// Candidate uses only selected original Text template + already rendered
// native HTML + original raw Entry date converted by the G008 facade.
// It does not call GFCommon, re-render or perform global date replacement.
// The unique-anchor grammar is intentionally narrow; no generic claim.
$align = static function ( $rendered, $selected_form, $given_entry ) use (
	$tag, $native_date, $jalali_date, $template
) {
	$selected = $selected_form['confirmation'] ?? null;
	if (
		! is_string( $rendered ) ||
		! is_array( $selected ) ||
		'message' !== ( $selected['type'] ?? null ) ||
		( $selected['message'] ?? null ) !== $template ||
		( $given_entry['date_created'] ?? null ) !== '2026-03-20 20:30:00' ||
		1 !== substr_count( $template, $tag )
	) {
		return array( 'admitted' => false, 'output' => $rendered );
	}
	$offset = strpos( $template, $tag );
	$prefix = substr( $template, 0, $offset );
	$suffix = substr( $template, $offset + strlen( $tag ) );
	// One possible complete decomposition, requiring identical stable
	// literal bytes and independently validated native system date.
	if (
		1 !== substr_count( $rendered, $prefix ) ||
		1 !== substr_count( $rendered, $suffix ) ||
		$rendered !== $prefix . $native_date . $suffix
	) {
		return array( 'admitted' => false, 'output' => $rendered );
	}
	return array(
		'admitted' => true,
		'output' => $prefix . esc_html( $jalali_date ) . $suffix,
	);
};

$module_option = get_option( 'pgr_modules' );
if ( ! is_array( $module_option ) || ! isset( $module_option['states'] ) ) {
	$fail( 'Missing module state.' );
}
$adapter_callback = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
$adapter_priority = has_filter( 'gform_confirmation', $adapter_callback );
if ( false === $adapter_priority ) {
	$fail( 'Could not isolate existing adapter in the disposable process.' );
}
remove_filter( 'gform_confirmation', $adapter_callback, $adapter_priority );
$enabled = $module_option;
$enabled['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $enabled );

$records = array();
$run = static function ( bool $third_party_replaces, bool $candidate ) use (
	$test_form, $entry, $tag, $native_date, $align, &$records
): array {
	$counts = 0;
	$trace  = array();
	$trial  = null;
	$early  = static function ( $text ) use ( $third_party_replaces, $tag, $native_date ) {
		return $third_party_replaces ? str_replace( $tag, $native_date, $text ) : $text;
	};
	$spy = static function ( $text ) use ( &$counts, &$trace, $tag, $native_date ) {
		++$counts;
		$trace[] = array(
			'contains_original_tag' => is_string( $text ) && false !== strpos( $text, $tag ),
			'contains_native_literal' => is_string( $text ) && false !== strpos( $text, $native_date ),
		);
		return $text;
	};
	$observer = static function ( $rendered, $selected_form, $given_entry ) use ( $align, &$trial ) {
		$trial = $align( $rendered, $selected_form, $given_entry );
		return $trial['output'];
	};
	add_filter( 'gform_pre_replace_merge_tags', $early, 2, 1 );
	add_filter( 'gform_pre_replace_merge_tags', $spy, 999, 1 );
	if ( $candidate ) {
		add_filter( 'gform_confirmation', $observer, 25, 3 );
	}
	try {
		$copy = $test_form;
		$out = GFFormDisplay::handle_confirmation( $copy, $entry, false );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $early, 2 );
		remove_filter( 'gform_pre_replace_merge_tags', $spy, 999 );
		if ( $candidate ) {
			remove_filter( 'gform_confirmation', $observer, 25 );
		}
	}
	if ( ! is_string( $out ) ) {
		throw new RuntimeException( 'GF native Text result was not a string.' );
	}
	return array(
		'out' => $out,
		'callback_calls' => $counts,
		'callback_trace' => $trace,
		'candidate_admitted' => $trial['admitted'] ?? null,
	);
};

try {
	// Two equivalent native renders: one replaced by GF, one replaced by an
	// ordinary third-party pre-replacement hook. Candidate sees identical
	// template, selected form, entry, and final HTML.
	$native = $run( false, false );
	$third_party_native = $run( true, false );
	$positive = $run( false, true );
	$falsifier = $run( true, true );

	$same_visible_inputs = $native['out'] === $third_party_native['out'];
	$liveness = $positive['candidate_admitted'] === true &&
		$positive['out'] === '<strong>Created=' . esc_html( $jalali_date ) . '</strong> literal ' . $native_date &&
		$positive['out'] !== $native['out'];
	$causal_ambiguity = $falsifier['candidate_admitted'] === true &&
		$falsifier['out'] === $positive['out'] &&
		$same_visible_inputs &&
		$native['callback_trace'][0]['contains_original_tag'] &&
		! $third_party_native['callback_trace'][0]['contains_original_tag'];
	$count_equivalence =
		1 === $native['callback_calls'] &&
		1 === $third_party_native['callback_calls'] &&
		1 === $positive['callback_calls'] &&
		1 === $falsifier['callback_calls'];
	$stored_form = GFAPI::get_form( (int) $form['id'] );
	$stored_entry = GFAPI::get_entry( $entry_id );
	$persistence =
		is_array( $stored_form ) && hash( 'sha256', wp_json_encode( $stored_form ) ) === $persisted_form_hash &&
		is_array( $stored_entry ) && hash( 'sha256', wp_json_encode( $stored_entry ) ) === $persisted_entry_hash;

	$evidence = array(
		'contract'                   => 'G011_POST_NATIVE_ALIGNMENT_CAUSAL_PROVENANCE_FALSIFIER_V1',
		'head'                       => getenv( 'WU008_PGR_SHA' ),
		'host_version'               => GFForms::$version,
		'host_sha256'                => getenv( 'WU008_GF_SHA256' ),
		'selected_type'              => 'message',
		'native_expected_from_utc'   => $native_date,
		'jalali_from_G008_facade'    => $jalali_date,
		'positive_liveness'          => $liveness,
		'anchors_unique_for_fixture' => 1 === substr_count( $native['out'], '<strong>Created=' ) &&
			1 === substr_count( $native['out'], '</strong> literal ' . $native_date ),
		'control_native_outputs_identical' => $same_visible_inputs,
		'third_party_replaces_before_native_Gf' => $causal_ambiguity,
		'callback_count_equivalence' => $count_equivalence,
		'persisted_form_entry_unchanged' => $persistence,
		'observations' => array(
			'native' => array( 'calls' => $native['callback_calls'], 'trace' => $native['callback_trace'] ),
			'third_party_native' => array( 'calls' => $third_party_native['callback_calls'], 'trace' => $third_party_native['callback_trace'] ),
			'candidate' => array( 'calls' => $positive['callback_calls'], 'admitted' => $positive['candidate_admitted'] ),
			'candidate_with_third_party' => array( 'calls' => $falsifier['callback_calls'], 'admitted' => $falsifier['candidate_admitted'] ),
		),
		'decision' => 'FALSIFIED_UNOBSERVABLE_MERGE_TAG_VALUE_PROVENANCE',
	);
	file_put_contents(
		$dir . '/g011-postnative-alignment-falsifier.json',
		wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
	if ( ! $liveness || ! $causal_ambiguity || ! $count_equivalence || ! $persistence ) {
		$fail( 'Exact-host positive/falsifier conditions not established; inspect evidence.' );
	}
	echo "G011 post-native alignment liveness proven; identical-output causal-provenance falsifier confirmed\n";
} finally {
	update_option( 'pgr_modules', $module_option );
	add_filter( 'gform_confirmation', $adapter_callback, $adapter_priority, 4 );
}
