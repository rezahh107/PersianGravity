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

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/**
	 * One-shot proof that the next matching value filter originated from the
	 * actual Status-table column path.
	 *
	 * @var array<string,string>|null
	 */
	private $status_table_entry_token = null;

	/**
	 * Register the render reset, table-only proof seam and presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_status_args', array( $this, 'reset_status_context' ), 20, 1 );
		add_filter( 'gravityflow_entry_url_status_table', array( $this, 'mark_status_table_entry' ), 20, 4 );
		add_filter( 'gravityflow_field_value_status_table', array( $this, 'filter_status_value' ), 20, 4 );
	}

	/**
	 * Clear any stale table proof whenever a new Status render begins.
	 *
	 * The final table-vs-export decision is intentionally not inferred here:
	 * later gravityflow_status_args callbacks can still change format after
	 * this callback returns.
	 *
	 * @param mixed $args Native Status render arguments.
	 * @return mixed
	 */
	public function reset_status_context( $args ) {
		$this->status_table_entry_token = null;
		return $args;
	}

	/**
	 * Mark the exact table entry immediately before table column value filtering.
	 *
	 * Gravity Flow 3.1.0 calls get_entry_url() in both admitted table column
	 * methods before their gravityflow_field_value_status_table call. Its CSV
	 * exporter uses the value filter directly and never calls this seam.
	 *
	 * @param mixed        $entry_url Native Status entry URL.
	 * @param mixed        $form_id   Current form ID.
	 * @param mixed        $entry_id  Current entry ID.
	 * @param array<mixed> $entry     Current entry.
	 * @return mixed
	 */
	public function mark_status_table_entry( $entry_url, $form_id, $entry_id, $entry ) {
		$this->status_table_entry_token = null;

		if (
			! is_array( $entry ) ||
			! isset( $entry['form_id'], $entry['id'] ) ||
			(int) $form_id <= 0 ||
			(int) $entry_id <= 0 ||
			(string) $form_id !== (string) $entry['form_id'] ||
			(string) $entry_id !== (string) $entry['id']
		) {
			return $entry_url;
		}

		$this->status_table_entry_token = array(
			'form_id'  => (string) $form_id,
			'entry_id' => (string) $entry_id,
		);

		return $entry_url;
	}

	/**
	 * Convert only the two admitted Status-table system-date presentation values.
	 *
	 * Table authority is a one-shot token emitted by the exact table column path
	 * after Gravity Flow has already taken its final render branch. Export and
	 * unknown/direct value-filter calls therefore remain native.
	 *
	 * @param mixed        $value       Native display value.
	 * @param int          $form_id     Current Gravity Forms form ID.
	 * @param string       $column_name Status table column identity.
	 * @param array<mixed> $entry       Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_status_value( $value, $form_id, $column_name, $entry ) {
		$is_status_table = $this->consume_status_table_entry_token( $form_id, $entry );

		if (
			! $is_status_table ||
			! $this->is_exact_supported_host() ||
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
	 * Consume table proof exactly once and bind it to the same form/entry.
	 *
	 * @param mixed $form_id Current form ID.
	 * @param mixed $entry   Current entry.
	 * @return bool
	 */
	private function consume_status_table_entry_token( $form_id, $entry ) {
		$token                          = $this->status_table_entry_token;
		$this->status_table_entry_token = null;

		if (
			! is_array( $token ) ||
			! is_array( $entry ) ||
			! isset( $entry['form_id'], $entry['id'] )
		) {
			return false;
		}

		return (
			$token['form_id'] === (string) $form_id &&
			$token['form_id'] === (string) $entry['form_id'] &&
			$token['entry_id'] === (string) $entry['id']
		);
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
