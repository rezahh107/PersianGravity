<?php
/**
 * Structured Scanner profile registry and v1 custom-profile persistence.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Scanner_Profile_Registry {

	const OPTION          = 'pgr_scanner_profiles';
	const SCHEMA_VERSION  = 1;
	const SAYAD_V01       = 'sayad_v01';
	const PARSER_SEGMENTS = 'segments_v1';

	/**
	 * Return source-defined built-in profiles.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function built_in_profiles() {
		return array(
			self::SAYAD_V01 => array(
				'id'        => self::SAYAD_V01,
				'label'     => __( 'Sayad v0.1', 'persian-gravityforms' ),
				'enabled'   => true,
				'kind'      => 'built_in',
				'read_only' => true,
				'parser'    => array(
					'type'             => self::PARSER_SEGMENTS,
					'separator'        => 'newline',
					'trim'             => true,
					'normalize_digits' => true,
				),
				'outputs'   => array(
					array( 'key' => 'qr_version', 'label' => __( 'QR version', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'owner_type', 'label' => __( 'Owner type', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'owner_identifier', 'label' => __( 'Owner identifier', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'iban', 'label' => __( 'IBAN', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'bank_branch', 'label' => __( 'Bank branch', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'cheque_serial', 'label' => __( 'Cheque serial', 'persian-gravityforms' ), 'required' => true ),
					array( 'key' => 'sayad_id', 'label' => __( 'Sayad ID', 'persian-gravityforms' ), 'required' => true ),
				),
			),
		);
	}

	/**
	 * Return validated custom profiles from the versioned option.
	 *
	 * Malformed stored records are skipped instead of becoming executable.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function custom_profiles() {
		$stored = self::normalize_option( get_option( self::OPTION, array() ) );
		$result = array();

		foreach ( $stored['profiles'] as $profile ) {
			$validation = self::validate_profile( $profile );
			if ( ! $validation['valid'] ) {
				continue;
			}

			$normalized = $validation['profile'];
			if ( isset( self::built_in_profiles()[ $normalized['id'] ] ) ) {
				continue;
			}

			$result[ $normalized['id'] ] = $normalized;
		}

		return $result;
	}

	/**
	 * Return effective built-in + validated custom registry.
	 *
	 * Built-in IDs always win.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		return self::built_in_profiles() + self::custom_profiles();
	}

	/**
	 * Return enabled effective profiles.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function active() {
		return array_filter(
			self::all(),
			static function ( $profile ) {
				return ! empty( $profile['enabled'] );
			}
		);
	}

	/**
	 * Return one profile or null.
	 *
	 * Disabled profiles remain resolvable for existing form metadata.
	 *
	 * @param string $profile_id Profile ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $profile_id ) {
		$profiles = self::all();
		return isset( $profiles[ $profile_id ] ) ? $profiles[ $profile_id ] : null;
	}

	/**
	 * Return ordered output keys.
	 *
	 * @param string $profile_id Profile ID.
	 * @return array<int,string>
	 */
	public static function output_keys( $profile_id ) {
		$profile = self::get( $profile_id );
		if ( null === $profile ) {
			return array();
		}

		return array_values(
			array_map(
				static function ( $output ) {
					return $output['key'];
				},
				$profile['outputs']
			)
		);
	}

	/**
	 * Return runtime-safe, enabled profile definitions for the pure JS core.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function runtime_profiles() {
		$profiles = array();

		foreach ( self::active() as $profile ) {
			$profiles[] = array(
				'id'      => $profile['id'],
				'parser'  => $profile['parser'],
				'outputs' => array_values(
					array_map(
						static function ( $output ) {
							return array(
								'key'      => $output['key'],
								'required' => (bool) $output['required'],
							);
						},
						$profile['outputs']
					)
				),
			);
		}

		return $profiles;
	}

	/**
	 * Validate and normalize one custom profile definition.
	 *
	 * @param mixed  $profile     Candidate profile.
	 * @param string $existing_id Existing immutable ID when editing.
	 * @return array{valid:bool,profile:array<string,mixed>,errors:array<int,string>}
	 */
	public static function validate_profile( $profile, $existing_id = '' ) {
		$errors = array();
		$input  = is_array( $profile ) ? $profile : array();

		$id = isset( $input['id'] ) && is_scalar( $input['id'] ) ? trim( (string) $input['id'] ) : '';
		if ( '' !== $existing_id && $id !== $existing_id ) {
			$errors[] = 'immutable_id';
		}
		if ( '' === $id ) {
			$errors[] = 'missing_id';
		} elseif ( 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $id ) ) {
			$errors[] = 'invalid_id';
		}

		$label = isset( $input['label'] ) && is_scalar( $input['label'] ) ? trim( (string) $input['label'] ) : '';
		if ( '' === $label ) {
			$errors[] = 'missing_label';
		}

		$parser = isset( $input['parser'] ) && is_array( $input['parser'] ) ? $input['parser'] : array();
		$type   = isset( $parser['type'] ) && is_scalar( $parser['type'] ) ? (string) $parser['type'] : '';
		if ( self::PARSER_SEGMENTS !== $type ) {
			$errors[] = 'unsupported_parser';
		}

		$separator = isset( $parser['separator'] ) && is_scalar( $parser['separator'] ) ? (string) $parser['separator'] : '';
		if ( 'newline' !== $separator ) {
			$errors[] = 'unsupported_separator';
		}

		$outputs = isset( $input['outputs'] ) && is_array( $input['outputs'] ) ? $input['outputs'] : array();
		if ( empty( $outputs ) ) {
			$errors[] = 'zero_outputs';
		}

		$normalized_outputs = array();
		$seen_keys          = array();

		foreach ( $outputs as $output ) {
			if ( ! is_array( $output ) ) {
				$errors[] = 'invalid_output';
				continue;
			}

			$key = isset( $output['key'] ) && is_scalar( $output['key'] ) ? trim( (string) $output['key'] ) : '';
			if ( '' === $key || 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $key ) ) {
				$errors[] = 'invalid_output_key';
			}
			if ( '' !== $key && isset( $seen_keys[ $key ] ) ) {
				$errors[] = 'duplicate_output_key';
			}
			$seen_keys[ $key ] = true;

			$output_label = isset( $output['label'] ) && is_scalar( $output['label'] ) ? trim( (string) $output['label'] ) : '';
			if ( '' === $output_label ) {
				$errors[] = 'missing_output_label';
			}

			$normalized_outputs[] = array(
				'key'      => $key,
				'label'    => $output_label,
				'required' => ! empty( $output['required'] ),
			);
		}

		$normalized = array(
			'id'        => $id,
			'label'     => $label,
			'enabled'   => ! empty( $input['enabled'] ),
			'kind'      => 'custom',
			'read_only' => false,
			'parser'    => array(
				'type'             => $type,
				'separator'        => $separator,
				'trim'             => ! empty( $parser['trim'] ),
				'normalize_digits' => ! empty( $parser['normalize_digits'] ),
			),
			'outputs'   => $normalized_outputs,
		);

		return array(
			'valid'   => empty( $errors ),
			'profile' => $normalized,
			'errors'  => array_values( array_unique( $errors ) ),
		);
	}

	/**
	 * Normalize the versioned storage envelope.
	 *
	 * @param mixed $stored Raw option value.
	 * @return array{schema_version:int,profiles:array<int,array<string,mixed>>}
	 */
	public static function normalize_option( $stored ) {
		if (
			! is_array( $stored ) ||
			! isset( $stored['schema_version'] ) ||
			self::SCHEMA_VERSION !== (int) $stored['schema_version'] ||
			! isset( $stored['profiles'] ) ||
			! is_array( $stored['profiles'] )
		) {
			return array(
				'schema_version' => self::SCHEMA_VERSION,
				'profiles'       => array(),
			);
		}

		$profiles = array();
		foreach ( $stored['profiles'] as $profile ) {
			$validation = self::validate_profile( $profile );
			if ( ! $validation['valid'] ) {
				continue;
			}
			if ( isset( self::built_in_profiles()[ $validation['profile']['id'] ] ) ) {
				continue;
			}
			$profiles[] = $validation['profile'];
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'profiles'       => $profiles,
		);
	}

	/**
	 * Save a new or existing custom profile.
	 *
	 * @param mixed  $profile     Candidate profile.
	 * @param string $existing_id Existing immutable ID when editing.
	 * @return array{success:bool,profile:array<string,mixed>|null,errors:array<int,string>}
	 */
	public static function save_custom_profile( $profile, $existing_id = '' ) {
		$validation = self::validate_profile( $profile, $existing_id );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => $validation['errors'],
			);
		}

		$normalized = $validation['profile'];
		if ( isset( self::built_in_profiles()[ $normalized['id'] ] ) ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'built_in_collision' ),
			);
		}

		$stored = self::normalize_option( get_option( self::OPTION, array() ) );
		$found  = false;

		foreach ( $stored['profiles'] as $index => $saved ) {
			if ( $saved['id'] !== $normalized['id'] ) {
				continue;
			}
			if ( '' === $existing_id ) {
				return array(
					'success' => false,
					'profile' => null,
					'errors'  => array( 'duplicate_profile_id' ),
				);
			}
			$stored['profiles'][ $index ] = $normalized;
			$found                         = true;
			break;
		}

		if ( '' !== $existing_id && ! $found ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'profile_not_found' ),
			);
		}

		if ( '' === $existing_id ) {
			$stored['profiles'][] = $normalized;
		}

		self::persist_option( $stored );

		return array(
			'success' => true,
			'profile' => $normalized,
			'errors'  => array(),
		);
	}

	/**
	 * Delete one custom profile.
	 *
	 * Existing form mappings are intentionally not rewritten.
	 *
	 * @param string $profile_id Profile ID.
	 * @return bool
	 */
	public static function delete_custom_profile( $profile_id ) {
		if ( isset( self::built_in_profiles()[ $profile_id ] ) ) {
			return false;
		}

		$stored   = self::normalize_option( get_option( self::OPTION, array() ) );
		$before   = count( $stored['profiles'] );
		$profiles = array();

		foreach ( $stored['profiles'] as $profile ) {
			if ( $profile['id'] !== $profile_id ) {
				$profiles[] = $profile;
			}
		}

		if ( $before === count( $profiles ) ) {
			return false;
		}

		$stored['profiles'] = $profiles;
		self::persist_option( $stored );
		return true;
	}

	/**
	 * Scalar Gravity Forms field types supported as Scanner destinations.
	 *
	 * @return array<int,string>
	 */
	public static function supported_target_types() {
		return array( 'text', 'hidden' );
	}

	/**
	 * Persist custom profiles as one non-autoloaded versioned option.
	 *
	 * @param array<string,mixed> $value Option value.
	 * @return void
	 */
	private static function persist_option( $value ) {
		$existing = get_option( self::OPTION, null );
		if ( null === $existing && function_exists( 'add_option' ) ) {
			add_option( self::OPTION, $value, '', false );
			return;
		}

		update_option( self::OPTION, $value, false );
	}
}
