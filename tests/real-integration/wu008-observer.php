<?php
/**
 * Evidence-only observer for WU-008. This MU plugin never changes translations.
 */
defined( 'ABSPATH' ) || exit;

/**
 * The deterministic setup script creates its GF entry before it creates the
 * Gravity Flow step. Gravity Flow 3.1.0 only seeds these two workflow meta
 * values from gform_post_add_entry when a step already exists. Seed the same
 * normal starting state for this disposable fixture so that the subsequent
 * public Gravity_Flow_API::process_workflow() call can perform real orchestration.
 * This is fixture state only; it does not alter product/provider behavior.
 */
add_action( 'gform_post_add_entry', static function ( $entry, $form ) {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! getenv( 'WU008_ARTIFACT_DIR' ) || is_wp_error( $entry ) || empty( $entry['id'] ) || empty( $form['id'] ) ) {
		return;
	}
	gform_update_meta( (int) $entry['id'], 'workflow_final_status', 'pending', (int) $form['id'] );
	gform_update_meta( (int) $entry['id'], 'workflow_step', false, (int) $form['id'] );
}, 1, 2 );

function wu008_obs_map() {
	return array(
		's01' => array( 'id' => 'gravityforms::frontend_runtime::shortcode:gravityform', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/frontend-shortcode-fa_IR.po' ),
		's02' => array( 'id' => 'gravityforms::frontend_runtime::block:gravityforms/form', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/frontend-block-fa_IR.po' ),
		's03' => array( 'id' => 'gravityforms::admin_builder::admin_page:gf_edit_forms', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/admin-builder-fa_IR.po' ),
		's04' => array( 'id' => 'gravityforms::entry_management_runtime::admin_page:gf_entries', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/entry-management-fa_IR.po' ),
		's05' => array( 'id' => 'gravityforms::settings_integrations::admin_page:gf_settings', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/settings-integrations-fa_IR.po' ),
		's06' => array( 'id' => 'gravityforms::developer_diagnostics::admin_page:gf_system_status', 'product' => 'gravityforms', 'domain' => 'gravityforms', 'record' => 'gravityforms/source/records/developer-diagnostics-fa_IR.po' ),
		's07' => array( 'id' => 'gravityflow::workflow_runtime::shortcode:gravityflow', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/shortcode-fa_IR.po' ),
		's08' => array( 'id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/inbox-fa_IR.po' ),
		's09' => array( 'id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-status', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/status-fa_IR.po' ),
		's10' => array( 'id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-reports', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/reports-fa_IR.po' ),
		's11' => array( 'id' => 'gravityflow::admin_builder::form_settings:gravityflow', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/admin-builder-fa_IR.po' ),
		's12' => array( 'id' => 'gravityflow::settings_integrations::admin_page:gravityflow_settings', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/settings-integrations-fa_IR.po' ),
		's13' => array( 'id' => 'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow', 'product' => 'gravityflow', 'domain' => 'gravityflow', 'record' => 'gravityflow/source/records/entry-detail-sidebar-fa_IR.po' ),
		's14' => array( 'id' => 'gravityview::frontend_runtime::shortcode:gravityview', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/frontend-shortcode-fa_IR.po' ),
		's15' => array( 'id' => 'gravityview::frontend_runtime::block:gk-gravityview-blocks/view', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/gutenberg-view-block-fa_IR.po' ),
		's16' => array( 'id' => 'gravityview::admin_builder::post_type:gravityview', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/admin-builder-fa_IR.po' ),
		's17' => array( 'id' => 'gravityview::settings_integrations::foundation_settings:gravityview', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/foundation-settings-fa_IR.po' ),
		's18' => array( 'id' => 'gravityview::entry_management_runtime::gravityforms_entry_list:approval', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/entry-approval-fa_IR.po' ),
		's19' => array( 'id' => 'gravityview::frontend_runtime::widget:gravityview_widget_search', 'product' => 'gravityview', 'domain' => 'gk-gravityview', 'record' => 'gravityview/source/records/search-widget-fa_IR.po' ),
	);
}

function wu008_obs_key() {
	$key = isset( $_COOKIE['wu008_surface'] ) ? sanitize_key( wp_unslash( $_COOKIE['wu008_surface'] ) ) : '';
	$map = wu008_obs_map();
	return isset( $map[ $key ] ) ? $key : '';
}

function wu008_obs_write( array $event ) {
	$key = wu008_obs_key();
	$dir = getenv( 'WU008_ARTIFACT_DIR' );
	if ( '' === $key || ! is_string( $dir ) || '' === $dir ) return;
	$map = wu008_obs_map();
	$event = array_merge( array(
		'ts' => gmdate( 'c' ),
		'surface_key' => $key,
		'surface_id' => $map[ $key ]['id'],
		'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
		'is_admin' => is_admin(),
	), $event );
	wp_mkdir_p( $dir );
	file_put_contents( rtrim( $dir, '/' ) . '/surface-runtime-events.jsonl', wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND | LOCK_EX );
}

function wu008_obs_po( $file ) {
	static $cache = array();
	if ( isset( $cache[ $file ] ) ) return $cache[ $file ];
	if ( ! is_readable( $file ) ) return $cache[ $file ] = array();
	$out = array();
	$entry = array( 'ctx' => '', 'id' => null, 'str' => null );
	$field = null;
	$decode = static function ( $quoted ) {
		$quoted = trim( $quoted );
		$value = json_decode( $quoted, true );
		return is_string( $value ) ? $value : '';
	};
	$flush = static function () use ( &$entry, &$out ) {
		if ( is_string( $entry['id'] ) && '' !== $entry['id'] && is_string( $entry['str'] ) && '' !== $entry['str'] ) {
			$key = '' !== $entry['ctx'] ? $entry['ctx'] . "\004" . $entry['id'] : $entry['id'];
			$out[ $key ] = $entry['str'];
		}
		$entry = array( 'ctx' => '', 'id' => null, 'str' => null );
	};
	foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
		if ( 0 === strpos( $line, 'msgctxt ' ) ) { $field = 'ctx'; $entry['ctx'] = $decode( substr( $line, 8 ) ); continue; }
		if ( 0 === strpos( $line, 'msgid ' ) ) { $flush(); $field = 'id'; $entry['id'] = $decode( substr( $line, 6 ) ); continue; }
		if ( 0 === strpos( $line, 'msgid_plural ' ) || preg_match( '/^msgstr\[\d+\] /', $line ) ) { $field = null; continue; }
		if ( 0 === strpos( $line, 'msgstr ' ) ) { $field = 'str'; $entry['str'] = $decode( substr( $line, 7 ) ); continue; }
		if ( '"' === substr( ltrim( $line ), 0, 1 ) && in_array( $field, array( 'ctx', 'id', 'str' ), true ) ) { $entry[ $field ] .= $decode( ltrim( $line ) ); continue; }
		if ( '' === trim( $line ) ) { $flush(); $field = null; }
	}
	$flush();
	return $cache[ $file ] = $out;
}

function wu008_obs_sets() {
	static $sets = null;
	if ( null !== $sets ) return $sets;
	$key = wu008_obs_key();
	$map = wu008_obs_map();
	if ( '' === $key ) return $sets = array();
	$root = WP_PLUGIN_DIR . '/persian-gravityforms/languages/providers/';
	return $sets = array(
		'domain' => $map[ $key ]['domain'],
		'surface' => wu008_obs_po( $root . $map[ $key ]['record'] ),
		'aggregate' => wu008_obs_po( $root . $map[ $key ]['product'] . '/source/fa_IR.po' ),
	);
}

function wu008_obs_gettext( $translation, $text, $domain, $context = '' ) {
	static $counts = array( 'admitted' => 0, 'non' => 0, 'other' => 0 );
	$sets = wu008_obs_sets();
	if ( empty( $sets ) || $domain !== $sets['domain'] || ! is_string( $text ) || '' === $text ) return $translation;
	$key = '' !== $context ? $context . "\004" . $text : $text;
	if ( isset( $sets['surface'][ $key ] ) ) {
		if ( $counts['admitted']++ < 200 ) wu008_obs_write( array( 'type' => 'admitted_gettext', 'domain' => $domain, 'context' => $context, 'original' => $text, 'translation' => $translation, 'expected_translation' => $sets['surface'][ $key ], 'expected_match' => hash_equals( $sets['surface'][ $key ], (string) $translation ) ) );
	} elseif ( ! isset( $sets['aggregate'][ $key ] ) ) {
		if ( $counts['non']++ < 50 ) wu008_obs_write( array( 'type' => 'non_admitted_gettext', 'domain' => $domain, 'context' => $context, 'original' => $text, 'translation' => $translation, 'unchanged' => $translation === $text ) );
	} elseif ( $counts['other']++ < 50 ) {
		wu008_obs_write( array( 'type' => 'other_surface_admitted_gettext', 'domain' => $domain, 'context' => $context, 'original' => $text, 'translation' => $translation ) );
	}
	return $translation;
}
add_filter( 'gettext', static function ( $translation, $text, $domain ) { return wu008_obs_gettext( $translation, $text, $domain ); }, 999, 3 );
add_filter( 'gettext_with_context', static function ( $translation, $text, $context, $domain ) { return wu008_obs_gettext( $translation, $text, $domain, $context ); }, 999, 4 );

add_filter( 'load_translation_file', static function ( $file, $domain, $locale ) {
	if ( in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) wu008_obs_write( array( 'type' => 'translation_file', 'domain' => $domain, 'locale' => $locale, 'file' => (string) $file, 'persiangravity_provider_file' => false !== strpos( (string) $file, '/persian-gravityforms/languages/providers/' ) ) );
	return $file;
}, 999, 3 );

add_filter( 'load_script_translations', static function ( $translations, $file, $handle, $domain ) {
	if ( in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) wu008_obs_write( array( 'type' => 'load_script_translations', 'domain' => $domain, 'handle' => $handle, 'file' => (string) $file, 'returned_bytes' => is_string( $translations ) ? strlen( $translations ) : 0, 'file_is_pgr_provider_json' => false !== strpos( (string) $file, '/persian-gravityforms/languages/providers/' ) ) );
	return $translations;
}, 999, 4 );
add_filter( 'pre_load_script_translations', static function ( $translations, $file, $handle, $domain ) {
	if ( in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) wu008_obs_write( array( 'type' => 'pre_load_script_translations', 'domain' => $domain, 'handle' => $handle, 'file' => false === $file ? false : (string) $file, 'short_circuit_bytes' => is_string( $translations ) ? strlen( $translations ) : 0 ) );
	return $translations;
}, 999, 4 );

add_action( 'init', static function () { if ( '' !== wu008_obs_key() ) wu008_obs_write( array( 'type' => 'request_start', 'locale' => determine_locale(), 'rtl' => is_rtl() ) ); }, 999 );
add_action( 'shutdown', static function () {
	if ( '' === wu008_obs_key() ) return;
	$handles = array();
	if ( isset( $GLOBALS['wp_scripts'] ) && is_object( $GLOBALS['wp_scripts'] ) ) {
		foreach ( (array) $GLOBALS['wp_scripts']->registered as $handle => $dependency ) {
			$domain = isset( $dependency->textdomain ) ? (string) $dependency->textdomain : '';
			if ( ! in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) continue;
			$handles[] = array( 'handle' => (string) $handle, 'textdomain' => $domain, 'translations_path' => isset( $dependency->translations_path ) ? (string) $dependency->translations_path : '', 'src' => isset( $dependency->src ) ? (string) $dependency->src : '', 'enqueued' => in_array( $handle, (array) $GLOBALS['wp_scripts']->queue, true ), 'done' => in_array( $handle, (array) $GLOBALS['wp_scripts']->done, true ) );
		}
	}
	wu008_obs_write( array( 'type' => 'request_end', 'http_status' => function_exists( 'http_response_code' ) ? http_response_code() : null, 'script_translation_handles' => $handles ) );
}, PHP_INT_MAX );
