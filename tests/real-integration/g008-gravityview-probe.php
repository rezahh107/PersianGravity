<?php
/**
 * Qualification-only GravityView G-008 presentation probe.
 *
 * Loaded only by the disposable WU008 licensed runtime. This is not production
 * PersianGravity behavior and deliberately fails closed outside the exact
 * qualified field/view/configuration context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Append one bounded probe record to the WU008 artifact directory.
 *
 * @param array $record Probe data.
 */
function wu008_g008_gravityview_trace( array $record ): void {
	$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
	if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
		return;
	}

	$record['request_uri'] = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$record['locale']      = function_exists( 'determine_locale' ) ? determine_locale() : '';
	$record['module']      = class_exists( 'PGR_Module_Registry', false )
		? PGR_Module_Registry::is_enabled( 'jalali_presentation' )
		: false;

	file_put_contents(
		$artifact_dir . '/g008-gravityview-probe-trace.jsonl',
		wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n",
		FILE_APPEND | LOCK_EX
	);
}

/**
 * Strictly parse an authoritative Gravity Forms UTC system datetime.
 */
function wu008_g008_gravityview_parse_utc( $raw ): ?DateTimeImmutable {
	if ( ! is_string( $raw ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $raw ) ) {
		return null;
	}

	$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
	$errors = DateTimeImmutable::getLastErrors();
	if (
		false === $source ||
		( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
		$source->format( 'Y-m-d H:i:s' ) !== $raw
	) {
		return null;
	}

	return $source;
}

/**
 * Qualification-only callback for the exact type-specific output seam.
 *
 * @param mixed $output  Native GravityView output.
 * @param mixed $context GravityView template context.
 * @return mixed
 */
function wu008_g008_gravityview_present_system_date( $output, $context ) {
	$hook       = current_filter();
	$field_type = str_ends_with( $hook, '/date_created/output' ) ? 'date_created' : 'date_updated';
	$record     = array(
		'hook'          => $hook,
		'field_type'    => $field_type,
		'output_before' => is_scalar( $output ) ? (string) $output : gettype( $output ),
		'gate'          => 'UNKNOWN',
	);

	if ( ! defined( 'GV_PLUGIN_VERSION' ) || '3.3.4' !== GV_PLUGIN_VERSION ) {
		$record['gate'] = 'HOST_VERSION';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}
	if ( get_option( 'wu008_g008_gravityview_force_version_mismatch', false ) ) {
		$record['gate'] = 'FORCED_VERSION_MISMATCH';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}
	if (
		! class_exists( 'PGR_Module_Registry', false ) ||
		! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ||
		! class_exists( 'PGR_Jalali_Presentation', false )
	) {
		$record['gate'] = 'MODULE_OR_FACADE';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}
	if ( 'fa_IR' !== determine_locale() ) {
		$record['gate'] = 'LOCALE';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}
	if ( ! is_object( $context ) || ! isset( $context->view, $context->field, $context->entry ) ) {
		$record['gate'] = 'CONTEXT';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$target_view_id = (int) get_option( 'wu008_g008_gravityview_target_view_id', 0 );
	$view_id        = isset( $context->view->ID ) ? (int) $context->view->ID : 0;
	$field_id       = isset( $context->field->ID ) ? (string) $context->field->ID : '';
	$field_kind     = isset( $context->field->type ) ? (string) $context->field->type : '';
	$record['view_id']    = $view_id;
	$record['field_id']   = $field_id;
	$record['field_kind'] = $field_kind;

	if ( $target_view_id < 1 || $target_view_id !== $view_id || $field_type !== $field_id || $field_type !== $field_kind ) {
		$record['gate'] = 'IDENTITY';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$field_configuration = method_exists( $context->field, 'as_configuration' )
		? (array) $context->field->as_configuration()
		: array();
	if ( ! empty( $field_configuration['show_as_link'] ) || ! empty( $field_configuration['field_path'] ) ) {
		$record['gate'] = 'UNQUALIFIED_FIELD_CONFIGURATION';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}
	if ( ! method_exists( $context->entry, 'as_entry' ) ) {
		$record['gate'] = 'ENTRY_CONTEXT';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$entry = $context->entry->as_entry();
	$raw   = is_array( $entry ) && isset( $entry[ $field_type ] ) ? $entry[ $field_type ] : null;
	$record['entry_id']      = is_array( $entry ) && isset( $entry['id'] ) ? (int) $entry['id'] : 0;
	$record['raw']           = is_scalar( $raw ) ? (string) $raw : gettype( $raw );
	$record['context_value'] = isset( $context->value ) && is_scalar( $context->value ) ? (string) $context->value : gettype( $context->value ?? null );

	if ( ! is_string( $raw ) || (string) $context->value !== $raw ) {
		$record['gate'] = 'RAW_IDENTITY';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$source = wu008_g008_gravityview_parse_utc( $raw );
	if ( null === $source ) {
		$record['gate'] = 'RAW_PARSE';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$presented = PGR_Jalali_Presentation::format_datetime( $source );
	if ( null === $presented ) {
		$record['gate'] = 'FACADE';
		wu008_g008_gravityview_trace( $record );
		return $output;
	}

	$record['gate']         = 'CONVERTED';
	$record['output_after'] = $presented;
	wu008_g008_gravityview_trace( $record );
	return $presented;
}

add_filter( 'gravityview/template/field/date_created/output', 'wu008_g008_gravityview_present_system_date', 10, 2 );
add_filter( 'gravityview/template/field/date_updated/output', 'wu008_g008_gravityview_present_system_date', 10, 2 );
