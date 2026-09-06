<?php
/**
 * Structured Scanner profile metadata shared by the Gravity Forms field/editor layer.
 *
 * Parsing remains client-side in the pure JavaScript Scanner core. This class contains
 * configuration metadata only and never receives scanner payloads.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Scanner_Profile_Registry {

	const SAYAD_V01 = 'sayad_v01';

	/**
	 * Return registered Scanner profiles.
	 *
	 * @return array<string, array{label:string, outputs:array<string,string>}>
	 */
	public static function all() {
		return array(
			self::SAYAD_V01 => array(
				'label'   => __( 'Sayad v0.1', 'persian-gravityforms' ),
				'outputs' => array(
					'qr_version'       => __( 'QR version', 'persian-gravityforms' ),
					'owner_type'       => __( 'Owner type', 'persian-gravityforms' ),
					'owner_identifier' => __( 'Owner identifier', 'persian-gravityforms' ),
					'iban'             => __( 'IBAN', 'persian-gravityforms' ),
					'bank_branch'      => __( 'Bank branch', 'persian-gravityforms' ),
					'cheque_serial'    => __( 'Cheque serial', 'persian-gravityforms' ),
					'sayad_id'         => __( 'Sayad ID', 'persian-gravityforms' ),
				),
			),
		);
	}

	/**
	 * Return one profile or null when the ID is unknown.
	 *
	 * @param string $profile_id Profile ID.
	 * @return array{label:string, outputs:array<string,string>}|null
	 */
	public static function get( $profile_id ) {
		$profiles = self::all();
		return isset( $profiles[ $profile_id ] ) ? $profiles[ $profile_id ] : null;
	}

	/**
	 * Return ordered output keys for a profile.
	 *
	 * @param string $profile_id Profile ID.
	 * @return array<int,string>
	 */
	public static function output_keys( $profile_id ) {
		$profile = self::get( $profile_id );
		return null === $profile ? array() : array_keys( $profile['outputs'] );
	}

	/**
	 * Scalar Gravity Forms field types supported as Scanner destinations in v0.1.
	 *
	 * @return array<int,string>
	 */
	public static function supported_target_types() {
		return array( 'text', 'hidden' );
	}
}
