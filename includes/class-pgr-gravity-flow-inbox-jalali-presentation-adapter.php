<?php
/**
 * Bounded Jalali presentation for admitted Gravity Flow Inbox system dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter {

	/** Gravity Flow's display-only companion for the raw Submitted compare value. */
	private const DATE_CREATED_DISPLAY_ID = 'date_created_human_readable';

	/** Gravity Flow's display-only companion for the raw Last Updated compare value. */
	private const LAST_UPDATED_DISPLAY_ID = 'last_updated_human_readable';

	/** Gravity Flow's raw Due Date compare-value identity. */
	private const DUE_DATE_RAW_ID = 'due_date';

	/** Gravity Flow's display-only companion for the raw Due Date compare value. */
	private const DUE_DATE_DISPLAY_ID = 'due_date_human_readable';

	/** Host-owned runtime version observation. Version equality is not eligibility. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Canonical Gravity Flow plugin main-file identity. */
	private const HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php';

	private const CAP_DATE_CREATED = 'gravityflow.inbox.date-created';
	private const CAP_LAST_UPDATED = 'gravityflow.inbox.last-updated';
	private const CAP_DUE_DATE     = 'gravityflow.inbox.due-date';

	/**
	 * One-shot raw due-date authority for the immediately following display value.
	 *
	 * @var array{key:string,value:int}|null
	 */
	private $pending_due_date_raw = null;

	/** @var string|null */
	private $pending_due_date_failure_reason = null;

	/**
	 * Register only the documented Gravity Flow Inbox presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_inbox_field_value', array( $this, 'filter_inbox_value' ), 20, 4 );
	}

	/**
	 * Convert only the admitted human-readable Inbox system-date values.
	 *
	 * Gravity Flow keeps the corresponding raw values as independent AG Grid
	 * compare values. This callback intentionally never touches those raw
	 * identities or workflow-owned due-date state.
	 *
	 * @param mixed        $value    Native display value.
	 * @param int          $form_id  Current Gravity Forms form ID.
	 * @param int|string   $field_id Gravity Flow Inbox column identity.
	 * @param array<mixed> $entry    Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_inbox_value( $value, $form_id, $field_id, $entry ) {
		$capability_id = $this->capability_for_field( $field_id );

		if ( null === $capability_id ) {
			$this->clear_due_date_proof();
			return $value;
		}

		if ( ! $this->has_qualified_host_identity() ) {
			$this->clear_due_date_proof();
			$this->record_diagnostic( $capability_id, 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED' );
			return $value;
		}

		if ( self::DUE_DATE_RAW_ID === $field_id ) {
			$this->capture_due_date_raw( $value, $form_id, $entry );
			return $value;
		}

		if ( ! is_array( $entry ) ) {
			$this->clear_due_date_proof();
			$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return $value;
		}

		if ( self::DUE_DATE_DISPLAY_ID === $field_id ) {
			$proof = $this->consume_due_date_raw( $form_id, $entry );
			if ( null !== $proof['reason'] ) {
				$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', $proof['reason'] );
				return $value;
			}

			$raw_due_date = $proof['value'];
			if ( '-' === $value ) {
				if ( 0 === $raw_due_date ) {
					$this->record_available( self::CAP_DUE_DATE );
				} else {
					$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
				}
				return $value;
			}

			if ( 0 === $raw_due_date ) {
				$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
				return $value;
			}

			$source = $this->absolute_timestamp_source( $raw_due_date );
			if ( null === $source ) {
				$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_SOURCE_INVALID' );
				return $value;
			}
		} else {
			$this->clear_due_date_proof();

			if ( self::DATE_CREATED_DISPLAY_ID === $field_id ) {
				$source = $this->date_created_source( $entry );
			} else {
				if ( '-' === $value ) {
					$this->record_available( self::CAP_LAST_UPDATED );
					return $value;
				}
				$source = $this->last_updated_source( $entry );
			}

			if ( null === $source ) {
				$this->record_diagnostic( $capability_id, 'STATE_DEGRADED', 'REASON_SOURCE_INVALID' );
				return $value;
			}
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

		$this->record_available( $capability_id );
		return $formatted;
	}

	/** @return string|null */
	private function capability_for_field( $field_id ) {
		if ( self::DATE_CREATED_DISPLAY_ID === $field_id ) {
			return self::CAP_DATE_CREATED;
		}
		if ( self::LAST_UPDATED_DISPLAY_ID === $field_id ) {
			return self::CAP_LAST_UPDATED;
		}
		if ( self::DUE_DATE_RAW_ID === $field_id || self::DUE_DATE_DISPLAY_ID === $field_id ) {
			return self::CAP_DUE_DATE;
		}
		return null;
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
	private function last_updated_source( $entry ) {
		if ( ! isset( $entry['workflow_timestamp'] ) ) {
			return null;
		}

		return $this->absolute_timestamp_source( $entry['workflow_timestamp'] );
	}

	/**
	 * Capture the raw Inbox due-date value already computed by Gravity Flow.
	 *
	 * @param mixed $value   Native raw due-date compare value.
	 * @param mixed $form_id Current form ID.
	 * @param mixed $entry   Current entry.
	 * @return void
	 */
	private function capture_due_date_raw( $value, $form_id, $entry ) {
		$this->clear_due_date_proof();

		if ( ! is_array( $entry ) ) {
			$this->pending_due_date_failure_reason = 'REASON_CONTEXT_UNAVAILABLE';
			$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return;
		}

		$key = $this->due_date_capture_key( $form_id, $entry );
		if ( null === $key ) {
			$this->pending_due_date_failure_reason = 'REASON_CONTEXT_UNAVAILABLE';
			$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return;
		}

		if ( ! is_int( $value ) || $value < 0 ) {
			$this->pending_due_date_failure_reason = 'REASON_SOURCE_INVALID';
			$this->record_diagnostic( self::CAP_DUE_DATE, 'STATE_DEGRADED', 'REASON_SOURCE_INVALID' );
			return;
		}

		$this->pending_due_date_raw = array(
			'key'   => $key,
			'value' => $value,
		);
	}

	/**
	 * Consume one raw due-date proof only for the immediately following display
	 * callback of the same form/entry row.
	 *
	 * @param mixed        $form_id Current form ID.
	 * @param array<mixed> $entry   Current entry.
	 * @return array{value:int|null,reason:string|null}
	 */
	private function consume_due_date_raw( $form_id, $entry ) {
		$pending = $this->pending_due_date_raw;
		$reason  = $this->pending_due_date_failure_reason;
		$this->clear_due_date_proof();

		if ( null !== $reason ) {
			return array(
				'value'  => null,
				'reason' => $reason,
			);
		}

		$key = $this->due_date_capture_key( $form_id, $entry );
		if (
			! is_array( $pending ) ||
			! isset( $pending['key'], $pending['value'] ) ||
			null === $key ||
			$pending['key'] !== $key ||
			! is_int( $pending['value'] )
		) {
			return array(
				'value'  => null,
				'reason' => 'REASON_CONTEXT_UNAVAILABLE',
			);
		}

		return array(
			'value'  => $pending['value'],
			'reason' => null,
		);
	}

	/** @return void */
	private function clear_due_date_proof() {
		$this->pending_due_date_raw            = null;
		$this->pending_due_date_failure_reason = null;
	}

	/**
	 * Build the request-local due-date proof key without coercing loose IDs.
	 *
	 * @param mixed        $form_id Current form ID.
	 * @param array<mixed> $entry   Current entry.
	 * @return string|null
	 */
	private function due_date_capture_key( $form_id, $entry ) {
		if ( ! isset( $entry['id'], $entry['form_id'] ) ) {
			return null;
		}

		$form_id       = $this->positive_decimal_id( $form_id );
		$entry_form_id = $this->positive_decimal_id( $entry['form_id'] );
		$entry_id      = $this->positive_decimal_id( $entry['id'] );

		if ( null === $form_id || null === $entry_form_id || null === $entry_id || $entry_form_id !== $form_id ) {
			return null;
		}

		return $form_id . ':' . $entry_id;
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
	 * Strictly convert a positive Unix timestamp to an absolute instant.
	 *
	 * @param mixed $raw Host-owned timestamp value.
	 * @return DateTimeImmutable|null
	 */
	private function absolute_timestamp_source( $raw ) {
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

	/** @return void */
	private function record_available( $capability_id ) {
		$this->record_diagnostic( $capability_id, 'STATE_AVAILABLE', 'REASON_CONTRACT_SATISFIED' );
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
