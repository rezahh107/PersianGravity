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

	/** Exact host-owned runtime version authority admitted by the product registry. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/**
	 * Register only the documented Gravity Flow Inbox presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_inbox_field_value', array( $this, 'filter_inbox_value' ), 20, 4 );
	}

	/**
	 * Convert only the two admitted human-readable Inbox system-date values.
	 *
	 * Gravity Flow keeps the corresponding `date_created` and `last_updated`
	 * values as independent AG Grid compare values. This callback intentionally
	 * never touches those raw identities.
	 *
	 * @param mixed        $value    Native display value.
	 * @param int          $form_id  Current Gravity Forms form ID.
	 * @param int|string   $field_id Gravity Flow Inbox column identity.
	 * @param array<mixed> $entry    Current Gravity Forms entry with Flow meta.
	 * @return mixed
	 */
	public function filter_inbox_value( $value, $form_id, $field_id, $entry ) {
		unset( $form_id );

		if ( ! $this->is_exact_supported_host() || ! class_exists( 'PGR_Jalali_Presentation', false ) || ! is_array( $entry ) ) {
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
	 * Admit only the exact runtime version already owned by the repository's
	 * product registry. The manifest binds its target version to the host's
	 * version constant without duplicating the foreign product domain here.
	 * Missing or drifted authority is always native fallback.
	 *
	 * @return bool
	 */
	private function is_exact_supported_host() {
		if ( ! defined( self::HOST_VERSION_CONSTANT ) || ! defined( 'PGR_PATH' ) ) {
			return false;
		}

		$registry_path = PGR_PATH . 'includes/localization/products.php';
		if ( ! is_readable( $registry_path ) ) {
			return false;
		}

		$products = require $registry_path;
		$target   = '';
		foreach ( $products as $product ) {
			if ( self::HOST_VERSION_CONSTANT !== ( $product['runtime_version_constant'] ?? '' ) ) {
				continue;
			}
			$target = isset( $product['target_version'] ) ? (string) $product['target_version'] : '';
			break;
		}

		return '' !== $target && (string) constant( self::HOST_VERSION_CONSTANT ) === $target;
	}
}
