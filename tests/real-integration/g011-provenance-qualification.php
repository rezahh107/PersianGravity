<?php
/**
 * G-011 supported-seam provenance adversarial qualification.
 * TEST-ONLY. No vendor or production runtime modifications.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;

$fail = static function ( string $why ): void {
	throw new RuntimeException( 'G011_PROVENANCE: ' . $why );
};
$dir      = getenv( 'WU008_ARTIFACT_DIR' );
$manifest = is_string( $dir ) ? json_decode( (string) file_get_contents( $dir . '/g011-confirmation.json' ), true ) : null;
if ( ! is_array( $manifest ) || '3.1.1.1' !== GFForms::$version ||
	'3.1.1.1' !== ( $manifest['host_version'] ?? '' ) ||
	getenv( 'WU008_PGR_SHA' ) !== ( $manifest['head'] ?? null )
) {
	$fail( 'Exact licensed host or Head identity not proven.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
if ( ! is_array( $form ) ) {
	$fail( 'Missing authentic configured form.' );
}
$entry_id = GFAPI::add_entry( array(
	'form_id' => (int) $manifest['form_id'],
	'status' => 'active',
	'date_created' => '2026-03-20 20:30:00',
	'1' => 'ordinary',
) );
$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
if ( ! is_array( $entry ) ) {
	$fail( 'Disposable entry creation failed.' );
}
$tag = '{date_created:format:Y/m/d}';
$states_original = get_option( 'pgr_modules' );
if ( ! is_array( $states_original ) || ! isset( $states_original['states'] ) ) {
	$fail( 'Missing module state.' );
}
$states = $states_original;
$states['states']['jalali_presentation'] = false;
update_option( 'pgr_modules', $states );
$traces = array();
$active_case = '';
$pre = static function ( $text, $hook_form, $hook_entry, $url, $esc, $nl2br, $format ) use ( &$traces, &$active_case, $tag ) {
	$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
	$traces[ $active_case ]['pre'][] = array(
		'selected_type' => is_array( $selected ) ? ( $selected['type'] ?? null ) : null,
		'matches_selected_message' => is_array( $selected ) && $text === ( $selected['message'] ?? null ),
		'has_tag' => is_string( $text ) && false !== strpos( $text, $tag ),
		'format' => $format,
		'url_encode' => (bool) $url,
		'escape' => (bool) $esc,
	);
	return $text;
};
$post = static function ( $value, $hook_form ) use ( &$traces, &$active_case ) {
	$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
	$traces[ $active_case ]['post'][] = array(
		'selected_type' => is_array( $selected ) ? ( $selected['type'] ?? null ) : null,
		'is_string' => is_string( $value ),
		'is_redirect' => is_array( $value ) && isset( $value['redirect'] ),
	);
	return $value;
};

$lines = array();
$read_method = static function ( string $class, string $method ) use ( &$lines, $fail ): string {
	$reflection = new ReflectionMethod( $class, $method );
	$source     = file( $reflection->getFileName() );
	if ( false === $source ) {
		$fail( 'Cannot open pinned host method.' );
	}
	$start   = $reflection->getStartLine();
	$end     = $reflection->getEndLine();
	$lines[ $class . '::' . $method ] = array( 'file' => basename( $reflection->getFileName() ), 'start' => $start, 'end' => $end );
	return implode( '', array_slice( $source, $start - 1, $end - $start + 1 ) );
};
$handle_source = $read_method( 'GFFormDisplay', 'handle_confirmation' );
$message_source = $read_method( 'GFFormDisplay', 'get_confirmation_message' );
$url_source = $read_method( 'GFFormDisplay', 'get_confirmation_url' );
$replace_source = $read_method( 'GFCommon', 'replace_variables' );
$chain = array(
	'handle_selects_text' => false !== strpos( $handle_source, 'get_confirmation_message(' ),
	'handle_selects_transport' => false !== strpos( $handle_source, 'get_confirmation_url(' ),
	'handle_post_hook' => false !== strpos( $handle_source, 'gform_confirmation' ),
	'message_native_merge_tags' => false !== strpos( $message_source, 'replace_variables(' ),
	'url_native_merge_tags' => false !== strpos( $url_source, 'replace_variables(' ),
	'merge_tag_shared_prehook' => false !== strpos( $replace_source, 'gform_pre_replace_merge_tags' ),
);
$make_form = static function ( string $type, string $message, bool $conditional = false ) use ( $form ): array {
	$copy = $form;
	$confirmation = array(
		'id' => 'g011-provenance-default',
		'name' => 'Provenance Default',
		'isActive' => true,
		'isDefault' => true,
		'type' => $type,
		'message' => $message,
		'disableAutoformat' => true,
		'url' => home_url( '/?keep=1' ),
		'pageId' => (int) get_option( 'page_on_front', 0 ),
		'queryString' => 'day={date_created:format:Y/m/d}&keep=1',
	);
	$copy['confirmations'] = array( 'default' => $confirmation );
	if ( $conditional ) {
		$copy['confirmations']['conditional'] = array(
			'id' => 'g011-provenance-conditional',
			'name' => 'Provenance Conditional',
			'isActive' => true,
			'isDefault' => false,
			'type' => 'message',
			'message' => '<strong>Selected special ' . '{date_created:format:Y/m/d}' . '</strong>',
			'disableAutoformat' => true,
			'conditionalLogic' => array(
				'actionType' => 'show', 'logicType' => 'all',
				'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ),
			),
		);
	}
	return $copy;
};
$sample = '<p>Created=' . $tag . '</p> receipt-' . $tag . '.tar.gz';
$evidence = array(
	'contract' => 'G011_PROVENANCE_QUALIFICATION_1',
	'head' => getenv( 'WU008_PGR_SHA' ),
	'host_version' => GFForms::$version,
	'package_sha256' => getenv( 'WU008_GF_SHA256' ),
	'host_lines' => $lines,
	'chain' => $chain,
);
add_filter( 'gform_pre_replace_merge_tags', $pre, 550, 7 );
add_filter( 'gform_confirmation', $post, 5, 2 );
try {
	foreach ( array(
		'ordinary' => array( 'message', $sample, false, false ),
		'ajax' => array( 'message', $sample, false, true ),
		'conditional' => array( 'message', $sample, true, false ),
		'page' => array( 'page', $sample, false, false ),
		'redirect' => array( 'redirect', $sample, false, false ),
	) as $name => $cfg ) {
		$active_case = $name;
		$traces[ $name ] = array( 'pre' => array(), 'post' => array() );
		$one = $make_form( $cfg[0], $cfg[1], $cfg[2] );
		$qualified_entry = $entry;
		$qualified_entry['1'] = 'conditional' === $name ? 'special' : 'ordinary';
		$value = GFFormDisplay::handle_confirmation( $one, $qualified_entry, $cfg[3] );
		$evidence['cases'][ $name ] = array(
			'out_type' => get_debug_type( $value ),
			'selected_type' => $one['confirmation']['type'] ?? null,
			'selected_id' => $one['confirmation']['id'] ?? null,
			'pre_count' => count( $traces[ $name ]['pre'] ),
			'post_count' => count( $traces[ $name ]['post'] ),
			'pre' => $traces[ $name ]['pre'],
			'post' => $traces[ $name ]['post'],
		);
	}

	// The shared prehook is independently callable using identical form, entry
	// and matching selected template data, without a native confirmation call.
	$active_case = 'direct_forged';
	$traces[ $active_case ] = array( 'pre' => array(), 'post' => array() );
	$forged = $make_form( 'message', $sample );
	$forged['confirmation'] = $forged['confirmations']['default'];
	GFCommon::replace_variables( $sample, $forged, $entry, false, true, false, 'html' );
	GFCommon::replace_variables( $sample, $forged, $entry, false, true, false, 'html' );
	$evidence['cases'][ $active_case ] = array(
		'pre' => $traces[ $active_case ]['pre'],
		'post_count' => count( $traces[ $active_case ]['post'] ),
		'replay_calls' => count( $traces[ $active_case ]['pre'] ),
	);

	// Notification-like direct GFCommon call without notification dispatch.
	$active_case = 'notification_like';
	$traces[ $active_case ] = array( 'pre' => array(), 'post' => array() );
	$notification_form = $make_form( 'message', $sample );
	GFCommon::replace_variables( $sample, $notification_form, $entry, false, true, false, 'html' );
	$evidence['cases'][ $active_case ] = array(
		'pre' => $traces[ $active_case ]['pre'],
		'post_count' => count( $traces[ $active_case ]['post'] ),
	);

	// Reentrant call from another plugin's callback with forged selected context.
	$active_case = 'nested_reentrant';
	$traces[ $active_case ] = array( 'pre' => array(), 'post' => array() );
	$nested_depth = 0;
	$nested_spy = static function ( $text, $hook_form, $hook_entry ) use ( &$nested_depth, $tag ) {
		if ( 0 === $nested_depth && false !== strpos( (string) $text, $tag ) ) {
			++$nested_depth;
			GFCommon::replace_variables( $text, $hook_form, $hook_entry, false, true, false, 'html' );
		}
		return $text;
	};
	add_filter( 'gform_pre_replace_merge_tags', $nested_spy, 500, 3 );
	try {
		GFCommon::replace_variables( $sample, $forged, $entry, false, true, false, 'html' );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $nested_spy, 500 );
	}
	$evidence['cases'][ $active_case ] = array(
		'pre_count' => count( $traces[ $active_case ]['pre'] ),
		'selected_matches' => array_map(
			static function ( $record ) { return $record['matches_selected_message']; },
			$traces[ $active_case ]['pre']
		),
		'post_count' => count( $traces[ $active_case ]['post'] ),
	);
	
	// Deliberately demonstrate marker leakage from a metadata-only prehook.
	// No global context or product callback is mutated outside this fixture.
	$active_case = 'forged_marker_leak';
	$marker = 'PGRG011_PROVENANCE_COLLISION_SENTINEL';
	$trial_pre = static function ( $text, $hook_form ) use ( $sample, $tag, $marker ) {
		$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
		if ( $text === $sample && is_array( $selected ) && $text === ( $selected['message'] ?? null ) ) {
			return str_replace( 'Created=' . $tag, 'Created=' . $marker, $text );
		}
		return $text;
	};
	add_filter( 'gform_pre_replace_merge_tags', $trial_pre, 10, 2 );
	try {
		$forged_value = GFCommon::replace_variables( $sample, $forged, $entry, false, true, false, 'html' );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $trial_pre, 10 );
	}
	$evidence['cases'][ $active_case ] = array(
		'marker_leaked_to_direct_api' => is_string( $forged_value ) && false !== strpos( $forged_value, $marker ),
		'direct_api_post_confirmation_calls' => count( $traces[ $active_case ]['post'] ?? array() ),
	);

	// Pre-callback priority and content mutation: not all third-party
	// extensions preserve either template identity or markers.
	$active_case = 'priority_mutation';
	$traces[ $active_case ] = array( 'pre' => array(), 'post' => array() );
	$early = static function ( $text ) {
		return str_replace( 'Created=', 'NativeModified=', $text );
	};
	$late = static function ( $text ) {
		return str_replace( 'NativeModified=', 'LaterModified=', $text );
	};
	add_filter( 'gform_pre_replace_merge_tags', $early, 1, 1 );
	add_filter( 'gform_pre_replace_merge_tags', $late, 999, 1 );
	try {
		$mutated_value = GFCommon::replace_variables( $sample, $forged, $entry, false, true, false, 'html' );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $early, 1 );
		remove_filter( 'gform_pre_replace_merge_tags', $late, 999 );
	}
	$evidence['cases'][ $active_case ] = array(
		'pre' => $traces[ $active_case ]['pre'],
		'output_is_later_modified' => is_string( $mutated_value ) &&
			false !== strpos( $mutated_value, 'LaterModified=' ),
	);

	$evidence['falsification'] = array(
		'fake_meta_indistinguishable_in_shared_prehook' =>
			! empty( $traces['direct_forged']['pre'][0]['matches_selected_message'] ) &&
			2 === count( $traces['direct_forged']['pre'] ),
		'nested_matching_reentrant' =>
			2 === count( $traces['nested_reentrant']['pre'] ) &&
			! in_array( false, array_map( static function ( $one ) { return $one['matches_selected_message']; }, $traces['nested_reentrant']['pre'] ), true ),
		'forged_marker_leak' => $evidence['cases']['forged_marker_leak']['marker_leaked_to_direct_api'],
		'callback_priority_changes_input' => $evidence['cases']['priority_mutation']['output_is_later_modified'],
	);
} finally {
	remove_filter( 'gform_confirmation', $post, 5 );
	remove_filter( 'gform_pre_replace_merge_tags', $pre, 550 );
	update_option( 'pgr_modules', $states_original );
}

$path = $dir . '/g011-provenance-qualification.json';
file_put_contents( $path, wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
foreach ( $chain as $key => $value ) {
	if ( ! $value ) { $fail( 'Unexpected exact-host source chain for ' . $key ); }
}
foreach ( $evidence['falsification'] as $key => $value ) {
	if ( ! $value ) { $fail( 'Provenance falsifier not observed: ' . $key ); }
}
if ( ! ( $evidence['cases']['ordinary']['pre_count'] ?? 0 ) ||
	! ( $evidence['cases']['conditional']['pre_count'] ?? 0 ) ||
	! ( $evidence['cases']['redirect']['post_count'] ?? 0 ) ) {
	$fail( 'Authentic confirmation lifecycle controls incomplete.' );
}
echo "G011 supported-hook provenance falsifiers observed on exact GF 3.1.1.1\n";
