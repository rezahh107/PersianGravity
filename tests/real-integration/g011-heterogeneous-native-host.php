<?php
/**
 * Exact GF 3.1.1.1 discriminating native-host baseline.
 * Test only: native render ONCE per selected Confirmation, no merge reprocessing.
 */
defined( 'ABSPATH' ) || exit;
$fail = static function ( string $why ): void { throw new RuntimeException( 'G011_HETEROGENEOUS_NATIVE: ' . $why ); };
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_dir( $dir ) || GFForms::$version !== '3.1.1.1' ||
	getenv( 'WU008_GF_SHA256' ) !== '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' ||
	'fa_IR' !== determine_locale() ) {
	$fail( 'Locked host or locale missing' );
}
update_option( 'timezone_string', 'Asia/Tehran' );
$form_id = GFAPI::add_form( array(
	'title' => 'Disposable G011 Heterogeneous Method Qualification',
	'fields' => array(
		array( 'id' => 1, 'type' => 'text', 'label' => 'Selection' ),
		array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
) );
if ( ! is_int( $form_id ) ) { $fail( 'GFAPI add form failed' ); }
$form = GFAPI::get_form( $form_id );
$tags = array(
	'{date_created:format:Y/m/d}',
	'{date_created:time:format:Y/m/d}',
	'{Visit:2:year}',
	'{date_created:raw}',
);
$template = '<div class="hetero-g011">' .
	'<p>Submitted <strong>' . $tags[0] . '</strong></p>' .
	'<p>Recorded <em>' . $tags[1] . '</em></p>' .
	'<p>Visit year <b>' . $tags[2] . '</b></p>' .
	'<p>Native raw <i>' . $tags[3] . '</i></p>' .
	'<p>Keep <code>receipt-' . $tags[0] . '.pdf</code></p>' .
	'<a href="/?d=' . $tags[0] . '&amp;keep=1" data-greg="' . $tags[0] . '">Transport</a>' .
	'</div>';
$form['confirmations'] = array(
	'g011-het-default' => array(
		'id' => 'g011-het-default', 'name' => 'Default Text', 'isDefault' => true,
		'isActive' => true, 'type' => 'message', 'message' => $template, 'disableAutoformat' => true,
	),
	'g011-het-conditional' => array(
		'id' => 'g011-het-conditional', 'name' => 'Conditional Text', 'isDefault' => false,
		'isActive' => true, 'type' => 'message', 'message' => $template,
		'disableAutoformat' => true,
		'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ),
		),
	),
);
if ( true !== GFAPI::update_form( $form ) ) { $fail( 'GFAPI update form failed' ); }
$form = GFAPI::get_form( $form_id );
$ids = array_column( array_values( $form['confirmations'] ), 'id' );
if ( count( $ids ) !== 2 || ! in_array( 'g011-het-conditional', $ids, true ) ) {
	$fail( 'Persisted conditional Confirmation metadata missing' );
}
$adapter = array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' );
$old = has_filter( 'gform_confirmation', $adapter );
if ( false === $old ) { $fail( 'Expected production adapter registered' ); }
remove_filter( 'gform_confirmation', $adapter, $old );
$observations = array();
$pre = array();
$pre_hook = static function ( $text, $selected_form ) use ( &$pre, $form_id ) {
	if ( (int) ( $selected_form['id'] ?? 0 ) === $form_id ) {
		$pre[] = hash( 'sha256', (string) $text );
	}
	return $text;
};
$before = static function ( $html, $selected_form ) use ( $form_id, &$observations ) {
	if ( (int) ( $selected_form['id'] ?? 0 ) === $form_id ) {
		$observations[] = array( 'priority' => 10, 'sha256' => hash( 'sha256', (string) $html ) );
		return '<span class="external-prefix">THIRD_PARTY_VISIBLE</span>' . $html;
	}
	return $html;
};
$after = static function ( $html, $selected_form ) use ( $form_id, &$observations ) {
	if ( (int) ( $selected_form['id'] ?? 0 ) === $form_id ) {
		$observations[] = array( 'priority' => 30, 'sha256' => hash( 'sha256', (string) $html ) );
	}
	return $html;
};
add_filter( 'gform_pre_replace_merge_tags', $pre_hook, 999, 2 );
add_filter( 'gform_confirmation', $before, 10, 2 );
add_filter( 'gform_confirmation', $after, 30, 2 );
$cases = array();
try {
	foreach ( array( 'default' => 'normal', 'conditional' => 'special' ) as $name => $selection ) {
		$entry_id = GFAPI::add_entry( array(
			'form_id' => $form_id, 'status' => 'active',
			'date_created' => '2026-03-20 20:30:00',
			'1' => $selection, '2' => '03/21/2026',
		) );
		if ( ! is_int( $entry_id ) ) { $fail( 'Native Entry persistence failed' ); }
		$entry = GFAPI::get_entry( $entry_id );
		$start_obs = count( $observations );
		$start_pre = count( $pre );
		$native_form = $form;
		$result = GFFormDisplay::handle_confirmation( $native_form, $entry, false );
		$selected = $native_form['confirmation']['id'] ?? null;
		$utc = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $entry['date_created'], new DateTimeZone( 'UTC' ) );
		$local = $utc->setTimezone( wp_timezone() );
		$expected_full = $local->format( 'Y/m/d' );
		$expected_year = '2026';
		$pieces = array();
		foreach ( array( 'full' => 'strong', 'datetime' => 'em', 'date_component' => 'b', 'native_fallback' => 'i' ) as $key => $element ) {
			preg_match( '~<' . $element . '>(.*?)</' . $element . '>~s', (string) $result, $match );
			$pieces[$key] = $match[1] ?? null;
		}
		$check = array(
			'selected_identity' => $selected === 'g011-het-' . $name,
			'full_native' => $pieces['full'] === $expected_full,
			'date_component_native' => $pieces['date_component'] === $expected_year,
			'datetime_native_nonempty' => is_string( $pieces['datetime'] ) && str_contains( $pieces['datetime'], $expected_full ),
			'raw_fallback_nonempty' => is_string( $pieces['native_fallback'] ) && $pieces['native_fallback'] !== '',
			'single_shared_native_processing' => count( $pre ) - $start_pre === 1,
			'third_party_order' => array_column( array_slice( $observations, $start_obs ), 'priority' ) === array( 10, 30 ),
			'persisted_form_unchanged' => GFAPI::get_form( $form_id ) === $form,
			'entry_unchanged' => GFAPI::get_entry( $entry_id ) === $entry,
		);
		$cases[$name] = array(
			'entry_id' => $entry_id, 'selected' => $selected,
			'expected_utc' => $entry['date_created'],
			'expected_native_full' => $expected_full, 'expected_native_year' => $expected_year,
			'native_parts' => $pieces,
			'checks' => $check, 'failed' => array_keys( array_filter( $check, static function ( $v ) { return ! $v; } ) ),
			'full_html_sha256' => hash( 'sha256', (string) $result ),
		);
		if ( $cases[$name]['failed'] ) { break; }
	}
} finally {
	remove_filter( 'gform_pre_replace_merge_tags', $pre_hook, 999 );
	remove_filter( 'gform_confirmation', $before, 10 );
	remove_filter( 'gform_confirmation', $after, 30 );
	add_filter( 'gform_confirmation', $adapter, $old, 4 );
}
$passed = count( $cases ) === 2 && ! array_filter( $cases, static function ( $c ) { return $c['failed']; } );
file_put_contents( $dir . '/g011-heterogeneous-native-host.json',
	wp_json_encode( array(
		'status' => $passed ? 'PASS' : 'FAIL', 'head' => getenv( 'WU008_PGR_SHA' ),
		'GF' => GFForms::$version, 'GF_SHA256' => getenv( 'WU008_GF_SHA256' ),
		'form_id' => $form_id, 'cases' => $cases,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);
if ( ! $passed ) { $fail( 'Independent native-host prerequisites not satisfied' ); }
echo "G011 heterogeneous native-host baseline PASS\n";