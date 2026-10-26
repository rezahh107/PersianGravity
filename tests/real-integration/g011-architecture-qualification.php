<?php
/**
 * G-011 qualification-only: authentic host context and callback effects.
 * No production adapter or licensed source is modified or exported.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;

$fail = static function ( string $message ): void {
	throw new RuntimeException( 'G011_ARCH_QUAL: ' . $message );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$path = $dir . '/g011-architecture-qualification.json';
if ( ! is_dir( $dir ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
	$fail( 'Missing exact licensed GF 3.1.1.1 runtime/evidence directory.' );
}
$manifest = json_decode( file_get_contents( $dir . '/g011-confirmation.json' ), true );
if ( ! is_array( $manifest ) || '3.1.1.1' !== ( $manifest['host_version'] ?? '' ) ) {
	$fail( 'Prior exact-host G011 fixture is unavailable.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
if ( ! is_array( $form ) ) {
	$fail( 'Could not retrieve authentic GF form.' );
}
$entry_id = GFAPI::add_entry( array(
	'form_id' => (int) $manifest['form_id'],
	'date_created' => '2026-03-20 20:30:00',
	'status' => 'active',
	'1' => 'qualification',
) );
if ( is_wp_error( $entry_id ) ) {
	$fail( 'Could not create disposable qualification entry.' );
}
$entry = GFAPI::get_entry( $entry_id );
if ( is_wp_error( $entry ) ) {
	$fail( 'Could not retrieve qualification entry.' );
}
$tag = '{date_created:format:Y/m/d}';
$native_date = GFCommon::replace_variables( $tag, $form, $entry, false, true, false, 'html' );
if ( ! is_string( $native_date ) || $native_date === $tag ) {
	$fail( 'Native date oracle did not replace the exact tag.' );
}
$states_original = get_option( 'pgr_modules' );
if ( ! is_array( $states_original ) || ! isset( $states_original['states'] ) ) {
	$fail( 'Invalid module state.' );
}
$states = $states_original;
$counter_key = 'pgr_g011_qualification_side_effect_counter';
$results = array();
$trace = array();
$active_spy = null;
$active_pre_confirmation = null;
$spy = static function ( $text, $hook_form ) use ( &$trace, $counter_key, $tag ) {
	$count = (int) get_option( $counter_key, 0 ) + 1;
	update_option( $counter_key, $count, false );
	$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
	$trace[] = array(
		'count' => $count,
		'selected_text' => is_array( $selected ) && 'message' === ( $selected['type'] ?? null ),
		'exact_selected_template' => is_array( $selected ) && is_string( $text ) &&
			$text === ( $selected['message'] ?? null ),
		'has_date_tag' => is_string( $text ) && false !== strpos( $text, $tag ),
		'length' => is_string( $text ) ? strlen( $text ) : null,
	);
	return $text;
};
$early = array();
$pre_confirmation = static function ( $value, $hook_form ) use ( &$early ) {
	$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
	$early[] = array(
		'native_string' => is_string( $value ),
		'selected_text' => is_array( $selected ) && 'message' === ( $selected['type'] ?? null ),
	);
	return $value;
};
$run = static function ( string $template, bool $enabled, bool $mutate = false ) use (
	$form, $entry, $states, $spy, $pre_confirmation, &$trace, &$early, $counter_key, $fail
): array {
	$updated = $states;
	$updated['states']['jalali_presentation'] = $enabled;
	update_option( 'pgr_modules', $updated );
	update_option( $counter_key, 0, false );
	$trace = array();
	$early = array();
	$modified = $form;
	$modified['confirmations'] = array(
		'g011-architecture-case' => array(
			'id' => 'g011-architecture-case',
			'name' => 'G011 Architecture Case',
			'isActive' => true,
			'isDefault' => true,
			'type' => 'message',
			'message' => $template,
			'disableAutoformat' => true,
		),
	);
	$mutating = static function ( $text ) use ( $counter_key ) {
		return str_replace( 'ARCH_CALLBACK_SENTINEL', 'ARCH_CALLBACK_SENTINEL_' .
			(int) get_option( $counter_key, 0 ), $text );
	};
	add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 2 );
	if ( $mutate ) {
		add_filter( 'gform_pre_replace_merge_tags', $mutating, 999, 1 );
	}
	add_filter( 'gform_confirmation', $pre_confirmation, 5, 2 );
	try {
		$output = GFFormDisplay::handle_confirmation( $modified, $entry, false );
	} finally {
		remove_filter( 'gform_confirmation', $pre_confirmation, 5 );
		if ( $mutate ) {
			remove_filter( 'gform_pre_replace_merge_tags', $mutating, 999 );
		}
		remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
	}
	if ( ! is_string( $output ) ) {
		$fail( 'Native handle_confirmation did not return Text.' );
	}
	return array(
		'output' => $output,
		'invocations' => (int) get_option( $counter_key, 0 ),
		'trace' => $trace,
		'early' => $early,
	);
};

$cases = array(
	'zero' => '<strong>Zero eligible tags.</strong>',
	'one' => '<strong>Created=' . $tag . '</strong>',
	'multiple' => '<strong>Created=' . $tag . '</strong> <em>Again=' . $tag . '</em>',
	'opaque_only' => 'receipt-' . $tag . '.pdf',
	'bypass_mixed' => '<div><strong>Created=' . $tag . '</strong> ' .
		'receipt-' . $tag . '.tar.gz report_' . $tag . '-v2.pdf report_' .
		$tag . '_final.pdf <a href="/archive/' . $tag . '">Native link</a></div>',
	'malformed' => '<div><em>Created=' . $tag . '</div>',
);
try {
	foreach ( $cases as $name => $template ) {
		$native = $run( $template, false );
		$enabled = $run( $template, true );
		$results[ $name ] = array(
			'native_invocations' => $native['invocations'],
			'enabled_invocations' => $enabled['invocations'],
			'native_equals_enabled' => $native['output'] === $enabled['output'],
			'native_trace' => $native['trace'],
			'enabled_trace' => $enabled['trace'],
			'early_enabled' => $enabled['early'],
		);
		if ( 'bypass_mixed' === $name ) {
			$tokens = array(
				'receipt-' . $native_date . '.tar.gz',
				'report_' . $native_date . '-v2.pdf',
				'report_' . $native_date . '_final.pdf',
			);
			$observed = array();
			foreach ( $tokens as $i => $token ) {
				$observed[ 'machine_' . $i ] = array(
					'native_has' => false !== strpos( $native['output'], $token ),
					'enabled_has' => false !== strpos( $enabled['output'], $token ),
				);
			}
			$observed['human_native'] = false !== strpos( $native['output'], '<strong>Created=' . $native_date . '</strong>' );
			$observed['human_jalali'] = false !== strpos( $enabled['output'], '<strong>Created=۱۴۰۵/۰۱/۰۱</strong>' );
			$results[ $name ]['oracle'] = $observed;
		}
	}
	$mutable_template = 'ARCH_CALLBACK_SENTINEL Created=' . $tag;
	$native_mutation  = $run( $mutable_template, false, true );
	$enabled_mutation = $run( $mutable_template, true, true );
	$results['non_idempotent'] = array(
		'native_invocations' => $native_mutation['invocations'],
		'enabled_invocations' => $enabled_mutation['invocations'],
		'host_output_preserved' => $native_mutation['output'] === $enabled_mutation['output'],
		'persistent_effects_after_fallback' => $enabled_mutation['invocations'] > $native_mutation['invocations'],
		'native_trace' => $native_mutation['trace'],
		'enabled_trace' => $enabled_mutation['trace'],
	);

	// A bounded same-level method trial. Temporarily remove the production
	// adapter ONLY in this disposable CLI fixture; test a selected-Text
	// pre-render marker and a post-render presentation hook, without a
	// second native render. This does not qualify a general classifier.
	$prototype = '<div><strong>Created=' . $tag . '</strong> receipt-' .
		$tag . '.tar.gz <em>Again=' . $tag . '</em></div>';
	$baseline = $run( $prototype, false );
	$proto_form = $form;
	$proto_form['confirmations'] = array(
		'prototype' => array(
			'id' => 'prototype', 'name' => 'Prototype', 'type' => 'message',
			'isActive' => true, 'isDefault' => true,
			'message' => $prototype, 'disableAutoformat' => true,
		),
	);
	$proto_calls = 0;
	$proto_phase_hits = 0;
	$proto_post_hits = 0;
	$marker = 'PGRQ_ARCH_TRIAL_OPAQUE_8F21_END';
	$proto_pre = static function ( $text, $hook_form ) use ( $prototype, $tag, $marker, &$proto_phase_hits ) {
		$selected = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
		if ( is_array( $selected ) && 'message' === ( $selected['type'] ?? null ) &&
			$text === $prototype && $text === ( $selected['message'] ?? null ) &&
			false === strpos( $text, $marker ) ) {
			++$proto_phase_hits;
			return str_replace( 'Created=' . $tag, 'Created=' . $marker, $text );
		}
		return $text;
	};
	$proto_post = static function ( $confirmation, $hook_form ) use ( $marker, &$proto_post_hits ) {
		if ( is_string( $confirmation ) && 'message' === ( $hook_form['confirmation']['type'] ?? null ) ) {
			++$proto_post_hits;
			return str_replace( $marker, '۱۴۰۵/۰۱/۰۱', $confirmation );
		}
		return $confirmation;
	};
	$proto_counter = static function ( $text ) use ( &$proto_calls ) {
		++$proto_calls;
		return $text;
	};
	$adapter_callback = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
	$adapter_priority = has_filter( 'gform_confirmation', $adapter_callback );
	if ( false === $adapter_priority ) {
		$fail( 'Cannot isolate existing post-render adapter for method trial.' );
	}
	remove_filter( 'gform_confirmation', $adapter_callback, $adapter_priority );
	add_filter( 'gform_pre_replace_merge_tags', $proto_pre, 5, 2 );
	add_filter( 'gform_pre_replace_merge_tags', $proto_counter, 998, 1 );
	add_filter( 'gform_confirmation', $proto_post, 25, 2 );
	try {
		$proto_copy = $proto_form;
		$prototype_output = GFFormDisplay::handle_confirmation( $proto_copy, $entry, false );
	} finally {
		remove_filter( 'gform_confirmation', $proto_post, 25 );
		remove_filter( 'gform_pre_replace_merge_tags', $proto_counter, 998 );
		remove_filter( 'gform_pre_replace_merge_tags', $proto_pre, 5 );
		add_filter( 'gform_confirmation', $adapter_callback, $adapter_priority, 4 );
	}
	$prototype_expected = str_replace(
		array( 'Created=' . $native_date, 'Again=' . $native_date ),
		array( 'Created=۱۴۰۵/۰۱/۰۱', 'Again=' . $native_date ),
		$baseline['output']
	);
	$method_trial = array(
		'host_native_calls' => $baseline['invocations'],
		'marker_method_calls' => $proto_calls,
		'pre_selected_hits' => $proto_phase_hits,
		'post_selected_hits' => $proto_post_hits,
		'expected_output_match' => $prototype_expected === $prototype_output,
		'machine_native_preserved' => is_string( $prototype_output ) &&
			false !== strpos( $prototype_output, 'receipt-' . $native_date . '.tar.gz' ),
	);

	// The Merge Tag prehook is shared. Exact selected-form metadata can be
	// carried in an arbitrary API caller's form object; it is not, alone,
	// proof of confirmation-only invocation provenance.
	$shared_context = array();
	$observe_shared = static function ( $text, $hook_form ) use ( &$shared_context ) {
		$sel = is_array( $hook_form ) ? ( $hook_form['confirmation'] ?? null ) : null;
		$shared_context[] = array(
			'has_selected_message' => is_array( $sel ) && 'message' === ( $sel['type'] ?? null ),
			'equals_selected_template' => is_array( $sel ) && $text === ( $sel['message'] ?? null ),
		);
		return $text;
	};
	add_filter( 'gform_pre_replace_merge_tags', $observe_shared, 1, 2 );
	try {
		$unselected_form = $form;
		unset( $unselected_form['confirmation'] );
		GFCommon::replace_variables( $prototype, $unselected_form, $entry, false, true, false, 'html' );
		$provided_form = $form;
		$provided_form['confirmation'] = $proto_form['confirmations']['prototype'];
		GFCommon::replace_variables( $prototype, $provided_form, $entry, false, true, false, 'html' );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $observe_shared, 1 );
	}
	$method_trial['shared_pre_hook_direct_calls'] = $shared_context;
	$results['same_level_method_trial'] = $method_trial;
	if (
		1 !== $proto_calls || 1 !== $proto_phase_hits || 1 !== $proto_post_hits ||
		! $method_trial['expected_output_match'] || ! $method_trial['machine_native_preserved'] ||
		count( $shared_context ) < 2 ||
		$shared_context[0]['has_selected_message'] ||
		! $shared_context[1]['equals_selected_template']
	) {
		$fail( 'Bounded pre-marker/post-presentation method trial did not conform; inspect artifact.' );
	}

	$evidence = array(
		'qualification' => 'G011_ARCH_EXACT_HOST_V1',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'host_version' => GFForms::$version,
		'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
		'native_date' => $native_date,
		'cases' => $results,
		'seams' => array(
			'gform_confirmation' => has_filter( 'gform_confirmation', array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' ) ),
			'handle_confirmation' => ( new ReflectionMethod( 'GFFormDisplay', 'handle_confirmation' ) )->getDeclaringClass()->getName(),
			'get_confirmation_message' => ( new ReflectionMethod( 'GFFormDisplay', 'get_confirmation_message' ) )->isPublic(),
			'replace_variables' => ( new ReflectionMethod( 'GFCommon', 'replace_variables' ) )->isPublic(),
		),
	);
	file_put_contents( $path, wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$expected = array( 'zero' => array( 1, 1 ), 'one' => array( 1, 3 ), 'multiple' => array( 1, 4 ),
		'opaque_only' => array( 1, 1 ), 'malformed' => array( 1, 1 ) );
	foreach ( $expected as $name => $counts ) {
		if ( $results[ $name ]['native_invocations'] !== $counts[0] ||
			$results[ $name ]['enabled_invocations'] !== $counts[1] ) {
			$fail( 'Invocation model differs on ' . $name . '; consult bounded artifact.' );
		}
	}
	foreach ( array( 'machine_0', 'machine_1', 'machine_2' ) as $token ) {
		if ( ! $results['bypass_mixed']['oracle'][ $token ]['native_has'] ||
			$results['bypass_mixed']['oracle'][ $token ]['enabled_has'] ) {
			$fail( 'Expected representation bypass not observed at ' . $token . '.' );
		}
	}
	if ( ! $results['bypass_mixed']['oracle']['human_native'] ||
		! $results['bypass_mixed']['oracle']['human_jalali'] ||
		! $results['non_idempotent']['host_output_preserved'] ||
		! $results['non_idempotent']['persistent_effects_after_fallback'] ) {
		$fail( 'Human presentation or callback fallback observation disagrees.' );
	}
	echo "G011 architecture qualification: observed host bypass and callback side effects\n";
} finally {
	update_option( 'pgr_modules', $states_original );
	delete_option( $counter_key );
}
