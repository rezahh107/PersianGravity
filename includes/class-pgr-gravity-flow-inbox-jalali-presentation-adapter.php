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

	/** Gravity Flow's host-owned raw Due Date compare value. */
	private const DUE_DATE_RAW_ID = 'due_date';

	/** Gravity Flow's display-only companion for the raw Due Date compare value. */
	private const DUE_DATE_DISPLAY_ID = 'due_date_human_readable';

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/**
	 * One-shot raw due-date authority for the immediately following display value.
	 *
	 * @var array{key:string,value:int|null}|null
	 */
	private $pending_due_date_raw = null;

	/**
	 * Register only the documented Gravity Flow Inbox presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_inbox_field_value', array( $this, 'filter_inbox_value' ), 20, 4 );
	}

	/**
	 * Convert only admitted human-readable Inbox system-date values.
	 *
	 * Exact Gravity Flow 3.1.0 passes the raw due_date compare value through
	 * this same seam before due_date_human_readable for the row. PersianGravity
	 * observes that already-computed raw value without changing it, then consumes
	 * it only for the later display callback. It never re-enters Flow's operational
	 * due-date getter.
	 *
	 * @param mixed        $value    Native Inbox value.
	 * @param int          $form_id  Current Gravity Forms form ID.
	 * @param int|string   $field_id Gravity Flow Inbox column identity.
	 * @param array<mixed> $entry    Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_inbox_value( $value, $form_id, $field_id, $entry ) {
		if ( ! $this->is_exact_supported_host() || ! is_array( $entry ) ) {
			$this->pending_due_date_raw = null;
			return $value;
		}

		if ( self::DUE_DATE_RAW_ID === $field_id ) {
			$key = $this->due_date_capture_key( $form_id, $entry );
			$this->pending_due_date_raw = null === $key
				? null
				: array(
					'key' => $key,
					'raw' => $this->qualified_due_date_raw( $value ),
				);
			return $value;
		}

		if ( self::DUE_DATE_DISPLAY_ID === $field_id ) {
			$key     = $this->due_date_capture_key( $form_id, $entry );
			$pending = $this->pending_due_date_raw;
			$this->pending_due_date_raw = null;

			if (
				null === $key ||
				! is_array( $pending ) ||
				$pending['key'] !== $key ||
				! class_exists( 'PGR_Jalali_Presentation', false )
			) {
				return $value;
			}

			$raw = $pending['raw'];

			// Exact Gravity Flow 3.1.0 uses '-' when the current step has no due
			// date. Raw 0 is the matching no-due compare value.
			if ( '-' === $value || null === $raw || 0 === $raw ) {
				return $value;
			}

			$source = $this->absolute_timestamp_source( $raw );
			if ( null === $source ) {
				return $value;
			}

			$formatted = PGR_Jalali_Presentation::format_datetime( $source );
			return null === $formatted ? $value : $formatted;
		}

		// Exact Flow 3.1.0 emits due_date_human_readable immediately after raw
		// due_date. Any intervening identity invalidates the ordering authority.
		$this->pending_due_date_raw = null;

		if ( ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			return $value;
		}

		if ( self::DATE_CREATED_DISPLAY_ID === $field_id ) {
			$source = $this->date_created_source( $entry );
		} elseif ( self::LAST_UPDATED_DISPLAY_ID === $field_id ) {
			// Gravity Flow uses this sentinel when the workflow timestamp still
			// represents the original submission instant. Preserve that contract.
			if ( '-' === $value ) {
				return $value;
			}
			$source = $this->last_updated_source( $entry );
		} else {
			return $value;
		}

		if ( null === $source ) {
			return $value;
		}

		$formatted = PGR_Jalali_Presentation::format_datetime( $source );
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
	private function last_updated_source( $entry ) {
		if ( ! isset( $entry['workflow_timestamp'] ) ) {
			return null;
		}

		return $this->absolute_timestamp_source( $entry['workflow_timestamp'] );
	}

	/**
	 * Capture the already-computed raw due-date value for the next display cell.
	 *
	 * @param mixed        $value   Native raw due-date compare value.
	 * @param mixed        $form_id Current form ID.
	 * @param array<mixed> $entry   Current entry.
	 * @return void
	 */
	private function capture_due_date_raw( $value, $form_id, $entry ) {
		$this->pending_due_date_raw = null;

		$key = $this->due_date_capture_key( $form_id, $entry );
		if ( null === $key ) {
			return;
		}

		$this->pending_due_date_raw = array(
			'key'   => $key,
			'value' => $this->qualified_due_date_raw( $value ),
		);
	}

	/**
	 * Consume one raw due-date proof for the same form/entry display callback.
	 *
	 * @param mixed        $form_id Current form ID.
	 * @param array<mixed> $entry   Current entry.
	 * @return int|null
	 */
	private function consume_due_date_raw( $form_id, $entry ) {
		$pending                    = $this->pending_due_date_raw;
		$this->pending_due_date_raw = null;
		$key                        = $this->due_date_capture_key( $form_id, $entry );

		if (
			! is_array( $pending ) ||
			! isset( $pending['key'] ) ||
			! array_key_exists( 'value', $pending ) ||
			null === $key ||
			$pending['key'] !== $key
		) {
			return null;
		}

		return $pending['value'];
	}

	/**
	 * Build a request-local due-date capture key from host row identity.
	 *
	 * @param mixed        $form_id Current form ID.
	 * @param array<mixed> $entry   Current entry.
	 * @return string|null
	 */
	private function due_date_capture_key( $form_id, $entry ) {
		$form_identity       = $this->positive_integer_identity( $form_id );
		$entry_identity      = isset( $entry['id'] ) ? $this->positive_integer_identity( $entry['id'] ) : null;
		$entry_form_identity = isset( $entry['form_id'] ) ? $this->positive_integer_identity( $entry['form_id'] ) : null;

		if (
			null === $form_identity ||
			null === $entry_identity ||
			null === $entry_form_identity ||
			$form_identity !== $entry_form_identity
		) {
			return null;
		}

		return $form_identity . ':' . $entry_identity;
	}

	/**
	 * Accept only the qualified raw Gravity Flow due-date representation.
	 *
	 * Raw 0 is intentionally retained as the host-owned no-due-date state.
	 *
	 * @param mixed $raw Host-owned raw Inbox due_date value.
	 * @return int|null
	 */
	private function qualified_due_date_raw( $raw ) {
		if ( ! is_int( $raw ) || $raw < 0 ) {
			return null;
		}

		return $raw;
	}

	/**
	 * Normalize a positive integer identity without loose numeric coercion.
	 *
	 * @param mixed $raw Host-owned identity.
	 * @return string|null
	 */
	private function positive_integer_identity( $raw ) {
		if ( is_int( $raw ) && $raw > 0 ) {
			return (string) $raw;
		}

		if ( is_string( $raw ) && 1 === preg_match( '/^[1-9][0-9]*$/', $raw ) ) {
			return $raw;
		}

		return null;
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
	 * Admit only the exact host product/version already owned by the existing
	 * product registry. The host supplies both its version and plugin basename;
	 * the basename resolves the matching manifest product without duplicating a
	 * foreign product domain in production code. Missing or drifted authority is
	 * always native fallback.
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
