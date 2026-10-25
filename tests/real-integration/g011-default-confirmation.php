<?php
/**
 * Select the persisted default Text confirmation for the G-011 browser fixture.
 *
 * This is test-only code. GFAPI::update_form() rekeys confirmations by their
 * IDs; the original array index is not a reliable persisted lookup key.
 *
 * @package PersianGravityForms
 */

/**
 * @param array $form A freshly retrieved GFAPI form.
 * @return array{key:int|string, confirmation:array}
 * @throws RuntimeException When there is not exactly one valid active default Text confirmation.
 */
function pgr_g011_select_default_text_confirmation( array $form ): array {
	$confirmations = $form['confirmations'] ?? null;
	if ( ! is_array( $confirmations ) ) {
		throw new RuntimeException( 'G-011: Persisted confirmations are missing or invalid.' );
	}

	$defaults = array();
	foreach ( $confirmations as $key => $confirmation ) {
		if ( ! is_array( $confirmation ) ) {
			continue;
		}
		if ( ! empty( $confirmation['isActive'] ) && ! empty( $confirmation['isDefault'] ) ) {
			$defaults[ $key ] = $confirmation;
		}
	}

	if ( 1 !== count( $defaults ) ) {
		throw new RuntimeException( 'G-011: Expected exactly one active default confirmation; found ' . count( $defaults ) . '.' );
	}

	$key          = array_key_first( $defaults );
	$confirmation = $defaults[ $key ];

	if (
		'message' !== ( $confirmation['type'] ?? null ) ||
		! isset( $confirmation['id'] ) ||
		! is_string( $confirmation['id'] ) ||
		'' === trim( $confirmation['id'] ) ||
		(string) $key !== $confirmation['id'] ||
		! isset( $confirmation['message'] ) ||
		! is_string( $confirmation['message'] )
	) {
		throw new RuntimeException( 'G-011: Persisted active default is not a valid keyed Text confirmation.' );
	}

	return array(
		'key'          => $key,
		'confirmation' => $confirmation,
	);
}
