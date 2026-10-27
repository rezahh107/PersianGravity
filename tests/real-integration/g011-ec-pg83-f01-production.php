<?php
/**
 * EC-PG83-F01: real licensed GF selected-Text production-Adapter qualification.
 * Synthetic, disposable Form/Entry; no production/vendor writes.
 * Diagnostic evidence is recorded even when preservation fails.
 */
defined( 'ABSPATH' ) || exit;

( static function (): void {
	$dir = getenv( 'WU008_ARTIFACT_DIR' );
	$path = $dir . '/g011-ec-pg83-f01-production.json';
	$phase = getenv( 'WU008_F01_PHASE' ) ?: 'setup';
	if ( 'verify' === $phase ) {
		$host = is_file( $path ) ? json_decode( file_get_contents( $path ), true ) : null;
		$browser_path = $dir . '/g011-ec-pg83-f01-browser.json';
		$browser = is_file( $browser_path ) ? json_decode( file_get_contents( $browser_path ), true ) : null;
		if ( ! is_array( $host ) || ! is_array( $browser ) ||
			( $host['verdict'] ?? '' ) !== 'PASS' ||
			( $browser['verdict'] ?? '' ) !== 'PASS'
		) {
			throw new RuntimeException( 'EC-PG83-F01 production preservation failed or is NOT_PROVEN; inspect both artifacts.' );
		}
		echo "EC-PG83-F01 production licensed host and Chromium PASS\n";
		return;
	}
	if (
		'3.1.1.1' !== GFForms::$version ||
		'542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' ) ||
		20 !== has_filter( 'gform_confirmation', array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' ) )
	) {
		throw new RuntimeException( 'EC-PG83-F01 authentic licensed host/Production hook mismatch.' );
	}
	$original = get_option( 'pgr_modules' );
	if ( ! is_array( $original ) || ! isset( $original['states'] ) ) {
		throw new RuntimeException( 'EC-PG83-F01 module-state option unavailable.' );
	}
	$tag = '{date_created:format:Y/m/d}';
	$contents = '<div class="g011-f01">'
		. '<p>F01_MACHINE receipt-' . $tag . ' ./archive/report#release-' . $tag
		. ' /archive/' . $tag . ' receipt-' . $tag . '.pdf ' . $tag . '#details</p>'
		. '<p>Created=' . $tag . '</p>'
		. '<code>opaque ' . $tag . '</code>'
		. '<span data-created="' . $tag . '">fixed</span>'
		. '</div>';
	$confirmation = static function ( string $id, string $marker ) use ( $contents ): array {
		return array(
			'id' => $id, 'name' => $id, 'type' => 'message',
			'isActive' => true, 'isDefault' => 'f01-default' === $id,
			'disableAutoformat' => true,
			'message' => '<strong>' . $marker . '</strong>' . $contents,
		);
	};
	$form_id = GFAPI::add_form( array(
		'title' => 'EC-PG83-F01 Production Verification',
		'fields' => array( array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'isRequired' => true ) ),
		'button' => array( 'type' => 'text', 'text' => 'Submit' ),
	) );
	if ( ! is_int( $form_id ) || $form_id <= 0 ) {
		throw new RuntimeException( 'EC-PG83-F01 could not create GFAPI Form.' );
	}
	$form = GFAPI::get_form( $form_id );
	$form['confirmations'] = array(
		'f01-default' => $confirmation( 'f01-default', 'F01_DEFAULT' ),
		'f01-conditional' => array_merge(
			$confirmation( 'f01-conditional', 'F01_CONDITIONAL' ),
			array(
				'conditionalLogic' => array(
					'actionType' => 'show', 'logicType' => 'all',
					'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ),
				),
			)
		),
	);
	if ( true !== GFAPI::update_form( $form ) ) {
		throw new RuntimeException( 'EC-PG83-F01 GFAPI form configuration not persisted.' );
	}
	$form = GFAPI::get_form( $form_id );
	$form_snapshot = wp_json_encode( $form );
	$render = static function ( array $form, array $entry, bool $enabled, bool $ajax ) use ( $original ): array {
		$state = $original;
		$state['states']['jalali_presentation'] = $enabled;
		update_option( 'pgr_modules', $state );
		$calls = 0;
		$pre_adapter = null;
		$spy = static function ( $value ) use ( &$calls ) {
			++$calls;
			return $value;
		};
		$intercept = static function ( $result ) use ( &$pre_adapter ) {
			$pre_adapter = $result;
			return $result;
		};
		add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
		add_filter( 'gform_confirmation', $intercept, 19, 1 );
		try {
			$copy = $form;
			$result = GFFormDisplay::handle_confirmation( $copy, $entry, $ajax );
		} finally {
			remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
			remove_filter( 'gform_confirmation', $intercept, 19 );
		}
		return array( 'html' => $result, 'pre_adapter' => $pre_adapter, 'callbacks' => $calls );
	};
	$report = array(
		'contract' => 'EC-PG83-F01-PRODUCTION-DIRECT-V1',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'gf_version' => GFForms::$version,
		'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
		'production_hook_priority' => 20,
		'environment' => 'LICENSED_GFAPI_SELECTED_CONFIRMATION_PHP',
		'browser_qualification' => 'SEPARATE',
		'cases' => array(),
		'verdict' => 'NOT_PROVEN',
	);
	$all_good = true;
	try {
		foreach ( array( 'default' => 'ordinary', 'conditional' => 'special' ) as $selected => $name ) {
			$entry_id = GFAPI::add_entry( array(
				'form_id' => $form_id, 'status' => 'active',
				'date_created' => '2026-03-20 20:30:00',
				'1' => $name,
			) );
			$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
			if ( ! is_array( $entry ) || ( $entry['date_created'] ?? '' ) !== '2026-03-20 20:30:00' ) {
				throw new RuntimeException( 'EC-PG83-F01 GFAPI Entry persistence mismatch.' );
			}
			foreach ( array( 'ordinary' => false, 'ajax' => true ) as $mode => $ajax ) {
				$native = $render( $form, $entry, false, $ajax );
				$enabled = $render( $form, $entry, true, $ajax );
				$native_html = $native['html'];
				$enabled_html = $enabled['html'];
				$machine = array(
					'extensionless' => 'receipt-2026/03/21',
					'relative_fragment' => './archive/report#release-2026/03/21',
					'path' => '/archive/2026/03/21',
					'filename_pdf' => 'receipt-2026/03/21.pdf',
					'tagled_fragment' => '2026/03/21#details',
					'code' => '<code>opaque 2026/03/21</code>',
					'attribute' => 'data-created="2026/03/21"',
				);
				$machine_checks = array();
				foreach ( $machine as $id => $string ) {
					$machine_checks[ $id ] = is_string( $native_html ) &&
						1 === substr_count( $native_html, $string ) &&
						is_string( $enabled_html ) &&
						1 === substr_count( $enabled_html, $string );
				}
				$expected = is_string( $native_html ) ?
					str_replace( 'Created=2026/03/21', 'Created=۱۴۰۵/۰۱/۰۱', $native_html ) : null;
				$marker = 'default' === $selected ? 'F01_DEFAULT' : 'F01_CONDITIONAL';
				$all = is_string( $expected ) && $expected !== $native_html &&
					$enabled_html === $expected &&
					$enabled['pre_adapter'] === $native_html &&
					$native['pre_adapter'] === $native_html &&
					1 === $native['callbacks'] && 1 === $enabled['callbacks'] &&
					false !== strpos( $native_html, $marker ) &&
					false !== strpos( $enabled_html, $marker ) &&
					! in_array( false, $machine_checks, true );
				$case_id = $selected . '_' . $mode;
				$report['cases'][ $case_id ] = array(
					'status' => $all ? 'PASS' : 'FAILED',
					'entry_id' => $entry_id,
					'form_id' => $form_id,
					'native_html' => $native_html,
					'enabled_html' => $enabled_html,
					'expected_single_human_span_html' => $expected,
					'native_sha256' => is_string( $native_html ) ? hash( 'sha256', $native_html ) : null,
					'enabled_sha256' => is_string( $enabled_html ) ? hash( 'sha256', $enabled_html ) : null,
					'expected_sha256' => is_string( $expected ) ? hash( 'sha256', $expected ) : null,
					'machine_unchanged' => $machine_checks,
					'callbacks' => array( 'native' => $native['callbacks'], 'enabled' => $enabled['callbacks'] ),
					'pre_adapter_equal_to_native' => $enabled['pre_adapter'] === $native_html,
					'entry_unchanged' => GFAPI::get_entry( $entry_id ) === $entry,
				);
				$all_good = $all_good && $all && $report['cases'][ $case_id ]['entry_unchanged'];
			}
		}
		$report['form_unchanged'] = wp_json_encode( GFAPI::get_form( $form_id ) ) === $form_snapshot;
		$all_good = $all_good && $report['form_unchanged'];
	} finally {
		update_option( 'pgr_modules', $original );
	}
	$report['verdict'] = $all_good ? 'PASS' : 'CONFIRMED_FINDING';
	$urls = array();
	foreach ( array( 'ordinary' => 'false', 'ajax' => 'true' ) as $mode => $ajax ) {
		$page = wp_insert_post( array(
			'post_title' => 'EC-PG83-F01 ' . $mode,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="%s"]', $form_id, $ajax ),
		) );
		$urls[ $mode ] = get_permalink( $page );
	}
	$report['urls'] = $urls;
	if ( false === file_put_contents( $path, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
		throw new RuntimeException( 'EC-PG83-F01 evidence write failed.' );
	}
	echo 'EC-PG83-F01 licensed production PHP qualification: ' . $report['verdict'] . "\n";
} )();
