<?php
/**
 * Isolated Scanner same-ID recreation qualification. Test-only; never run on live WordPress.
 * All mutations use the actual product Registry and genuine GFAPI.
 */
defined( 'ABSPATH' ) || exit;

$root   = getenv( 'WU008_WP_PATH' );
$dir    = getenv( 'WU008_ARTIFACT_DIR' );
$action = getenv( 'WU008_INCAR_ACTION' );
$case   = getenv( 'WU008_INCAR_CASE' );
$cases  = array( 'unchanged', 'missing', 'changed_label', 'same_label', 'enabled_only', 'output_label_only', 'fresh_b', 'identical_content', 'interleaved_replace' );
$steps  = array( 'create_a', 'delete_a', 'recreate_b', 'edit_a', 'inspect', 'cleanup' );
if (
	! $root || ! $dir || realpath( ABSPATH ) !== realpath( $root ) ||
	! in_array( $case, $cases, true ) || ! in_array( $action, $steps, true ) ||
	! class_exists( 'PGR_Scanner_Profile_Registry' ) || ! class_exists( 'GFAPI' )
) {
	throw new RuntimeException( 'Isolated WU008 Registry/GFAPI qualification required.' );
}
$id   = 'alpha';
$file = $dir . '/scanner-incarnation-' . $case . '-fixture.json';
$a    = array(
	'id'      => $id,
	'label'   => 'Alpha',
	'enabled' => true,
	'parser'  => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
	'outputs' => array( array( 'key' => 'original', 'label' => 'Original', 'required' => true ) ),
);
$b = $a;
$b['parser']['trim']             = false;
$b['parser']['normalize_digits'] = false;
$b['outputs']                    = array( array( 'key' => 'replacement', 'label' => 'Replacement', 'required' => true ) );
$b['label']                      = 'changed_label' === $case ? 'Beta' : 'Alpha';
if ( 'enabled_only' === $case ) {
	$b               = $a;
	$b['enabled']     = false;
} elseif ( 'identical_content' === $case ) {
	$b = $a;
} elseif ( 'output_label_only' === $case ) {
	$b                        = $a;
	$b['outputs'][0]['label'] = 'Replacement output label';
}

$registry = static function () use ( $id ) {
	$p = PGR_Scanner_Profile_Registry::get( $id );
	if ( null === $p ) {
		return array( 'exists' => false );
	}
	return array(
		'exists'    => true,
		'id'        => $p['id'],
		'label'     => $p['label'],
		'trim'      => $p['parser']['trim'],
		'normalize' => $p['parser']['normalize_digits'],
		'enabled'   => (bool) $p['enabled'],
		'outputs'   => $p['outputs'],
		'keys'      => PGR_Scanner_Profile_Registry::output_keys( $id ),
	);
};
$assert_definition = static function ( $expected ) use ( $id, $registry ) {
	$actual = $registry();
	if ( ! $actual['exists'] || $actual['label'] !== $expected['label'] ||
		$actual['trim'] !== $expected['parser']['trim'] ||
		$actual['normalize'] !== $expected['parser']['normalize_digits'] ||
		$actual['enabled'] !== (bool) $expected['enabled'] ||
		$actual['outputs'] !== array( array(
			'key'      => $expected['outputs'][0]['key'],
			'label'    => $expected['outputs'][0]['label'],
			'required' => (bool) $expected['outputs'][0]['required'],
		) ) ||
		$actual['keys'] !== array( $expected['outputs'][0]['key'] ) ) {
		throw new RuntimeException( 'Persisted Registry definition mismatch for ' . $id );
	}
};
if ( 'create_a' === $action ) {
	if ( null !== PGR_Scanner_Profile_Registry::get( $id ) || is_file( $file ) ) {
		throw new RuntimeException( 'Fixture collision before A creation.' );
	}
	$created = PGR_Scanner_Profile_Registry::save_custom_profile( $a );
	if ( ! $created['success'] ) {
		throw new RuntimeException( 'Real Registry could not create A: ' . wp_json_encode( $created['errors'] ) );
	}
	$assert_definition( $a );
	$form_id = GFAPI::add_form(
		array(
			'title'  => 'WU008 same-ID deletion qualification ' . $case,
			'fields' => array(
				array( 'id' => 1, 'label' => 'Scanner', 'type' => 'pgr_structured_scanner', 'scanner_profile' => $id, 'scanner_mappings' => array( 'original' => '2' ) ),
				array( 'id' => 2, 'label' => 'Destination', 'type' => 'text' ),
			),
			'button' => array( 'type' => 'text', 'text' => 'Submit' ),
		)
	);
	if ( is_wp_error( $form_id ) ) {
		throw new RuntimeException( 'Genuine mapped GF form failed: ' . $form_id->get_error_message() );
	}
	$form = GFAPI::get_form( $form_id );
	if ( ! $form || $form['fields'][0]->scanner_profile !== $id ||
		$form['fields'][0]->scanner_mappings['original'] !== '2' ) {
		throw new RuntimeException( 'GFAPI did not persist the original mapped form.' );
	}
	file_put_contents( $file, wp_json_encode( array(
		'form_id'      => $form_id,
		'field_before' => wp_json_encode( $form['fields'][0] ),
	) ) );
} elseif ( 'delete_a' === $action ) {
	$assert_definition( $a );
	if ( ! PGR_Scanner_Profile_Registry::delete_custom_profile( $id, PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( $id ) ) ) || null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
		throw new RuntimeException( 'Independent legitimate Registry deletion of A failed.' );
	}
} elseif ( 'recreate_b' === $action ) {
	if ( null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
		throw new RuntimeException( 'Recreate attempted over existing profile.' );
	}
	$result = PGR_Scanner_Profile_Registry::save_custom_profile( $b );
	if ( ! $result['success'] ) {
		throw new RuntimeException( 'Registry refused valid B creation: ' . wp_json_encode( $result['errors'] ) );
	}
	$assert_definition( $b );
} elseif ( 'edit_a' === $action ) {
	if ( ! in_array( $case, array( 'enabled_only', 'output_label_only' ), true ) ) {
		throw new RuntimeException( 'Unexpected in-place mutation scenario.' );
	}
	$assert_definition( $a );
	$current = PGR_Scanner_Profile_Registry::get( $id );
	$result = PGR_Scanner_Profile_Registry::save_custom_profile( $b, $id, $current['revision'], $current['incarnation_id'] );
	if ( ! $result['success'] ) {
		throw new RuntimeException( 'Registry refused valid in-place metadata change: ' . wp_json_encode( $result['errors'] ) );
	}
	$assert_definition( $b );
} elseif ( 'cleanup' === $action ) {
	if ( null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
		if ( ! PGR_Scanner_Profile_Registry::delete_custom_profile( $id ) ) {
			throw new RuntimeException( 'Cleanup failed to remove test-only alpha.' );
		}
	}
} elseif ( ! is_readable( $file ) ) {
	throw new RuntimeException( 'Missing real persisted GF fixture.' );
}
$state = $registry();
if ( 'create_a' !== $action ) {
	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'Missing GFAPI control fixture.' );
	}
	$record = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
	$form   = GFAPI::get_form( $record['form_id'] );
	$state['gfapi_mapping_unchanged'] = $form && wp_json_encode( $form['fields'][0] ) === $record['field_before'];
	if ( ! $state['gfapi_mapping_unchanged'] ) {
		throw new RuntimeException( 'GFAPI mapped field was unexpectedly modified.' );
	}
}
$state['action'] = $action;
$state['case']   = $case;
echo wp_json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
