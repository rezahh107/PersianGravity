<?php
/**
 * Bounded Jalali presentation for admitted Gravity Flow Status-table system dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter {

	/** Exact Status-table creation-date column identity. */
	private const DATE_CREATED_COLUMN = 'date_created';

	/** Exact Status-table workflow timestamp column identity. */
	private const WORKFLOW_TIMESTAMP_COLUMN = 'workflow_timestamp';

	/** Exact Gravity Flow table wrapper that owns browser presentation. */
	private const STATUS_TABLE_CLASS = 'Gravity_Flow_Status_Table';

	/** Exact method used by 3.1.0 table rendering, but not CSV export. */
	private const STATUS_TABLE_FILTER_METHOD = 'filter_field_value';

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/**
	 * Register only the exact Gravity Flow Status value seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_field_value_status_table', array( $this, 'filter_status_value' ), 20, 4 );
	}

	/**
	 * Convert only the two admitted Status-table system-date presentation values.
	 *
	 * Gravity Flow 3.1.0 also invokes this filter directly while producing CSV.
	 * The browser table path uniquely reaches it through
	 * Gravity_Flow_Status_Table::filter_field_value(); requiring that exact
	 * frame prevents this adapter from changing export/raw/operational values.
	 *
	 * @param mixed        $value       Native display value.
	 * @param int          $form_id     Current Gravity Forms form ID.
	 * @param string       $column_name Status table column identity.
	 * @param array<mixed> $entry       Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_status_value( $value, $form_id, $column_name, $entry ) {
		unset( $form_id );

		if (
			! $this->is_exact_supported_host() ||
			! $this->is_status_table_presentation_context() ||
			! class_exists( 'PGR_Jalali_Presentation', false ) ||
			! is_array( $entry )
		) {
			return $value;
		}

		if ( self::DATE_CREATED_COLUMN === $column_name ) {
			$source = $this->date_created_source( $entry );
		} elseif ( self::WORKFLOW_TIMESTAMP_COLUMN === $column_name ) {
			$source = $this->workflow_timestamp_source( $entry );
		} else {
			return $value;
		}

		if ( null === $source ) {
			return $value;
		}

		try {
			$formatted = PGR_Jalali_Presentation::format_datetime( $source );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return $value;
		}

		return null === $formatted ? $value : $formatted;
	}

	/**
	 * Require the exact 3.1.0 browser-table caller. CSV export applies the same
	 * WordPress filter directly and therefore intentionally lacks this frame.
	 *
	 * @return bool
	 */
	private function is_status_table_presentation_context() {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ) as $frame ) {
			if (
				( $frame['class'] ?? '' ) === self::STATUS_TABLE_CLASS &&
				( $frame['function'] ?? '' ) === self::STATUS_TABLE_FILTER_METHOD
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve Gravity Forms' authoritative UTC entry creation timestamp.
	 *
	 * @param array<mixed> $entry Current entry.
	 * @return DateTimeImmutable|null
	 */
	private function date_created_source( $entry ) {
		$raw = isset( $entry['date_created'] ) && is_string( $entry['date_created'] ) ? $entry['date_created'] : '';
		if ( '' === $raw ) {
			return null;
		}

		$timezone = new DateTimeZone( 'UTC' );
		$source   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, $timezone );
		$errors   = DateTimeImmutable::getLastErrors();
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
	 * Resolve Gravity Flow's authoritative Unix workflow timestamp.
	 *
	 * @param array<mixed> $entry Current entry.
	 * @return DateTimeImmutable|null
	 */
	private function workflow_timestamp_source( $entry ) {
		if ( ! isset( $entry['workflow_timestamp'] ) ) {
			return null;
		}

		$raw = $entry['workflow_timestamp'];
		if ( is_int( $raw ) ) {
			$timestamp = $raw;
		} elseif ( is_string( $raw ) && 1 === preg_match( '/^[1-9][0-9]*$/', $raw ) ) {
			$timestamp = (int) $raw;
		} else {
			return null;
		}

		if ( $timestamp <= 0 ) {
			return null;
		}

		try {
			return new DateTimeImmutable( '@' . $timestamp );
		} catch ( Exception $exception ) {
			unset( $exception );
			return null;
		}
	}

	/**
	 * Admit only the exact host product/version already owned by the existing
	 * product registry. Missing or drifted authority always keeps native output.
	 *
	 * @return bool
	 */
	private function is_exact_supported_host() {
		if (
			! defined( self::HOST_VERSION_CONSTANT ) ||
			! defined( self::HOST_BASENAME_CONSTANT ) ||
			! defined( 'PGR_PATH' )
		) {
			return false;
		}

		$registry_path = PGR_PATH . 'includes/localization/products.php';
		if ( ! is_readable( $registry_path ) ) {
			return false;
		}

		$plugin_basename = str_replace( '\\', '/', (string) constant( self::HOST_BASENAME_CONSTANT ) );
		$product_slug    = dirname( $plugin_basename );
		if ( '' === $product_slug || '.' === $product_slug || '/' === $product_slug ) {
			return false;
		}

		$products = require $registry_path;
		$target   = '';
		foreach ( $products as $product ) {
			if ( ( $product['product'] ?? '' ) !== $product_slug ) {
				continue;
			}
			$target = isset( $product['target_version'] ) ? (string) $product['target_version'] : '';
			break;
		}

		return '' !== $target && (string) constant( self::HOST_VERSION_CONSTANT ) === $target;
	}
}
