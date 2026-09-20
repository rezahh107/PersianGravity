<?php
/**
 * Bounded Gravity Forms Entries List adapter for G-008.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GF_Jalali_Presentation_Adapter {

	/** Register only the display-time Entry List seam. */
	public function hooks(): void {
		add_filter( 'gform_entries_field_value', array( __CLASS__, 'filter_entry_list_value' ), 10, 4 );
	}

	/**
	 * Present the authoritative UTC date_created property as Jalali.
	 *
	 * Gravity Forms keeps the Entry value untouched; this filter changes only
	 * the string returned for the Entry List display cell.
	 *
	 * @param mixed  $value    Native display value.
	 * @param int    $form_id  Current form ID.
	 * @param mixed  $field_id Field/property identifier.
	 * @param array  $entry    Current Entry object.
	 * @return mixed
	 */
	public static function filter_entry_list_value( $value, $form_id, $field_id, $entry ) {
		unset( $form_id );

		if ( 'date_created' !== (string) $field_id || ! is_array( $entry ) ) {
			return $value;
		}

		$raw = $entry['date_created'] ?? null;
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $value;
		}

		$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if (
			false === $source ||
			( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
			$source->format( 'Y-m-d H:i:s' ) !== $raw
		) {
			return $value;
		}

		$presented = PGR_Jalali_Presentation::format_datetime( $source );
		return null === $presented ? $value : $presented;
	}
}
