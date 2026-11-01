<?php
/**
 * Structured Scanner profiles: versioned incarnation identities and guarded option writes.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Scanner_Profile_Registry {

	const OPTION          = 'pgr_scanner_profiles';
	const SCHEMA_VERSION  = 2;
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
					array(
						'key'      => 'qr_version',
						'label'    => __( 'QR version', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'owner_type',
						'label'    => __( 'Owner type', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'owner_identifier',
						'label'    => __( 'Owner identifier', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'iban',
						'label'    => __( 'IBAN', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'bank_branch',
						'label'    => __( 'Bank branch', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'cheque_serial',
						'label'    => __( 'Cheque serial', 'persian-gravityforms' ),
						'required' => true,
					),
					array(
						'key'      => 'sayad_id',
						'label'    => __( 'Sayad ID', 'persian-gravityforms' ),
						'required' => true,
					),
				),
			),
		);
	}

	/**
	 * Return validated custom profiles from the versioned option.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function custom_profiles() {
		$stored = self::normalize_option( self::raw_state()['value'] );
		$result = array();

		foreach ( $stored['profiles'] as $profile ) {
			$validation = self::validate_profile( $profile );
			if ( ! $validation['valid'] ) {
				continue;
			}

			$normalized = $profile; // normalize_option() already validated the source and preserved v2 identity.
			if ( isset( self::built_in_profiles()[ $normalized['id'] ] ) ) {
				continue;
			}

			$result[ $normalized['id'] ] = $normalized;
		}

		return $result;
	}

	/**
	 * Return effective built-in and validated custom profiles.
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
	 * Return one effective profile.
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
	 * Return runtime-safe enabled profile definitions for the pure JS core.
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
	 * Fingerprint the complete normalized, validated custom profile snapshot.
	 *
	 * Includes display metadata, enabled state, parser settings and all output
	 * labels/keys/flags. Identity/revision distinguish identical-content recreation from the
	 * original creation; every metadata edit advances the revision.
	 *
	 * @param mixed $profile Candidate profile.
	 * @return string SHA-256 hex, or an empty string for invalid input.
	 */

	public static function snapshot_fingerprint( $profile ) {
		$validation = self::validate_profile( $profile );
		if ( ! $validation['valid'] || ! is_array( $profile ) ||
			! isset( $profile['incarnation_id'], $profile['revision'] ) ||
			! is_string( $profile['incarnation_id'] ) ||
			1 !== preg_match( '/^[a-f0-9]{32}$/', $profile['incarnation_id'] ) ||
			! is_int( $profile['revision'] ) || $profile['revision'] < 1 ||
			isset( self::built_in_profiles()[ $validation['profile']['id'] ] ) ) {
			return '';
		}

		$canonical = wp_json_encode(
			array(
				'profile'        => $validation['profile'],
				'incarnation_id' => $profile['incarnation_id'],
				'revision'       => $profile['revision'],
			)
		);
		return is_string( $canonical ) ? hash( 'sha256', $canonical ) : '';
	}

	/**
	 * Read v1 for compatibility; keep v2 metadata in the only canonical option.
	 * Unknown versions and invalid records are never eligible for mutation.
	 *
	 * @param mixed $stored Untrusted stored state.
	 * @return array<string,mixed>
	 */
	public static function normalize_option( $stored ) {
		$empty = array(
			'schema_version' => self::SCHEMA_VERSION,
			'profiles'       => array(),
		);
		if ( ! is_array( $stored ) || ! isset( $stored['schema_version'], $stored['profiles'] ) ||
			! is_int( $stored['schema_version'] ) || ! is_array( $stored['profiles'] ) ||
			! in_array( $stored['schema_version'], array( 1, self::SCHEMA_VERSION ), true ) ) {
			return $empty;
		}

		$result = array(
			'schema_version' => $stored['schema_version'],
			'profiles'       => array(),
		);
		foreach ( $stored['profiles'] as $profile ) {
			$validation = self::validate_profile( $profile );
			if ( ! $validation['valid'] || isset( self::built_in_profiles()[ $validation['profile']['id'] ] ) ) {
				continue;
			}
			$normalized = $validation['profile'];
			if ( self::SCHEMA_VERSION === $stored['schema_version'] ) {
				if ( '' === self::snapshot_fingerprint( $profile ) ) {
					continue;
				}
				$normalized['incarnation_id'] = $profile['incarnation_id'];
				$normalized['revision']       = $profile['revision'];
			}
			$result['profiles'][] = $normalized;
		}
		return $result;
	}

	/**
	 * All reads used for mutation/confirmation bypass potentially stale Options API caches.
	 * This also deliberately bypasses value-altering get_option filters. The option is
	 * plugin-owned: external direct option writers and filters are not supported.
	 *
	 * @return array{exists:bool,raw:string,value:mixed}
	 */
	private static function raw_state() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array(
				'exists'    => false,
				'raw'       => '',
				'value'     => null,
				'available' => false,
			);
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);
		if ( null === $row && ! empty( $wpdb->last_error ) ) {
			return array(
				'exists'    => false,
				'raw'       => '',
				'value'     => null,
				'available' => false,
			);
		}
		if ( is_object( $row ) ) {
			return array(
				'exists'    => true,
				'raw'       => $row->option_value,
				'value'     => maybe_unserialize( $row->option_value ),
				'available' => true,
			);
		}
		return array(
			'exists'    => false,
			'raw'       => '',
			'value'     => null,
			'available' => true,
		);
	}

	/**
	 * Only an explicit, operator-invoked migration with a verified raw backup hash
	 * may assign fresh non-content-derived identities to legacy records.
	 *
	 * @param string $backup_sha256 SHA-256 of the exact pre-migration serialized option.
	 * @return array{success:bool,reason:string}
	 */
	public static function migrate_v1_to_v2( $backup_sha256 ) {
		$before = self::raw_state();
		if ( empty( $before['available'] ) ) {
			return array(
				'success' => false,
				'reason'  => 'storage_unavailable',
			);
		}
		if ( $before['exists'] && is_array( $before['value'] ) &&
			( $before['value']['schema_version'] ?? null ) === self::SCHEMA_VERSION ) {
			if ( ! self::valid_v2( $before['value'] ) ) {
				return array(
					'success' => false,
					'reason'  => 'invalid_v2',
				);
			}
			return array(
				'success' => true,
				'reason'  => 'already_v2',
			);
		}
		if ( ! $before['exists'] || ! is_string( $backup_sha256 ) ||
			! hash_equals( hash( 'sha256', $before['raw'] ), $backup_sha256 ) ||
			! self::valid_legacy( $before['value'] ) ) {
			return array(
				'success' => false,
				'reason'  => 'backup_or_legacy_mismatch',
			);
		}
		$next = array(
			'schema_version' => self::SCHEMA_VERSION,
			'profiles'       => array(),
		);
		foreach ( $before['value']['profiles'] as $legacy ) {
			$legacy['incarnation_id'] = bin2hex( random_bytes( 16 ) );
			$legacy['revision']       = 1;
			$next['profiles'][]       = $legacy;
		}
		return self::persist_option( $next, $before )
			? array(
				'success' => true,
				'reason'  => 'migrated',
			)
			: array(
				'success' => false,
				'reason'  => 'write_conflict_or_failure',
			);
	}

	/**
	 * Produce a raw, read-only backup envelope for separately verified safe storage.
	 * The operator must retain it outside WordPress before requesting migration.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function pre_migration_backup() {
		$state = self::raw_state();
		return ! empty( $state['available'] ) && $state['exists'] && self::valid_legacy( $state['value'] )
			? array(
				'raw'    => $state['raw'],
				'sha256' => hash( 'sha256', $state['raw'] ),
			)
			: null;
	}

	/** Require a lossless, source-produced v1 envelope; never drop unknown/bad records. */
	private static function valid_legacy( $value ) {
		if ( ! is_array( $value ) || array_keys( $value ) !== array( 'schema_version', 'profiles' ) ||
			1 !== $value['schema_version'] || ! is_array( $value['profiles'] ) ||
			( ! empty( $value['profiles'] ) && array_keys( $value['profiles'] ) !== range( 0, count( $value['profiles'] ) - 1 ) ) ) {
			return false;
		}
		$seen = array();
		foreach ( $value['profiles'] as $profile ) {
			$check = self::validate_profile( $profile );
			if ( ! $check['valid'] || $check['profile'] !== $profile ||
				isset( self::built_in_profiles()[ $profile['id'] ] ) || isset( $seen[ $profile['id'] ] ) ) {
				return false;
			}
			$seen[ $profile['id'] ] = true;
		}
		return true;
	}

	/**
	 * Create/edit only against v2; a stale opened edit form cannot overwrite a newer revision.
	 * The executable parser/output contract is immutable for the same incarnation.
	 *
	 * @param mixed $profile Candidate profile.
	 * @param string $existing_id Existing profile ID on edit.
	 * @param int|null $expected_revision Exact revision of opened edit form.
	 * @param string $expected_incarnation Exact incarnation of opened edit form.
	 * @return array<string,mixed>
	 */
	public static function save_custom_profile( $profile, $existing_id = '', $expected_revision = null, $expected_incarnation = '' ) {
		$check = self::validate_profile( $profile, $existing_id );
		if ( ! $check['valid'] ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => $check['errors'],
			);
		}
		$normalized = $check['profile'];
		if ( '' !== $existing_id && ( ! is_int( $expected_revision ) || $expected_revision < 1 ||
			! is_string( $expected_incarnation ) || 1 !== preg_match( '/^[a-f0-9]{32}$/', $expected_incarnation ) ) ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'missing_edit_identity' ),
			);
		}
		if ( isset( self::built_in_profiles()[ $normalized['id'] ] ) ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'built_in_collision' ),
			);
		}
		$before = self::raw_state();
		if ( empty( $before['available'] ) || ( $before['exists'] && ! self::valid_v2( $before['value'] ) ) ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'migration_required_or_invalid_storage' ),
			);
		}
		$stored = $before['exists'] ? $before['value'] : array(
			'schema_version' => self::SCHEMA_VERSION,
			'profiles'       => array(),
		);

		$found = false;
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
			if ( $saved['revision'] !== $expected_revision ||
				! hash_equals( $saved['incarnation_id'], $expected_incarnation ) ) {
				return array(
					'success' => false,
					'profile' => null,
					'errors'  => array( 'edit_conflict' ),
				);
			}
			if ( self::contract_definition( $saved ) !== self::contract_definition( $normalized ) ) {
				return array(
					'success' => false,
					'profile' => null,
					'errors'  => array( 'immutable_contract' ),
				);
			}
			$normalized['incarnation_id'] = $saved['incarnation_id'];
			$normalized['revision']       = $saved['revision'] + 1;

			$stored['profiles'][ $index ] = $normalized;

			$found = true;
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
			$normalized['incarnation_id'] = bin2hex( random_bytes( 16 ) );
			$normalized['revision']       = 1;
			$stored['profiles'][]         = $normalized;
		}
		if ( ! self::persist_option( $stored, $before ) ) {
			return array(
				'success' => false,
				'profile' => null,
				'errors'  => array( 'write_conflict_or_failure' ),
			);
		}
		return array(
			'success' => true,
			'profile' => $normalized,
			'errors'  => array(),
		);
	}

	/**
	 * Delete only the exact confirmed incarnation AND revision/content, at the
	 * atomic compare-and-swap boundary. An ID-only delete is not permitted.
	 *
	 * @param string $profile_id Stable public profile ID.
	 * @param string $expected_fingerprint Confirmed snapshot-bound nonce identity.
	 * @return bool
	 */
	public static function delete_custom_profile( $profile_id, $expected_fingerprint = '' ) {
		if ( isset( self::built_in_profiles()[ $profile_id ] ) ||
			! is_string( $expected_fingerprint ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_fingerprint ) ) {
			return false;
		}
		$before = self::raw_state();
		if ( empty( $before['available'] ) || ! $before['exists'] || ! self::valid_v2( $before['value'] ) ) {
			return false;
		}
		$next = $before['value'];
		foreach ( $next['profiles'] as $i => $profile ) {
			if ( $profile['id'] !== $profile_id ) {
				continue;
			}
			if ( ! hash_equals( $expected_fingerprint, self::snapshot_fingerprint( $profile ) ) ) {
				return false;
			}
			array_splice( $next['profiles'], $i, 1 );
			return self::persist_option( $next, $before );
		}
		return false;
	}

	/** Strict mutation gate: unknown fields/schema/duplicate IDs never disappear on write. */
	private static function valid_v2( $value ) {
		if ( ! is_array( $value ) || array_keys( $value ) !== array( 'schema_version', 'profiles' ) ||
			self::SCHEMA_VERSION !== $value['schema_version'] || ! is_array( $value['profiles'] ) ) {
			return false;
		}
		$seen = array();
		foreach ( $value['profiles'] as $profile ) {
			$check = self::validate_profile( $profile );
			if ( ! $check['valid'] || '' === self::snapshot_fingerprint( $profile ) ||
				isset( $seen[ $profile['id'] ] ) || isset( self::built_in_profiles()[ $profile['id'] ] ) ||
				array_merge(
					$check['profile'],
					array(
						'incarnation_id' => $profile['incarnation_id'],
						'revision'       => $profile['revision'],
					)
				) !== $profile ) {
				return false;
			}
			$seen[ $profile['id'] ] = true;
		}
		return true;
	}

	/**
	 * Return supported mapped target types.
	 *
	 * @return array<int,string>
	 */
	public static function supported_target_types() {
		return array( 'text', 'hidden' );
	}

	/**
	 * Return the immutable executable portion of a profile.
	 *
	 * @param array<string,mixed> $profile Profile.
	 * @return array<string,mixed>
	 */
	private static function contract_definition( $profile ) {
		$outputs = array();
		foreach ( $profile['outputs'] as $output ) {
			$outputs[] = array(
				'key'      => $output['key'],
				'required' => (bool) $output['required'],
			);
		}

		return array(
			'parser'  => array(
				'type'             => $profile['parser']['type'],
				'separator'        => $profile['parser']['separator'],
				'trim'             => (bool) $profile['parser']['trim'],
				'normalize_digits' => (bool) $profile['parser']['normalize_digits'],
			),
			'outputs' => $outputs,
		);
	}

	/**
	 * DB compare-and-swap is the linearization point for all Registry writers.
	 * get_option/update_option are not conditional and cannot provide this guard.
	 * Write filters and actions are honored where applicable, but write
	 * filters must not alter canonical data; external writers are unsupported.
	 * A cache delete never serves as the concurrency authority.
	 *
	 * @param array<string,mixed> $next Validated next canonical envelope.
	 * @param array<string,mixed> $before Exact DB version read before mutation.
	 * @return bool
	 */
	private static function persist_option( $next, $before ) {
		global $wpdb;
		if ( empty( $before['available'] ) || ! self::valid_v2( $next ) || ! isset( $wpdb ) ) {
			return false;
		}
		// Mirror WordPress Options API's relevant write hooks, but refuse any
		// filter that would change the canonical payload or bypass the CAS guard.
		if ( sanitize_option( self::OPTION, $next ) !== $next ) {
			return false;
		}
		if ( $before['exists'] ) {
			$filtered = apply_filters( 'pre_update_option_' . self::OPTION, $next, $before['value'], self::OPTION );
			$filtered = apply_filters( 'pre_update_option', $filtered, self::OPTION, $before['value'] );
			if ( $filtered !== $next ) {
				return false;
			}
			do_action( 'update_option', self::OPTION, $before['value'], $next );
		} else {
			do_action( 'add_option', self::OPTION, $next );
		}
		$serialized = maybe_serialize( $next );
		if ( $before['exists'] ) {
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
					$serialized,
					self::OPTION,
					$before['raw']
				)
			);
		} else {
			$written = $wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => self::OPTION,
					'option_value' => $serialized,
					'autoload'     => 'off',
				),
				array( '%s', '%s', '%s' )
			);
		}
		if ( 1 !== $written ) {
			return false;
		}
		// Registry readers use fresh DB snapshots, including with a persistent cache.
		// Invalidate all three WP option cache paths for ordinary downstream readers.
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( $before['exists'] ) {
			do_action( 'update_option_' . self::OPTION, $before['value'], $next, self::OPTION );
			do_action( 'updated_option', self::OPTION, $before['value'], $next );
		} else {
			do_action( 'add_option_' . self::OPTION, self::OPTION, $next );
			do_action( 'added_option', self::OPTION, $next );
		}
		return true;
	}
}
