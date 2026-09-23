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

	/** Exact Gravity Flow browser-table output format. */
	private const STATUS_TABLE_FORMAT = 'table';

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** @var string|null Exact render format observed through gravityflow_status_args. */
	private $status_format = null;

	/**
	 * Register the exact Status render-context observer and presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_status_args', array( $this, 'capture_status_context' ), 20, 1 );
		add_filter( 'gravityflow_field_value_status_table', array( $this, 'filter_status_value' ), 20, 4 );
	}

	/**
	 * Capture only Gravity Flow's explicit Status render format.
	 *
	 * Gravity Flow 3.1.0 normalizes defaults before this filter and branches on
	 * the resulting format: table renders the browser Status table while csv
	 * enters the export path. Missing or unfamiliar context stays fail-closed.
	 *
	 * @param mixed $args Native Status render arguments.
	 * @return mixed
	 */
	public function capture_status_context( $args ) {
		$this->status_format = null;

		if ( is_array( $args ) && isset( $args['format'] ) && is_string( $args['format'] ) ) {
			$this->status_format = $args['format'];
		}

		return $args;
	}

	/**
	 * Convert only the two admitted Status-table system-date presentation values.
	 *
	 * CSV/export uses the same value filter, but Gravity Flow marks that render
	 * as format=csv through gravityflow_status_args first. The adapter therefore
	 * returns native export/raw values unchanged.
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
			self::STATUS_TABLE_FORMAT !== $this->status_format ||
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
