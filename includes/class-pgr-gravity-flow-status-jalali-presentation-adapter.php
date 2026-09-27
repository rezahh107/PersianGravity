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

	/** Host-owned runtime version observation. Version equality is not eligibility. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Canonical Gravity Flow plugin main-file identity. */
	private const HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php';

	private const CAP_DATE_CREATED       = 'gravityflow.status.date-created';
	private const CAP_WORKFLOW_TIMESTAMP = 'gravityflow.status.workflow-timestamp';

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
	 * The admitted host calls this seam in both migrated table columns before
	 * gravityflow_field_value_status_table. CSV/export uses the value filter
	 * directly, so it receives no table token and remains native.
	 *
	 * @param mixed        $entry_url Native Status entry URL.
	 * @param mixed        $form_id   Current form ID.
	 * @param mixed        $entry_id  Current entry ID.
	 * @param array<mixed> $entry     Current entry.
	 * @return mixed
	 */
	public function mark_status_table_entry( $entry_url, $form_id, $entry_id, $entry ) {
		$this->status_table_entry_token = null;

		if ( ! is_array( $entry ) || ! isset( $entry['form_id'], $entry['id'] ) ) {
			return $entry_url;
		}

		$form_id       = $this->positive_decimal_id( $form_id );
		$entry_id      = $this->positive_decimal_id( $entry_id );
		$entry_form_id = $this->positive_decimal_id( $entry['form_id'] );
		$entry_row_id  = $this->positive_decimal_id( $entry['id'] );

		if (
			null === $form_id ||
			null === $entry_id ||
			null === $entry_form_id ||
			null === $entry_row_id ||
			$form_id !== $entry_form_id ||
			$entry_id !== $entry_row_id
		) {
			return $entry_url;
		}

		$this->status_table_entry_token = array(
			'form_id'  => $form_id,
			'entry_id' => $entry_id,
		);

		return $entry_url;
	}

	/**
	 * Convert only the two admitted Status-table system-date presentation values.
	 *
	 * @param mixed        $value       Native display value.
	 * @param int          $form_id     Current Gravity Forms form ID.
	 * @param string       $column_name Status table column identity.
	 * @param array<mixed> $entry       Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_status_value( $value, $form_id, $column_name, $entry ) {
		$capability_id   = $this->capability_for_column( $column_name );
		$is_status_table = $this->consume_status_table_entry_token( $form_id, $entry );

		if ( null === $capability_id ) {
			return $value;
		}

		if ( ! $this->has_qualified_host_identity() ) {
			$this->record_diagnostic( $capability_id, 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED' );
			return $value;
		}

		if ( ! $is_status_table || ! is_array( $entry ) ) {
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return $value;
		}

		if ( self::DATE_CREATED_COLUMN === $column_name ) {
			$source = $this->date_created_source( $entry );
		} else {
			$source = $this->workflow_timestamp_source( $entry );
		}

		if ( null === $source ) {
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_SOURCE_INVALID' );
			return $value;
		}

		if ( ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_CONVERSION_UNAVAILABLE' );
			return $value;
		}

		try {
			$formatted = PGR_Jalali_Presentation::format_datetime( $source );
		} catch ( Throwable $exception ) {
			unset( $exception );
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_CONVERSION_UNAVAILABLE' );
			return $value;
		}

		if ( null === $formatted ) {
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_CONVERSION_UNAVAILABLE' );
			return $value;
		}

		$this->record_diagnostic( $capability_id, 'STATE_AVAILABLE', 'REASON_CONTRACT_SATISFIED' );
		return $formatted;
	}

	/** @return string|null */
	private function capability_for_column( $column_name ) {
		if ( self::DATE_CREATED_COLUMN === $column_name ) {
			return self::CAP_DATE_CREATED;
		}
		if ( self::WORKFLOW_TIMESTAMP_COLUMN === $column_name ) {
			return self::CAP_WORKFLOW_TIMESTAMP;
		}
		return null;
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

		if ( ! is_array( $token ) || ! is_array( $entry ) || ! isset( $entry['form_id'], $entry['id'] ) ) {
			return false;
		}

		$form_id       = $this->positive_decimal_id( $form_id );
		$entry_form_id = $this->positive_decimal_id( $entry['form_id'] );
		$entry_id      = $this->positive_decimal_id( $entry['id'] );

		return (
			null !== $form_id &&
			null !== $entry_form_id &&
			null !== $entry_id &&
			$token['form_id'] === $form_id &&
			$token['form_id'] === $entry_form_id &&
			$token['entry_id'] === $entry_id
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
	 * Normalize only canonical positive integer IDs used by the qualified host.
	 *
	 * @param mixed $value Candidate ID.
	 * @return string|null
	 */
	private function positive_decimal_id( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? (string) $value : null;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Validate Gravity Flow product identity without making version equality an
	 * activation oracle. The callback/data/context contract supplies capability
	 * compatibility; the version remains bounded diagnostic evidence only.
	 *
	 * @return bool
	 */
	private function has_qualified_host_identity() {
		if ( ! defined( self::HOST_VERSION_CONSTANT ) || ! defined( self::HOST_BASENAME_CONSTANT ) ) {
			return false;
		}

		$version = trim( (string) constant( self::HOST_VERSION_CONSTANT ) );
		if ( '' === $version || strlen( $version ) > 32 || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/', $version ) ) {
			return false;
		}

		$plugin_basename = str_replace( '\\', '/', (string) constant( self::HOST_BASENAME_CONSTANT ) );
		return self::HOST_PLUGIN_BASENAME === $plugin_basename;
	}

	/**
	 * Record reporting evidence only after this adapter has made its own decision.
	 *
	 * @return void
	 */
	private function record_diagnostic( $capability_id, $state_constant, $reason_constant ) {
		if ( ! class_exists( 'PGR_Gravity_Flow_Compatibility_Diagnostics', false ) ) {
			return;
		}

		$diagnostics = 'PGR_Gravity_Flow_Compatibility_Diagnostics';
		$state       = constant( $diagnostics . '::' . $state_constant );
		$reason      = constant( $diagnostics . '::' . $reason_constant );
		$diagnostics::record( $capability_id, $state, $reason );
	}
}
