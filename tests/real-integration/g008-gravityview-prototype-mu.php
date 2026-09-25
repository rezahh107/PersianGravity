<?php
/**
 * Qualification-only GravityView G-008 prototype.
 *
 * This file is copied into the disposable WordPress MU-plugin directory only by
 * the licensed integration workflow. It is never loaded by production code.
 */

defined( 'ABSPATH' ) || exit;

function pgr_wu008_gv_exact_version(): bool {
	if ( (bool) get_option( 'wu008_gv_qualification_force_version_drift', false ) ) {
		return false;
	}
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/gravityview/gravityview.php', false, false );
	return isset( $data['Version'] ) && '3.3.4' === (string) $data['Version'];
}

function pgr_wu008_gv_probe_mode(): string {
	$mode = get_option( 'wu008_gv_qualification_mode', 'native' );
	return is_string( $mode ) ? $mode : 'native';
}

function pgr_wu008_gv_trace( array $record ): void {
	$trace = get_option( 'wu008_gv_qualification_trace', array() );
	if ( ! is_array( $trace ) ) {
		$trace = array();
	}

	$trace[] = $record;
	if ( count( $trace ) > 200 ) {
		$trace = array_slice( $trace, -200 );
	}
	update_option( 'wu008_gv_qualification_trace', $trace, false );
}

function pgr_wu008_gv_parse_utc( string $raw ): ?DateTimeImmutable {
	$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $source || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
		return null;
	}
	if ( $source->format( 'Y-m-d H:i:s' ) !== $raw ) {
		return null;
	}
	return $source;
}

function pgr_wu008_gv_filter_system_date( $output, $context ) {
	$field_id = null;
	$entry    = array();
	$view_id  = 0;

	if ( is_object( $context ) && isset( $context->field ) && is_object( $context->field ) ) {
		$field_id = isset( $context->field->ID ) ? (string) $context->field->ID : null;
	}
	if ( is_object( $context ) && isset( $context->entry ) && is_object( $context->entry ) && method_exists( $context->entry, 'as_entry' ) ) {
		$entry = $context->entry->as_entry();
	}
	if ( is_object( $context ) && isset( $context->view ) && is_object( $context->view ) ) {
		$view_id = isset( $context->view->ID ) ? (int) $context->view->ID : 0;
	}

	$expected_view_id = (int) get_option( 'wu008_gv_qualification_view_id', 0 );
	$mode             = pgr_wu008_gv_probe_mode();
	$raw              = is_array( $entry ) && null !== $field_id && array_key_exists( $field_id, $entry ) ? (string) $entry[ $field_id ] : '';
	$record           = array(
		'hook'               => current_filter(),
		'mode'               => $mode,
		'field_id'           => $field_id,
		'view_id'            => $view_id,
		'entry_id'           => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
		'raw'                => $raw,
		'native_output'      => (string) $output,
		'locale'             => function_exists( 'determine_locale' ) ? determine_locale() : null,
		'module_enabled'     => class_exists( 'PGR_Module_Registry', false ) && PGR_Module_Registry::is_enabled( 'jalali_presentation' ),
		'exact_version'      => pgr_wu008_gv_exact_version(),
		'expected_view_match'=> $expected_view_id > 0 && $view_id === $expected_view_id,
		'is_admin'           => is_admin(),
	);

	$eligible = in_array( $mode, array( 'enabled', 'english', 'drift' ), true )
		&& in_array( $field_id, array( 'date_created', 'date_updated' ), true )
		&& $expected_view_id > 0
		&& $view_id === $expected_view_id
		&& ! is_admin()
		&& 'fa_IR' === ( function_exists( 'determine_locale' ) ? determine_locale() : '' )
		&& pgr_wu008_gv_exact_version()
		&& class_exists( 'PGR_Module_Registry', false )
		&& PGR_Module_Registry::is_enabled( 'jalali_presentation' )
		&& class_exists( 'PGR_Jalali_Presentation', false );

	if ( ! $eligible ) {
		$record['presented'] = false;
		pgr_wu008_gv_trace( $record );
		return $output;
	}

	$source = pgr_wu008_gv_parse_utc( $raw );
	if ( ! $source ) {
		$record['presented'] = false;
		$record['reason']    = 'raw_parse_failed';
		pgr_wu008_gv_trace( $record );
		return $output;
	}

	try {
		$presented = PGR_Jalali_Presentation::format_datetime( $source );
	} catch ( Throwable $exception ) {
		unset( $exception );
		$presented = null;
	}

	if ( null === $presented ) {
		$record['presented'] = false;
		$record['reason']    = 'presentation_fallback';
		pgr_wu008_gv_trace( $record );
		return $output;
	}

	$record['presented'] = true;
	$record['jalali']    = $presented;
	pgr_wu008_gv_trace( $record );

	return sprintf(
		'<span class="wu008-gv-system-date" data-field="%s" data-entry="%d" data-raw="%s" data-native="%s">%s</span>',
		esc_attr( (string) $field_id ),
		(int) $entry['id'],
		esc_attr( $raw ),
		esc_attr( wp_strip_all_tags( (string) $output ) ),
		esc_html( $presented )
	);
}

add_filter( 'gravityview/template/field/date_created/output', 'pgr_wu008_gv_filter_system_date', 20, 2 );
add_filter( 'gravityview/template/field/date_updated/output', 'pgr_wu008_gv_filter_system_date', 20, 2 );
