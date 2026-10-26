<?php
/**
 * Real GF 3.1.1.1 test-only heterogeneous candidate qualification.
 * Matched native/candidate are separate independent renders; the
 * candidate NEVER renders again or calls a shared replacement hook.
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/g011-heterogeneous-candidate.php';
$fail = static function ( string $message ) { throw new RuntimeException( 'G011_HET_METHOD: ' . $message ); };
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$baseline = json_decode( (string) file_get_contents( $dir . '/g011-heterogeneous-native-host.json' ), true );
if ( ! is_array( $baseline ) || 'PASS' !== $baseline['status'] ||
	GFForms::$version !== '3.1.1.1' ||
	$baseline['GF_SHA256'] !== getenv( 'WU008_GF_SHA256' ) ) {
	$fail( 'Independent native-host discriminator did not pass' );
}
$form = GFAPI::get_form( (int) $baseline['form_id'] );
if ( ! is_array( $form ) ) { $fail( 'Baseline persisted Form unavailable' ); }
$form_sha = hash( 'sha256', wp_json_encode( $form ) );
$prod = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
$priority = has_filter( 'gform_confirmation', $prod );
if ( false === $priority || (int) $priority !== 20 ) { $fail( 'Production priority20 seam drift' ); }
remove_filter( 'gform_confirmation', $prod, $priority );
$results = array();
try {
	foreach ( $baseline['cases'] as $name => $data ) {
		$entry_id = (int) $data['entry_id'];
		$entry = GFAPI::get_entry( $entry_id );
		if ( ! is_array( $entry ) ) { $fail( 'Baseline entry missing' ); }
		$entry_sha = hash( 'sha256', wp_json_encode( $entry ) );
		$runs = array();
		foreach ( array( 'native', 'candidate' ) as $mode ) {
			$trace = array();
			$shared = array();
			$side_effects = 0;
			$last_result = null;
			$shared_callback = static function ( $text, $selected_form ) use ( &$shared, $baseline ) {
				if ( (int) ( $selected_form['id'] ?? 0 ) === (int) $baseline['form_id'] ) {
					$shared[] = array( 'input_sha256' => hash( 'sha256', (string) $text ),
						'form_id' => (int) $selected_form['id'] );
				}
				return $text;
			};
			$before = static function ( $html, $selected_form ) use ( &$trace, &$side_effects, $baseline ) {
				if ( (int) ( $selected_form['id'] ?? 0 ) !== (int) $baseline['form_id'] ) {
					return $html;
				}
				++$side_effects;
				$trace[] = array( 'priority' => 10, 'input_sha256' => hash( 'sha256', (string) $html ) );
				return '<span class="external-prefix">THIRD_PARTY_VISIBLE</span>' . $html;
			};
			$tie_before = static function ( $html, $selected_form ) use ( &$trace, $baseline ) {
				if ( (int) ( $selected_form['id'] ?? 0 ) === (int) $baseline['form_id'] ) {
					$trace[] = array( 'priority' => '20-before',
						'sha256' => hash( 'sha256', (string) $html ) );
				}
				return $html;
			};
			$candidate = static function ( $html, $selected_form, $selected_entry ) use (
				&$last_result, &$trace, $baseline
			) {
				if ( (int) ( $selected_form['id'] ?? 0 ) !== (int) $baseline['form_id'] ) {
					return $html;
				}
				$trace[] = array( 'priority' => '20-candidate',
					'input_sha256' => hash( 'sha256', (string) $html ) );
				$last_result = WU008_G011_Heterogeneous_Alignment::align(
					$html, $selected_form, $selected_entry
				);
				return $last_result['output'];
			};
			$tie_after = static function ( $html, $selected_form ) use ( &$trace, $baseline ) {
				if ( (int) ( $selected_form['id'] ?? 0 ) === (int) $baseline['form_id'] ) {
					$trace[] = array( 'priority' => '20-after',
						'sha256' => hash( 'sha256', (string) $html ),
						'contains_jalali_full' => str_contains( (string) $html, '۱۴۰۵/۰۱/۰۱' ) );
				}
				return $html;
			};
			$after = static function ( $html, $selected_form ) use ( &$trace, $baseline ) {
				if ( (int) ( $selected_form['id'] ?? 0 ) === (int) $baseline['form_id'] ) {
					$trace[] = array( 'priority' => 30,
						'sha256' => hash( 'sha256', (string) $html ),
						'contains_jalali_full' => str_contains( (string) $html, '۱۴۰۵/۰۱/۰۱' ) );
				}
				return $html;
			};
			add_filter( 'gform_pre_replace_merge_tags', $shared_callback, 999, 2 );
			add_filter( 'gform_confirmation', $before, 10, 2 );
			add_filter( 'gform_confirmation', $tie_before, 20, 2 );
			if ( 'candidate' === $mode ) {
				add_filter( 'gform_confirmation', $candidate, 20, 3 );
			}
			add_filter( 'gform_confirmation', $tie_after, 20, 2 );
			add_filter( 'gform_confirmation', $after, 30, 2 );
			try {
				$selected_form = $form;
				$html = GFFormDisplay::handle_confirmation( $selected_form, $entry, false );
				$selected_id = $selected_form['confirmation']['id'] ?? null;
			} finally {
				remove_filter( 'gform_pre_replace_merge_tags', $shared_callback, 999 );
				remove_filter( 'gform_confirmation', $before, 10 );
				remove_filter( 'gform_confirmation', $tie_before, 20 );
				if ( 'candidate' === $mode ) {
					remove_filter( 'gform_confirmation', $candidate, 20 );
				}
				remove_filter( 'gform_confirmation', $tie_after, 20 );
				remove_filter( 'gform_confirmation', $after, 30 );
			}
			$runs[$mode] = array(
				'output' => $html, 'selected' => $selected_id,
				'trace' => $trace, 'shared' => $shared,
				'side_effects' => $side_effects, 'alignment' => $last_result,
				// GF may enrich transient GFAPI objects during native
				// confirmation handling; compare persisted business-owned
				// values rather than treating all runtime metadata as storage.
				'entry_unchanged' => (static function ( $stored ) use ( $entry ) {
					if ( ! is_array( $stored ) ) { return false; }
					foreach ( array( 'id', 'form_id', 'status', 'date_created', '1', '2' ) as $key ) {
						if ( (string) ( $stored[$key] ?? '' ) !== (string) ( $entry[$key] ?? '' ) ) {
							return false;
						}
					}
					return true;
				})( GFAPI::get_entry( $entry_id ) ),
				'form_unchanged' => (static function ( $stored ) use ( $form ) {
					return is_array( $stored ) &&
						(string) $stored['id'] === (string) $form['id'] &&
						$stored['confirmations'] === $form['confirmations'] &&
						count( $stored['fields'] ) === count( $form['fields'] ) &&
						array_map( static function ( $v ) { return (int) $v->id; }, $stored['fields'] ) ===
						array_map( static function ( $v ) { return (int) $v->id; }, $form['fields'] );
				})( GFAPI::get_form( (int) $baseline['form_id'] ) ),
			);
		}
		$n = $runs['native'];
		$c = $runs['candidate'];
		$alignment = $c['alignment'];
		$spans = $alignment['spans'] ?? array();
		$reconstructed = '';
		$cursor = 0;
		foreach ( $spans as $span ) {
			$reconstructed .= substr( $n['output'], $cursor, $span['start'] - $cursor ) .
				esc_html( $span['jalali'] );
			$cursor = $span['end'];
		}
		$reconstructed .= substr( $n['output'], $cursor );
		$read_part = static function ( string $html, string $tag ): ?string {
			preg_match( '~<' . $tag . '>(.*?)</' . $tag . '>~s', $html, $m );
			return $m[1] ?? null;
		};
		$checks = array(
			'selected_identity' => $n['selected'] === 'g011-het-' . $name &&
				$n['selected'] === $c['selected'],
			'typed_source_three_nonempty_spans' =>
				true === ( $alignment['admitted'] ?? null ) && count( $spans ) === 3,
			'typed_source_distinct_output_shapes' =>
				count( array_unique( array_column( $spans, 'native' ) ) ) === 3,
			'full_date_jalali' => '۱۴۰۵/۰۱/۰۱' === $read_part( $c['output'], 'strong' ),
			'date_component_jalali' => '۱۴۰۵' === $read_part( $c['output'], 'b' ),
			'datetime_jalali' => is_string( $read_part( $c['output'], 'em' ) ) &&
				str_contains( $read_part( $c['output'], 'em' ), '۱۴۰۵/۰۱/۰۱' ) &&
				str_contains( $read_part( $c['output'], 'em' ), '12:00 ق.ظ' ),
			'unsupported_raw_native' => $read_part( $n['output'], 'i' ) ===
				$read_part( $c['output'], 'i' ),
			'machine_opaque_native' =>
				str_contains( $c['output'], 'receipt-2026/03/21.pdf' ) &&
				str_contains( $c['output'], 'data-greg="2026/03/21"' ) &&
				str_contains( $c['output'], '/?d=2026/03/21&amp;keep=1' ),
			'third_party_prefix_exact' =>
				str_contains( $n['output'], 'THIRD_PARTY_VISIBLE' ) &&
				str_contains( $c['output'], 'THIRD_PARTY_VISIBLE' ),
			'outside_spans_byte_exact' => $reconstructed === $c['output'],
			'single_shared_callback_per_run' => count( $n['shared'] ) === 1 &&
				count( $c['shared'] ) === 1 &&
				$n['shared'] === $c['shared'],
			'third_party_side_effect_once' => $n['side_effects'] === 1 &&
				$c['side_effects'] === 1,
			'callbacks_exact_order_and_tie_composition' =>
				array_column( $n['trace'], 'priority' ) === array( 10, '20-before', '20-after', 30 ) &&
				array_column( $c['trace'], 'priority' ) ===
					array( 10, '20-before', '20-candidate', '20-after', 30 ),
			'post_adapter_jalali_visible' => true ===
				( $c['trace'][3]['contains_jalali_full'] ?? false ) &&
				true === ( $c['trace'][4]['contains_jalali_full'] ?? false ) &&
				$n['trace'][1]['sha256'] === $c['trace'][1]['sha256'],
			'form_entry_unchanged' => $n['entry_unchanged'] && $c['entry_unchanged'] &&
				$n['form_unchanged'] && $c['form_unchanged'],
		);
		$failed = array_keys( array_filter( $checks, static function ( $passed ) { return ! $passed; } ) );
		$results[$name] = array(
			'status' => $failed ? 'FAIL' : 'PASS', 'checks' => $checks,
			'failed_invariants' => $failed,
			'selected_confirmation' => $n['selected'],
			'source_utc' => $entry['date_created'], 'date_field' => $entry['2'],
			'spans' => $spans, 'reason' => $alignment['reason'] ?? null,
			'native_parts' => array(
				'full' => $read_part( $n['output'], 'strong' ),
				'datetime' => $read_part( $n['output'], 'em' ),
				'year' => $read_part( $n['output'], 'b' ),
				'raw' => $read_part( $n['output'], 'i' ),
			),
			'candidate_parts' => array(
				'full' => $read_part( $c['output'], 'strong' ),
				'datetime' => $read_part( $c['output'], 'em' ),
				'year' => $read_part( $c['output'], 'b' ),
				'raw' => $read_part( $c['output'], 'i' ),
			),
			'native_sha256' => hash( 'sha256', (string) $n['output'] ),
			'candidate_sha256' => hash( 'sha256', (string) $c['output'] ),
			'callback_priority_order_native' => array_column( $n['trace'], 'priority' ),
			'callback_priority_order_candidate' => array_column( $c['trace'], 'priority' ),
			'callback_pre_equal' => $n['shared'] === $c['shared'],
			'side_effects_native' => $n['side_effects'],
			'side_effects_candidate' => $c['side_effects'],
		);
		if ( $failed ) { break; }
	}
} finally {
	add_filter( 'gform_confirmation', $prod, $priority, 4 );
}
$passed = count( $results ) === 2 &&
	! array_filter( $results, static function ( $c ) { return 'PASS' !== $c['status']; } );
file_put_contents( $dir . '/g011-heterogeneous-method.json',
	wp_json_encode( array(
		'status' => $passed ? 'PASS' : 'FAIL',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'GF' => GFForms::$version,
		'GF_SHA256' => getenv( 'WU008_GF_SHA256' ),
		'cases' => $results,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);
if ( ! $passed ) { $fail( 'Discriminating heterogeneous candidate failed' ); }
echo "G011 typed heterogeneous selected Default+Conditional PASS\n";
