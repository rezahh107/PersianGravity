<?php
/** Deterministic validation for bounded reviewed translation-content admission. */

/**
 * Hash sorted rows using the repository newline-joined contract.
 *
 * @param array $lines Rows to hash.
 * @return string
 */
function pgr_content_hash_lines( array $lines ) {
	sort( $lines, SORT_STRING );
	return hash( 'sha256', implode( "\n", $lines ) . ( empty( $lines ) ? '' : "\n" ) );
}

/**
 * Canonical gettext identity used by source admission.
 *
 * @param object $entry Gettext translation entry.
 * @return string
 */
function pgr_content_identity( $entry ) {
	return (string) ( $entry->getContext() ?? '' ) . "\x1f" . $entry->getOriginal() . "\x1f" . (string) ( $entry->getPlural() ?? '' );
}

/**
 * Whether an evidence path belongs to the explicit surface rules.
 *
 * @param string $path  Source path.
 * @param array  $rules Explicit surface path rules.
 * @return bool
 */
function pgr_content_surface_path_matches( $path, array $rules ) {
	foreach ( $rules as $rule ) {
		if ( str_ends_with( $rule, '/' ) ) {
			if ( str_starts_with( $path, $rule ) ) {
				return true;
			}
			continue;
		}
		if ( $path === $rule ) {
			return true;
		}
	}
	return false;
}

/**
 * Extract functional placeholders/markup/literals which translations must retain.
 *
 * @param string $value Source or translation text.
 * @return array
 */
function pgr_content_tokens( $value ) {
	$patterns = array(
		'/%(?:\d+\$)?[-+0 #\']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX]/',
		'/\$\{[^{}\r\n]+\}/',
		'/\{[A-Za-z0-9_.:-]+\}/',
		'/<\/?[A-Za-z][^>]*>/',
		'~https?://[^\s<>"\']+~',
		'/&(?:#\d+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/',
	);
	$tokens = array();
	foreach ( $patterns as $pattern ) {
		if ( preg_match_all( $pattern, $value, $matches ) ) {
			$tokens = array_merge( $tokens, $matches[0] );
		}
	}
	sort( $tokens, SORT_STRING );
	return $tokens;
}

/**
 * Validate the one approved partial production-content admission.
 *
 * Licensed vendor bytes are not committed. The exact reviewed baseline, package,
 * POT, selected keyset, selected source-path index and translation content are all
 * pinned by independent hashes. The compact source-evidence index lets CI prove
 * every admitted identity belongs to the registered Inbox path rules.
 *
 * @param string $root             Repository root.
 * @param array  $source_admission Validated source-admission records.
 * @param array  $products         Runtime product manifests.
 * @return array
 */
function pgr_validate_content_admission( $root, array $source_admission, array $products ) {
	$root     = rtrim( $root, '/\\' );
	$manifest = pgr_admission_json( $root . '/tools/i18n/admission/content.json' );
	$surfaces = pgr_admission_json( $root . '/tools/i18n/admission/surfaces.json' );

	$expected = array(
		'product'                             => 'gravityflow',
		'domain'                              => 'gravityflow',
		'locale'                              => 'fa_IR',
		'target_version'                      => '3.1.0',
		'content_state'                       => 'CONTENT_ADMITTED_PARTIAL',
		'reviewed_source_po_sha256'           => 'c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9',
		'reviewed_source_message_count'       => 1098,
		'source_package_sha256'               => 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404',
		'vendor_pot_sha256'                   => '09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961',
		'surface_id'                          => 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox',
		'admitted_message_count'              => 255,
		'admitted_keyset_sha256'              => '446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a',
		'admitted_surface_path_index_sha256'  => '6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09',
		'admitted_translation_content_sha256' => '6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f',
		'surface_evidence_index_path'          => 'tools/i18n/admission/gravityflow-inbox-index.json',
		'surface_evidence_index_sha256'        => '7eb6a9203d194995cd1de8e7cbb91d83b935dd591247506c386f0145bb731e0f',
		'provider_source_path'                 => 'languages/providers/gravityflow/source/fa_IR.po',
		'provider_source_sha256'               => 'ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570',
		'native_js_handles_activated'          => 0,
		'js_translation_json_generated'        => 0,
	);

	if ( 1 !== ( $manifest['content_admission_revision'] ?? null ) ||
		'SHA256_UTF8_MSGCTXT_US_MSGID_US_MSGID_PLURAL' !== ( $manifest['identity_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256' !== ( $manifest['keyset_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_MATCHED_SOURCE_PATH' !== ( $manifest['surface_path_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_NUL_JOINED_MSGSTR' !== ( $manifest['translation_hash_method'] ?? null ) ) {
		throw new RuntimeException( 'Invalid content-admission revision/hash contract' );
	}

	$records = $manifest['admissions'] ?? null;
	if ( ! is_array( $records ) || 1 !== count( $records ) || ! is_array( $records[0] ) ) {
		throw new RuntimeException( 'Content admission must contain exactly the approved Gravity Flow record' );
	}
	$record = $records[0];
	foreach ( $expected as $field => $value ) {
		if ( ( $record[ $field ] ?? null ) !== $value ) {
			throw new RuntimeException( 'Content admission drift: gravityflow:' . $field );
		}
	}

	$source = $source_admission['gravityflow'] ?? null;
	if ( ! is_array( $source ) ||
		$source['target_version'] !== $record['target_version'] ||
		$source['package_sha256'] !== $record['source_package_sha256'] ||
		$source['vendor_pot_sha256'] !== $record['vendor_pot_sha256'] ) {
		throw new RuntimeException( 'Content admission is not bound to admitted Gravity Flow source evidence' );
	}
	if ( ! isset( $products['gravityflow'] ) ||
		'gravityflow' !== $products['gravityflow']['product'] ||
		$products['gravityflow']['target_version'] !== $record['target_version'] ||
		array() !== $products['gravityflow']['scripts'] ) {
		throw new RuntimeException( 'Gravity Flow product manifest drift for partial content admission' );
	}

	$surface = null;
	foreach ( $surfaces['surfaces'] ?? array() as $candidate ) {
		if ( ( $candidate['surface_id'] ?? null ) === $record['surface_id'] ) {
			$surface = $candidate;
			break;
		}
	}
	$rules = array( 'includes/pages/class-inbox.php', 'includes/inbox/' );
	if ( ! is_array( $surface ) ||
		'gravityflow' !== ( $surface['product'] ?? null ) ||
		$rules !== ( $surface['source_path_rules'] ?? null ) ) {
		throw new RuntimeException( 'Inbox surface source-path contract drift' );
	}

	$index_path = $root . '/' . $record['surface_evidence_index_path'];
	if ( ! is_file( $index_path ) || hash_file( 'sha256', $index_path ) !== $record['surface_evidence_index_sha256'] ) {
		throw new RuntimeException( 'Inbox source-evidence index drift' );
	}
	$index = pgr_admission_json( $index_path );
	if ( $record['surface_id'] !== ( $index['surface_id'] ?? null ) || $rules !== ( $index['source_path_rules'] ?? null ) ) {
		throw new RuntimeException( 'Inbox source-evidence index scope drift' );
	}
	$index_paths = $index['paths'] ?? null;
	$index_entries = $index['entries'] ?? null;
	if ( ! is_array( $index_paths ) || ! is_array( $index_entries ) || $record['admitted_message_count'] !== count( $index_entries ) ) {
		throw new RuntimeException( 'Inbox source-evidence index count drift' );
	}
	$sorted_paths = $index_paths;
	sort( $sorted_paths, SORT_STRING );
	if ( $sorted_paths !== $index_paths || count( $index_paths ) !== count( array_unique( $index_paths ) ) ) {
		throw new RuntimeException( 'Non-deterministic Inbox evidence path dictionary' );
	}
	foreach ( $index_paths as $path ) {
		if ( ! pgr_content_surface_path_matches( $path, $rules ) ) {
			throw new RuntimeException( 'Out-of-surface source evidence path: ' . $path );
		}
	}

	$index_ids    = array();
	$surface_rows = array();
	$previous_id  = '';
	foreach ( $index_entries as $item ) {
		if ( ! is_array( $item ) || 2 !== count( $item ) ) {
			throw new RuntimeException( 'Invalid Inbox evidence index entry shape' );
		}
		$id      = $item[0];
		$indexes = $item[1];
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || $id <= $previous_id || ! is_array( $indexes ) || empty( $indexes ) ) {
			throw new RuntimeException( 'Invalid or non-deterministic Inbox evidence index entry' );
		}
		$previous_id = $id;
		$sorted      = $indexes;
		sort( $sorted, SORT_NUMERIC );
		if ( $sorted !== $indexes || count( $indexes ) !== count( array_unique( $indexes ) ) ) {
			throw new RuntimeException( 'Non-deterministic Inbox evidence path indexes' );
		}
		foreach ( $indexes as $path_index ) {
			if ( ! is_int( $path_index ) || ! isset( $index_paths[ $path_index ] ) ) {
				throw new RuntimeException( 'Unknown Inbox evidence path index' );
			}
			$surface_rows[] = $id . "\x1f" . $index_paths[ $path_index ];
		}
		$index_ids[] = $id;
	}
	if ( $record['admitted_keyset_sha256'] !== pgr_content_hash_lines( $index_ids ) ||
		$record['admitted_surface_path_index_sha256'] !== pgr_content_hash_lines( $surface_rows ) ) {
		throw new RuntimeException( 'Inbox source-evidence fingerprints drift' );
	}

	$provider_path = $root . '/' . $record['provider_source_path'];
	if ( ! is_file( $provider_path ) || hash_file( 'sha256', $provider_path ) !== $record['provider_source_sha256'] ) {
		throw new RuntimeException( 'Sparse provider source drift' );
	}
	$provenance = pgr_admission_json( dirname( $provider_path ) . '/provenance.json' );
	$provenance_expected = array(
		'content_admission_state'              => $record['content_state'],
		'reviewed_source_po_sha256'            => $record['reviewed_source_po_sha256'],
		'reviewed_source_message_count'        => $record['reviewed_source_message_count'],
		'admitted_surface_id'                  => $record['surface_id'],
		'admitted_message_count'               => $record['admitted_message_count'],
		'admitted_keyset_sha256'               => $record['admitted_keyset_sha256'],
		'admitted_surface_path_index_sha256'   => $record['admitted_surface_path_index_sha256'],
		'admitted_translation_content_sha256'  => $record['admitted_translation_content_sha256'],
		'surface_evidence_index_sha256'        => $record['surface_evidence_index_sha256'],
		'admitted_provider_po_sha256'          => $record['provider_source_sha256'],
	);
	foreach ( $provenance_expected as $field => $value ) {
		if ( ( $provenance[ $field ] ?? null ) !== $value ) {
			throw new RuntimeException( 'Gravity Flow content provenance drift: ' . $field );
		}
	}

	$catalog = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $provider_path );
	$headers = iterator_to_array( $catalog->getHeaders() );
	if ( 'fa_IR' !== ( $headers['Language'] ?? null ) ||
		'gravityflow' !== ( $headers['X-Domain'] ?? null ) ||
		'nplurals=2; plural=(n > 1);' !== ( $headers['Plural-Forms'] ?? null ) ) {
		throw new RuntimeException( 'Invalid sparse Gravity Flow PO headers' );
	}

	$po_ids           = array();
	$translation_rows = array();
	$seen             = array();
	foreach ( $catalog as $entry ) {
		if ( $entry->isDisabled() ) {
			continue;
		}
		if ( $entry->getFlags()->has( 'fuzzy' ) ) {
			throw new RuntimeException( 'Fuzzy entry in admitted Gravity Flow content' );
		}

		$values = array( $entry->getTranslation() );
		if ( null !== $entry->getPlural() ) {
			$values = array_merge( $values, $entry->getPluralTranslations() );
			if ( 2 !== count( $values ) ) {
				throw new RuntimeException( 'Incomplete plural in admitted Gravity Flow content' );
			}
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				throw new RuntimeException( 'Empty translation in admitted Gravity Flow content' );
			}
		}

		$id = hash( 'sha256', pgr_content_identity( $entry ) );
		if ( isset( $seen[ $id ] ) ) {
			throw new RuntimeException( 'Duplicate admitted Gravity Flow identity' );
		}
		$seen[ $id ] = true;
		$po_ids[]    = $id;

		$source_forms = array( $entry->getOriginal() );
		if ( null !== $entry->getPlural() ) {
			$source_forms[] = $entry->getPlural();
		}
		foreach ( $values as $index_value => $value ) {
			$source_form = $source_forms[ min( $index_value, count( $source_forms ) - 1 ) ];
			if ( pgr_content_tokens( $source_form ) !== pgr_content_tokens( $value ) ) {
				throw new RuntimeException( 'Placeholder/markup/literal drift in admitted entry: ' . $entry->getOriginal() );
			}
		}
		$translation_rows[] = $id . "\x1f" . implode( "\x00", $values );
	}

	sort( $po_ids, SORT_STRING );
	if ( $index_ids !== $po_ids ||
		$record['admitted_message_count'] !== count( $po_ids ) ||
		$record['admitted_keyset_sha256'] !== pgr_content_hash_lines( $po_ids ) ||
		$record['admitted_translation_content_sha256'] !== pgr_content_hash_lines( $translation_rows ) ) {
		throw new RuntimeException( 'Sparse provider content is not the approved Inbox selection' );
	}

	$record['computed'] = array(
		'admitted_message_count'              => count( $po_ids ),
		'admitted_keyset_sha256'              => pgr_content_hash_lines( $po_ids ),
		'admitted_surface_path_index_sha256'  => pgr_content_hash_lines( $surface_rows ),
		'admitted_translation_content_sha256' => pgr_content_hash_lines( $translation_rows ),
	);
	return array( 'gravityflow' => $record );
}
