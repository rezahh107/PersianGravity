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
 * Whether a path uses the canonical repository-relative syntax.
 *
 * Repository path authority is lexical and platform-independent. Forward slashes
 * are the only separators. One trailing slash is allowed for directory rules;
 * dot/traversal segments, absolute paths and Windows-qualified forms are rejected
 * rather than normalized.
 *
 * @param mixed $path Candidate repository-relative path.
 * @return bool
 */
function pgr_content_repository_path_is_valid( $path ) {
	if ( ! is_string( $path ) || '' === $path || str_starts_with( $path, '/' ) || str_contains( $path, '\\' ) || preg_match( '/^[A-Za-z]:/', $path ) ) {
		return false;
	}

	$segments = explode( '/', $path );
	$last     = count( $segments ) - 1;
	foreach ( $segments as $index => $segment ) {
		if ( '' === $segment ) {
			if ( $index === $last && 0 < $index ) {
				continue;
			}
			return false;
		}
		if ( '.' === $segment || '..' === $segment ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether an evidence path belongs to the explicit surface rules.
 *
 * @param string $path  Source path.
 * @param array  $rules Explicit surface path rules.
 * @return bool
 */
function pgr_content_surface_path_matches( $path, array $rules ) {
	if ( ! pgr_content_repository_path_is_valid( $path ) ) {
		return false;
	}
	foreach ( $rules as $rule ) {
		if ( ! pgr_content_repository_path_is_valid( $rule ) ) {
			return false;
		}
	}
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
 * Natural-language percent signs, sentence punctuation around URLs, and named
 * HTML entities such as &amp; are not functional gettext tokens. Numeric entities
 * remain protected, while complete markup tags protect any entities inside them.
 *
 * @param string $value Source or translation text.
 * @return array
 */
function pgr_content_tokens( $value ) {
	$patterns = array(
		'printf' => '/(?<!\d)%(?:\d+\$)?[-+0#\']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX]/',
		'printf_space' => '/(?<!\d)%(?:\d+\$)?[-+0#\']* +[-+0#\']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX](?![A-Za-z])/',
		'literal' => '/\$\{[^{}\r\n]+\}/',
		'brace'   => '/\{[^{}\r\n]+\}/',
		'markup'  => '/<\/?[A-Za-z][^>]*>/',
		'url'     => '~https?://[^\s<>"\']+~u',
		'entity'  => '/&#(?:\d+|x[0-9A-Fa-f]+);/',
	);
	$tokens = array();
	foreach ( $patterns as $kind => $pattern ) {
		if ( ! preg_match_all( $pattern, $value, $matches ) ) {
			continue;
		}
		foreach ( $matches[0] as $token ) {
			if ( 'url' === $kind ) {
				$token = preg_replace( '/[.,;:!?،؛؟]+$/u', '', $token );
				foreach ( array( array( '(', ')' ), array( '[', ']' ), array( '{', '}' ) ) as $pair ) {
					while ( str_ends_with( $token, $pair[1] ) && substr_count( $token, $pair[0] ) < substr_count( $token, $pair[1] ) ) {
						$token = substr( $token, 0, -1 );
					}
				}
			}
			if ( '' !== $token ) {
				$tokens[] = $token;
			}
		}
	}
	sort( $tokens, SORT_STRING );
	return $tokens;
}

/**
 * Extract protected product/technical literals which must remain byte-identical.
 *
 * @param string $value Source or translation text.
 * @return array
 */
function pgr_content_protected_literals( $value ) {
	$pattern = '/Gravity Forms|Gravity Flow|GravityView|WordPress|\bAPI\b|\bURLs?\b|\bPHP\b|\bCSS\b|\bHTML\b|\bJSON\b|\bREST\b|\bAJAX\b|JavaScript|reCAPTCHA|OAuth1|SMTP|Mailgun|GravityFlow\.io|display_all|allow_anonymous|page_id|\[gravityflow\]|Client Key|Client Secret|Consumer Key|Form ID|Lead ID/';
	if ( ! preg_match_all( $pattern, $value, $matches ) ) {
		return array();
	}
	$result = array_map(
		static function ( $literal ) {
			return 'URLs' === $literal ? 'URL' : $literal;
		},
		$matches[0]
	);
	sort( $result, SORT_STRING );
	return $result;
}

/**
 * Hash the canonical source keyset using the locked source-admission contract.
 *
 * @param array $ids Identity SHA-256 values.
 * @return string
 */
function pgr_content_source_keyset_hash( array $ids ) {
	sort( $ids, SORT_STRING );
	return hash( 'sha256', implode( "\n", $ids ) );
}

/**
 * Hash the accepted translation values for one identity.
 *
 * @param array $values Translation forms.
 * @return string
 */
function pgr_content_review_translation_hash( array $values ) {
	return hash( 'sha256', implode( "\x00", $values ) );
}

/**
 * Compute source-derived review risk flags.
 *
 * @param string      $msgid        Singular source string.
 * @param string|null $msgid_plural Optional plural source string.
 * @return array
 */
function pgr_content_review_risk_flags( $msgid, $msgid_plural ) {
	$value = $msgid . "\n" . (string) $msgid_plural;
	$flags = array();
	if ( null !== $msgid_plural ) {
		$flags[] = 'PLURAL';
	}
	if ( preg_match( '/(?<!\d)%(?:\d+\$)?[-+0#\']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX]/', $value ) ) {
		$flags[] = 'PRINTF';
	}
	if ( preg_match( '/<\/?[A-Za-z][^>]*>/', $value ) ) {
		$flags[] = 'MARKUP';
	}
	if ( preg_match( '~https?://[^\s<>"\']+~u', $value ) ) {
		$flags[] = 'URL';
	}
	if ( preg_match( '/\{[^{}\r\n]+\}/', $value ) ) {
		$flags[] = 'TEMPLATE_TOKEN';
	}
	if ( preg_match( '/&#(?:\d+|x[0-9A-Fa-f]+);/', $value ) ) {
		$flags[] = 'NUMERIC_ENTITY';
	}
	if ( ! empty( pgr_content_protected_literals( $value ) ) ) {
		$flags[] = 'PROTECTED_LITERAL';
	}
	if ( 16 >= strlen( trim( $msgid ) ) ) {
		$flags[] = 'SHORT_AMBIGUOUS';
	}
	return $flags;
}

/**
 * Validate a repository-relative path and return its absolute form.
 *
 * @param string $root Repository root.
 * @param mixed  $path Candidate relative path.
 * @param string $label Evidence label.
 * @return string
 */
function pgr_content_repository_path( $root, $path, $label ) {
	if ( ! pgr_content_repository_path_is_valid( $path ) ) {
		throw new RuntimeException( 'Invalid repository-relative path: ' . $label );
	}
	return rtrim( $root, '/\\' ) . '/' . $path;
}

/**
 * Require a lowercase SHA-256 value.
 *
 * @param mixed  $value Hash candidate.
 * @param string $label Evidence label.
 * @return void
 */
function pgr_content_require_hash( $value, $label ) {
	if ( 'vendor_pot_sha256' === $label && null === $value ) {
		return;
	}
	if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}$/D', $value ) ) {
		throw new RuntimeException( 'Invalid content-admission hash: ' . $label );
	}
}

/**
 * Return the exact runtime product manifest for one admission record.
 *
 * @param array $record   Admission record.
 * @param array $products Runtime product manifests keyed by domain.
 * @return array
 */
function pgr_content_product_manifest( array $record, array $products ) {
	$domain  = $record['domain'] ?? null;
	$product = is_string( $domain ) && isset( $products[ $domain ] ) ? $products[ $domain ] : null;
	if ( ! is_array( $product ) ||
		( $product['product'] ?? null ) !== ( $record['product'] ?? null ) ||
		( $product['target_version'] ?? null ) !== ( $record['target_version'] ?? null ) ||
		! isset( $product['scripts'] ) || ! is_array( $product['scripts'] ) ) {
		throw new RuntimeException( 'Content admission product/domain/version manifest mismatch' );
	}
	return $product;
}

/**
 * Parse and validate one sparse provider PO.
 *
 * @param string $path                       Absolute PO path.
 * @param string $domain                     Text domain.
 * @param string $locale                     Locale.
 * @param bool   $enforce_protected_literals Whether product/technical literals must remain byte-identical.
 * @return array
 */
function pgr_content_load_sparse_po( $path, $domain, $locale, $enforce_protected_literals = false ) {
	$catalog = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $path );
	$headers = iterator_to_array( $catalog->getHeaders() );
	if ( $locale !== ( $headers['Language'] ?? null ) ||
		$domain !== ( $headers['X-Domain'] ?? null ) ||
		'nplurals=2; plural=(n > 1);' !== ( $headers['Plural-Forms'] ?? null ) ) {
		throw new RuntimeException( 'Invalid sparse provider PO headers: ' . $path );
	}

	$ids              = array();
	$translation_rows = array();
	$seen             = array();
	foreach ( $catalog as $entry ) {
		if ( $entry->isDisabled() ) {
			continue;
		}
		if ( $entry->getFlags()->has( 'fuzzy' ) ) {
			throw new RuntimeException( 'Fuzzy entry in admitted provider content' );
		}

		$values = array( $entry->getTranslation() );
		if ( null !== $entry->getPlural() ) {
			$values = array_merge( $values, $entry->getPluralTranslations() );
			if ( 2 !== count( $values ) ) {
				throw new RuntimeException( 'Incomplete plural in admitted provider content' );
			}
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				throw new RuntimeException( 'Empty translation in admitted provider content' );
			}
		}

		$id = hash( 'sha256', pgr_content_identity( $entry ) );
		if ( isset( $seen[ $id ] ) ) {
			throw new RuntimeException( 'Duplicate admitted provider identity' );
		}
		$seen[ $id ] = true;
		$ids[]       = $id;

		$source_forms = array( $entry->getOriginal() );
		if ( null !== $entry->getPlural() ) {
			$source_forms[] = $entry->getPlural();
		}
		foreach ( $values as $index => $value ) {
			$source_form = $source_forms[ min( $index, count( $source_forms ) - 1 ) ];
			if ( pgr_content_tokens( $source_form ) !== pgr_content_tokens( $value ) ) {
				throw new RuntimeException( 'Placeholder/markup/literal drift in admitted entry: ' . $entry->getOriginal() );
			}
			if ( $enforce_protected_literals && pgr_content_protected_literals( $source_form ) !== pgr_content_protected_literals( $value ) ) {
				throw new RuntimeException( 'Protected literal drift in admitted entry: ' . $entry->getOriginal() );
			}
		}
		$translation_rows[ $id ] = $id . "\x1f" . implode( "\x00", $values );
	}

	sort( $ids, SORT_STRING );
	ksort( $translation_rows, SORT_STRING );
	return array(
		'ids'              => $ids,
		'translation_rows' => $translation_rows,
	);
}

/**
 * Create the deterministic record identity tuple.
 *
 * @param array $record Admission record.
 * @return string
 */
function pgr_content_record_identity( array $record ) {
	$scope = $record['surface_id'] ?? ( $record['authority_scope'] ?? '' );
	return implode(
		"\x1f",
		array(
			$record['product'],
			$record['domain'],
			$record['locale'],
			$record['target_version'],
			$scope,
		)
	);
}

/**
 * Return the per-record provenance projection.
 *
 * @param array $record Validated admission record.
 * @return array
 */
function pgr_content_provenance_record( array $record ) {
	$fields = array(
		'product',
		'domain',
		'locale',
		'target_version',
		'content_state',
		'reviewed_source_po_sha256',
		'reviewed_source_message_count',
		'surface_id',
		'authority_scope',
		'preexisting_accepted_message_count',
		'preexisting_accepted_keyset_sha256',
		'preexisting_accepted_translation_content_sha256',
		'canonical_message_count',
		'canonical_keyset_sha256',
		'admitted_message_count',
		'admitted_keyset_sha256',
		'admitted_surface_path_index_sha256',
		'admitted_translation_content_sha256',
		'surface_evidence_index_path',
		'surface_evidence_index_sha256',
		'remainder_evidence_index_path',
		'remainder_evidence_index_sha256',
		'review_index_path',
		'review_index_sha256',
		'provider_source_path',
		'provider_source_sha256',
	);
	$result = array();
	foreach ( $fields as $field ) {
		if ( array_key_exists( $field, $record ) ) {
			$result[ $field ] = $record[ $field ];
		}
	}
	return $result;
}

/**
 * Validate one product-remainder content admission and its review evidence.
 *
 * @param string $root             Repository root.
 * @param array  $record           Remainder admission record.
 * @param array  $source_admission Validated source admission.
 * @param array  $products         Runtime product manifests.
 * @return array
 */
function pgr_validate_content_remainder_record( $root, array $record, array $source_admission, array $products ) {
	$required_strings = array( 'product', 'domain', 'locale', 'target_version', 'content_state', 'authority_scope', 'remainder_evidence_index_path', 'review_index_path', 'provider_source_path' );
	foreach ( $required_strings as $field ) {
		if ( ! isset( $record[ $field ] ) || ! is_string( $record[ $field ] ) || '' === $record[ $field ] ) {
			throw new RuntimeException( 'Missing remainder content-admission field: ' . $field );
		}
	}
	if ( 'fa_IR' !== $record['locale'] || 'CONTENT_ADMITTED_REMAINDER' !== $record['content_state'] || 'PRODUCT_REMAINDER' !== $record['authority_scope'] ) {
		throw new RuntimeException( 'Unsupported remainder content-admission locale/state/scope' );
	}
	foreach (
		array(
			'reviewed_source_po_sha256',
			'source_package_sha256',
			'vendor_pot_sha256',
			'preexisting_accepted_keyset_sha256',
			'preexisting_accepted_translation_content_sha256',
			'canonical_keyset_sha256',
			'admitted_keyset_sha256',
			'admitted_translation_content_sha256',
			'remainder_evidence_index_sha256',
			'review_index_sha256',
			'provider_source_sha256',
		) as $field
	) {
		pgr_content_require_hash( $record[ $field ] ?? null, $field );
	}
	foreach ( array( 'reviewed_source_message_count', 'preexisting_accepted_message_count', 'canonical_message_count', 'admitted_message_count' ) as $field ) {
		if ( ! is_int( $record[ $field ] ?? null ) || 0 > $record[ $field ] ) {
			throw new RuntimeException( 'Invalid remainder content-admission count: ' . $field );
		}
	}
	if ( 0 !== ( $record['native_js_handles_activated'] ?? null ) || 0 !== ( $record['js_translation_json_generated'] ?? null ) ) {
		throw new RuntimeException( 'Remainder admission cannot activate JavaScript handles' );
	}

	$product_manifest = pgr_content_product_manifest( $record, $products );
	if ( array() !== $product_manifest['scripts'] ) {
		throw new RuntimeException( 'Remainder admission cannot activate unapproved JavaScript handles' );
	}
	$source = $source_admission[ $record['product'] ] ?? null;
	if ( ! is_array( $source ) ||
		( $source['target_version'] ?? null ) !== $record['target_version'] ||
		( $source['package_sha256'] ?? null ) !== $record['source_package_sha256'] ||
		( $source['vendor_pot_sha256'] ?? null ) !== $record['vendor_pot_sha256'] ||
		( $source['canonical_message_count'] ?? null ) !== $record['reviewed_source_message_count'] ||
		( $source['canonical_message_count'] ?? null ) !== $record['canonical_message_count'] ||
		( $source['canonical_keyset_sha256'] ?? null ) !== $record['canonical_keyset_sha256'] ) {
		throw new RuntimeException( 'Remainder content admission is not bound to validated source admission' );
	}
	if ( $record['preexisting_accepted_message_count'] + $record['admitted_message_count'] !== $record['canonical_message_count'] ) {
		throw new RuntimeException( 'Remainder content-admission count does not complete the source census' );
	}

	$evidence_path = pgr_content_repository_path( $root, $record['remainder_evidence_index_path'], 'remainder evidence index' );
	if ( ! is_file( $evidence_path ) || hash_file( 'sha256', $evidence_path ) !== $record['remainder_evidence_index_sha256'] ) {
		throw new RuntimeException( 'Remainder evidence index drift' );
	}
	$evidence = pgr_admission_json( $evidence_path );
	$evidence_contract = array(
		'schema_version'                                  => 1,
		'scope'                                           => 'PRODUCT_REMAINDER',
		'product'                                         => $record['product'],
		'domain'                                          => $record['domain'],
		'locale'                                          => $record['locale'],
		'target_version'                                  => $record['target_version'],
		'source_package_sha256'                           => $record['source_package_sha256'],
		'vendor_pot_sha256'                               => $record['vendor_pot_sha256'],
		'canonical_message_count'                         => $record['canonical_message_count'],
		'canonical_keyset_sha256'                         => $record['canonical_keyset_sha256'],
		'canonical_keyset_hash_method'                    => 'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_NO_TRAILING_NEWLINE',
		'preexisting_accepted_message_count'              => $record['preexisting_accepted_message_count'],
		'preexisting_accepted_keyset_sha256'              => $record['preexisting_accepted_keyset_sha256'],
		'preexisting_accepted_translation_content_sha256' => $record['preexisting_accepted_translation_content_sha256'],
		'remainder_message_count'                         => $record['admitted_message_count'],
		'remainder_keyset_sha256'                         => $record['admitted_keyset_sha256'],
	);
	foreach ( $evidence_contract as $field => $expected ) {
		if ( ( $evidence[ $field ] ?? null ) !== $expected ) {
			throw new RuntimeException( 'Remainder evidence contract drift: ' . $field );
		}
	}
	$canonical_ids = $evidence['canonical_identities'] ?? null;
	if ( ! is_array( $canonical_ids ) || $canonical_ids !== array_values( $canonical_ids ) || count( $canonical_ids ) !== $record['canonical_message_count'] ) {
		throw new RuntimeException( 'Invalid canonical identity census in remainder evidence' );
	}
	$canonical_seen = array();
	foreach ( $canonical_ids as $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || isset( $canonical_seen[ $id ] ) ) {
			throw new RuntimeException( 'Invalid/duplicate canonical identity in remainder evidence' );
		}
		$canonical_seen[ $id ] = true;
	}
	if ( $record['canonical_keyset_sha256'] !== pgr_content_source_keyset_hash( $canonical_ids ) ) {
		throw new RuntimeException( 'Canonical source keyset drift in remainder evidence' );
	}

	$entries = $evidence['entries'] ?? null;
	if ( ! is_array( $entries ) || $entries !== array_values( $entries ) || count( $entries ) !== $record['admitted_message_count'] ) {
		throw new RuntimeException( 'Invalid remainder evidence entries' );
	}
	$remainder_ids = array();
	$source_by_id  = array();
	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) || ! array_key_exists( 'identity', $entry ) || ! array_key_exists( 'msgid', $entry ) || ! array_key_exists( 'references', $entry ) ) {
			throw new RuntimeException( 'Invalid remainder evidence entry shape' );
		}
		$msgctxt     = $entry['msgctxt'] ?? null;
		$msgid       = $entry['msgid'];
		$msgid_plural = $entry['msgid_plural'] ?? null;
		if ( ( null !== $msgctxt && ! is_string( $msgctxt ) ) || ! is_string( $msgid ) || '' === $msgid || ( null !== $msgid_plural && ! is_string( $msgid_plural ) ) ) {
			throw new RuntimeException( 'Invalid remainder evidence source text' );
		}
		$id = hash( 'sha256', (string) $msgctxt . "\x1f" . $msgid . "\x1f" . (string) $msgid_plural );
		if ( $id !== $entry['identity'] || isset( $source_by_id[ $id ] ) || ! isset( $canonical_seen[ $id ] ) ) {
			throw new RuntimeException( 'Invalid/duplicate/out-of-census remainder identity' );
		}
		$references = $entry['references'];
		if ( ! is_array( $references ) || $references !== array_values( $references ) ) {
			throw new RuntimeException( 'Invalid remainder source references' );
		}
		pgr_admission_unique_strings( $references, 'remainder source references' );
		$source_by_id[ $id ] = array( $msgid, $msgid_plural );
		$remainder_ids[]     = $id;
	}
	sort( $remainder_ids, SORT_STRING );
	if ( $record['admitted_keyset_sha256'] !== pgr_content_hash_lines( $remainder_ids ) ) {
		throw new RuntimeException( 'Remainder identity fingerprint drift' );
	}

	$provider_path = pgr_content_repository_path( $root, $record['provider_source_path'], 'remainder provider source' );
	if ( ! is_file( $provider_path ) || hash_file( 'sha256', $provider_path ) !== $record['provider_source_sha256'] ) {
		throw new RuntimeException( 'Remainder provider source drift' );
	}
	$provider = pgr_content_load_sparse_po( $provider_path, $record['domain'], $record['locale'], true );
	if ( $provider['ids'] !== $remainder_ids || $record['admitted_translation_content_sha256'] !== pgr_content_hash_lines( array_values( $provider['translation_rows'] ) ) ) {
		throw new RuntimeException( 'Remainder provider source is not the reviewed remainder identity set' );
	}

	$review_path = pgr_content_repository_path( $root, $record['review_index_path'], 'remainder review index' );
	if ( ! is_file( $review_path ) || hash_file( 'sha256', $review_path ) !== $record['review_index_sha256'] ) {
		throw new RuntimeException( 'Remainder review index drift' );
	}
	$review = pgr_admission_json( $review_path );
	if ( 1 !== ( $review['schema_version'] ?? null ) || 'ACCEPTED_TRANSLATION_REVIEW' !== ( $review['review_scope'] ?? null ) ||
		$record['product'] !== ( $review['product'] ?? null ) || $record['domain'] !== ( $review['domain'] ?? null ) ||
		$record['locale'] !== ( $review['locale'] ?? null ) || $record['target_version'] !== ( $review['target_version'] ?? null ) ||
		$record['admitted_message_count'] !== ( $review['accepted_count'] ?? null ) || 0 !== ( $review['unreviewed_count'] ?? null ) ||
		0 !== ( $review['rejected_count'] ?? null ) || 2 > ( $review['semantic_review_passes'] ?? 0 ) ||
		'SHA256_UTF8_NUL_JOINED_MSGSTR' !== ( $review['translation_hash_method'] ?? null ) ) {
		throw new RuntimeException( 'Invalid remainder review summary' );
	}
	$review_entries = $review['entries'] ?? null;
	if ( ! is_array( $review_entries ) || $review_entries !== array_values( $review_entries ) || count( $review_entries ) !== $record['admitted_message_count'] ) {
		throw new RuntimeException( 'Invalid remainder review entries' );
	}
	$review_seen = array();
	$corrections = 0;
	foreach ( $review_entries as $item ) {
		$id = is_array( $item ) ? ( $item['identity'] ?? null ) : null;
		if ( ! is_string( $id ) || ! isset( $provider['translation_rows'][ $id ] ) || isset( $review_seen[ $id ] ) || 'ACCEPTED' !== ( $item['status'] ?? null ) ) {
			throw new RuntimeException( 'Invalid/duplicate/unaccepted remainder review identity' );
		}
		$review_seen[ $id ] = true;
		$translation_values = substr( $provider['translation_rows'][ $id ], 65 );
		if ( hash( 'sha256', $translation_values ) !== ( $item['translation_sha256'] ?? null ) ) {
			throw new RuntimeException( 'Remainder reviewed translation hash drift' );
		}
		$expected_flags = pgr_content_review_risk_flags( $source_by_id[ $id ][0], $source_by_id[ $id ][1] );
		if ( $expected_flags !== ( $item['risk_flags'] ?? null ) ) {
			throw new RuntimeException( 'Remainder review risk flags drift' );
		}
		$corrected = $item['second_pass_corrected'] ?? null;
		if ( ! is_bool( $corrected ) ) {
			throw new RuntimeException( 'Invalid second-pass review marker' );
		}
		if ( $corrected ) {
			++$corrections;
		}
	}
	if ( $corrections !== ( $review['second_pass_corrections'] ?? null ) ) {
		throw new RuntimeException( 'Second-pass correction count drift' );
	}

	$record['canonical_ids']   = $canonical_ids;
	$record['translation_rows'] = $provider['translation_rows'];
	return $record;
}

/**
 * Validate one content-admission record independently.
 *
 * @param string $root             Repository root.
 * @param array  $record           Admission record.
 * @param array  $source_admission Validated source admission.
 * @param array  $products         Runtime product manifests.
 * @param array  $surface_by_id    Surface registry keyed by surface_id.
 * @return array
 */
function pgr_validate_content_admission_record( $root, array $record, array $source_admission, array $products, array $surface_by_id ) {
	if ( 'CONTENT_ADMITTED_REMAINDER' === ( $record['content_state'] ?? null ) ) {
		return pgr_validate_content_remainder_record( $root, $record, $source_admission, $products );
	}

	$required_strings = array( 'product', 'domain', 'locale', 'target_version', 'content_state', 'surface_id' );
	foreach ( $required_strings as $field ) {
		if ( ! isset( $record[ $field ] ) || ! is_string( $record[ $field ] ) || '' === $record[ $field ] ) {
			throw new RuntimeException( 'Missing content-admission field: ' . $field );
		}
	}
	if ( 'fa_IR' !== $record['locale'] || 'CONTENT_ADMITTED_PARTIAL' !== $record['content_state'] ) {
		throw new RuntimeException( 'Unsupported content-admission locale/state' );
	}
	foreach (
		array(
			'reviewed_source_po_sha256',
			'source_package_sha256',
			'vendor_pot_sha256',
			'admitted_keyset_sha256',
			'admitted_surface_path_index_sha256',
			'admitted_translation_content_sha256',
			'surface_evidence_index_sha256',
			'provider_source_sha256',
		) as $field
	) {
		pgr_content_require_hash( $record[ $field ] ?? null, $field );
	}
	if ( ! is_int( $record['reviewed_source_message_count'] ?? null ) || 0 >= $record['reviewed_source_message_count'] ||
		! is_int( $record['admitted_message_count'] ?? null ) || 0 > $record['admitted_message_count'] ||
		0 !== ( $record['native_js_handles_activated'] ?? null ) || 0 !== ( $record['js_translation_json_generated'] ?? null ) ) {
		throw new RuntimeException( 'Invalid content-admission count/JavaScript contract' );
	}

	$product_manifest = pgr_content_product_manifest( $record, $products );
	if ( array() !== $product_manifest['scripts'] ) {
		throw new RuntimeException( 'Content admission cannot activate unapproved JavaScript handles' );
	}
	$source = $source_admission[ $record['product'] ] ?? null;
	if ( ! is_array( $source ) ||
		( $source['target_version'] ?? null ) !== $record['target_version'] ||
		( $source['package_sha256'] ?? null ) !== $record['source_package_sha256'] ||
		( $source['vendor_pot_sha256'] ?? null ) !== $record['vendor_pot_sha256'] ||
		( $source['canonical_message_count'] ?? null ) !== $record['reviewed_source_message_count'] ) {
		throw new RuntimeException( 'Content admission is not bound to validated source admission' );
	}

	$surface = $surface_by_id[ $record['surface_id'] ] ?? null;
	if ( ! is_array( $surface ) || ( $surface['product'] ?? null ) !== $record['product'] ) {
		throw new RuntimeException( 'Unknown/unowned content-admission surface' );
	}
	$rules = $surface['source_path_rules'] ?? null;
	if ( ! is_array( $rules ) || empty( $rules ) ) {
		throw new RuntimeException( 'Invalid content-admission surface path rules' );
	}
	pgr_admission_unique_strings( $rules, $record['surface_id'] . ' source-path rules' );

	$index_path = pgr_content_repository_path( $root, $record['surface_evidence_index_path'] ?? null, 'surface evidence index' );
	if ( ! is_file( $index_path ) || hash_file( 'sha256', $index_path ) !== $record['surface_evidence_index_sha256'] ) {
		throw new RuntimeException( 'Content source-evidence index drift' );
	}
	$index = pgr_admission_json( $index_path );
	if ( $record['surface_id'] !== ( $index['surface_id'] ?? null ) || $rules !== ( $index['source_path_rules'] ?? null ) ) {
		throw new RuntimeException( 'Content source-evidence index scope drift' );
	}
	$index_paths   = $index['paths'] ?? null;
	$index_entries = $index['entries'] ?? null;
	if ( ! is_array( $index_paths ) || ! is_array( $index_entries ) || $index_paths !== array_values( $index_paths ) || $index_entries !== array_values( $index_entries ) ) {
		throw new RuntimeException( 'Invalid content source-evidence index shape' );
	}
	pgr_admission_unique_strings( $index_paths, $record['surface_id'] . ' evidence paths' );
	foreach ( $index_paths as $path ) {
		if ( ! pgr_content_surface_path_matches( $path, $rules ) ) {
			throw new RuntimeException( 'Out-of-surface source evidence path: ' . $path );
		}
	}

	$index_ids    = array();
	$seen_ids     = array();
	$surface_rows = array();
	foreach ( $index_entries as $item ) {
		if ( ! is_array( $item ) || 2 !== count( $item ) ) {
			throw new RuntimeException( 'Invalid content evidence index entry shape' );
		}
		$id      = $item[0];
		$indexes = $item[1];
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || isset( $seen_ids[ $id ] ) || ! is_array( $indexes ) || empty( $indexes ) ) {
			throw new RuntimeException( 'Invalid/duplicate content evidence identity' );
		}
		$seen_ids[ $id ] = true;
		$seen_indexes     = array();
		foreach ( $indexes as $path_index ) {
			if ( ! is_int( $path_index ) || ! isset( $index_paths[ $path_index ] ) || isset( $seen_indexes[ $path_index ] ) ) {
				throw new RuntimeException( 'Invalid/duplicate content evidence path index' );
			}
			$seen_indexes[ $path_index ] = true;
			$surface_rows[]               = $id . "\x1f" . $index_paths[ $path_index ];
		}
		$index_ids[] = $id;
	}
	sort( $index_ids, SORT_STRING );
	if ( $record['admitted_message_count'] !== count( $index_ids ) ||
		$record['admitted_keyset_sha256'] !== pgr_content_hash_lines( $index_ids ) ||
		$record['admitted_surface_path_index_sha256'] !== pgr_content_hash_lines( $surface_rows ) ) {
		throw new RuntimeException( 'Content source-evidence fingerprints drift' );
	}

	$provider_path = pgr_content_repository_path( $root, $record['provider_source_path'] ?? null, 'admission provider source' );
	if ( ! is_file( $provider_path ) || hash_file( 'sha256', $provider_path ) !== $record['provider_source_sha256'] ) {
		throw new RuntimeException( 'Sparse admission provider source drift' );
	}
	$provider = pgr_content_load_sparse_po( $provider_path, $record['domain'], $record['locale'] );
	if ( $provider['ids'] !== $index_ids ) {
		throw new RuntimeException( 'Sparse admission provider source is not the admitted identity set' );
	}
	if ( $record['admitted_translation_content_sha256'] !== pgr_content_hash_lines( array_values( $provider['translation_rows'] ) ) ) {
		throw new RuntimeException( 'Admitted translation-content fingerprint drift' );
	}

	$record['computed'] = array(
		'admitted_message_count'              => count( $index_ids ),
		'admitted_keyset_sha256'              => pgr_content_hash_lines( $index_ids ),
		'admitted_surface_path_index_sha256'  => pgr_content_hash_lines( $surface_rows ),
		'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $provider['translation_rows'] ) ),
	);
	$record['translation_rows'] = $provider['translation_rows'];
	return $record;
}

/**
 * Validate N independently admitted records and build deterministic product unions.
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
	$revision = $manifest['content_admission_revision'] ?? null;

	if ( ! in_array( $revision, array( 2, 3 ), true ) ||
		'SHA256_UTF8_MSGCTXT_US_MSGID_US_MSGID_PLURAL' !== ( $manifest['identity_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256' !== ( $manifest['keyset_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_MATCHED_SOURCE_PATH' !== ( $manifest['surface_path_hash_method'] ?? null ) ||
		'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_NUL_JOINED_MSGSTR' !== ( $manifest['translation_hash_method'] ?? null ) ||
		( 3 === $revision && 'SHA256_UTF8_NUL_JOINED_MSGSTR' !== ( $manifest['review_translation_hash_method'] ?? null ) ) ) {
		throw new RuntimeException( 'Invalid content-admission revision/hash contract' );
	}
	$records = $manifest['admissions'] ?? null;
	if ( ! is_array( $records ) || $records !== array_values( $records ) ) {
		throw new RuntimeException( 'Content admissions must be a list' );
	}

	$surface_by_id = array();
	foreach ( $surfaces['surfaces'] ?? array() as $surface ) {
		$id = $surface['surface_id'] ?? null;
		if ( ! is_string( $id ) || '' === $id || isset( $surface_by_id[ $id ] ) ) {
			throw new RuntimeException( 'Invalid/duplicate surface registry identity' );
		}
		$surface_by_id[ $id ] = $surface;
	}

	$tuples     = array();
	$by_product = array();
	foreach ( $records as $record ) {
		if ( ! is_array( $record ) ) {
			throw new RuntimeException( 'Invalid content-admission record' );
		}
		foreach ( array( 'product', 'domain', 'locale', 'target_version', 'content_state' ) as $field ) {
			if ( ! isset( $record[ $field ] ) || ! is_string( $record[ $field ] ) || '' === $record[ $field ] ) {
				throw new RuntimeException( 'Missing content-admission record identity field: ' . $field );
			}
		}
		if ( 2 === $revision && 'CONTENT_ADMITTED_REMAINDER' === $record['content_state'] ) {
			throw new RuntimeException( 'Remainder admissions require content-admission revision 3' );
		}
		$scope = $record['surface_id'] ?? ( $record['authority_scope'] ?? null );
		if ( ! is_string( $scope ) || '' === $scope ) {
			throw new RuntimeException( 'Missing content-admission surface/scope identity' );
		}
		$tuple = pgr_content_record_identity( $record );
		if ( isset( $tuples[ $tuple ] ) ) {
			throw new RuntimeException( 'Duplicate content-admission record identity tuple' );
		}
		$tuples[ $tuple ] = true;
		$validated        = pgr_validate_content_admission_record( $root, $record, $source_admission, $products, $surface_by_id );
		$by_product[ $validated['product'] ][] = $validated;
	}

	$result = array();
	ksort( $by_product, SORT_STRING );
	foreach ( $by_product as $product => $admissions ) {
		usort( $admissions, static fn( $left, $right ) => pgr_content_record_identity( $left ) <=> pgr_content_record_identity( $right ) );
		$first          = $admissions[0];
		$surface_rows   = array();
		$remainder_rows = array();
		$remainder      = null;
		foreach ( $admissions as $admission ) {
			$target = 'CONTENT_ADMITTED_REMAINDER' === $admission['content_state'] ? 'remainder' : 'surface';
			if ( 'remainder' === $target ) {
				if ( null !== $remainder ) {
					throw new RuntimeException( 'Multiple product-remainder admissions are not allowed: ' . $product );
				}
				$remainder = $admission;
			}
			foreach ( $admission['translation_rows'] as $id => $row ) {
				if ( 'surface' === $target ) {
					if ( isset( $surface_rows[ $id ] ) && $surface_rows[ $id ] !== $row ) {
						throw new RuntimeException( 'Conflicting translation content for shared admitted identity' );
					}
					$surface_rows[ $id ] = $row;
				} else {
					if ( isset( $remainder_rows[ $id ] ) ) {
						throw new RuntimeException( 'Duplicate product-remainder identity' );
					}
					$remainder_rows[ $id ] = $row;
				}
			}
		}
		ksort( $surface_rows, SORT_STRING );
		ksort( $remainder_rows, SORT_STRING );

		$content_state       = 'CONTENT_ADMITTED_PARTIAL';
		$product_revision    = 2;
		$union_rows          = $surface_rows;
		if ( null !== $remainder ) {
			if ( count( $surface_rows ) !== $remainder['preexisting_accepted_message_count'] ||
				pgr_content_hash_lines( array_keys( $surface_rows ) ) !== $remainder['preexisting_accepted_keyset_sha256'] ||
				pgr_content_hash_lines( array_values( $surface_rows ) ) !== $remainder['preexisting_accepted_translation_content_sha256'] ) {
				throw new RuntimeException( 'Preexisting accepted translation baseline drift: ' . $product );
			}
			if ( array_intersect_key( $surface_rows, $remainder_rows ) ) {
				throw new RuntimeException( 'Product remainder overlaps preexisting accepted authority: ' . $product );
			}
			$union_rows = $surface_rows + $remainder_rows;
			ksort( $union_rows, SORT_STRING );
			$union_ids = array_keys( $union_rows );
			$canonical_ids = $remainder['canonical_ids'];
			sort( $canonical_ids, SORT_STRING );
			if ( count( $union_rows ) !== $remainder['canonical_message_count'] || $union_ids !== $canonical_ids ||
				pgr_content_source_keyset_hash( $union_ids ) !== $remainder['canonical_keyset_sha256'] ) {
				throw new RuntimeException( 'Completed product union does not equal the exact canonical source census: ' . $product );
			}
			$content_state    = 'CONTENT_ADMITTED_FULL';
			$product_revision = 3;
		}

		$union_ids = array_keys( $union_rows );
		$aggregate_path_relative = 'languages/providers/' . $product . '/source/' . $first['locale'] . '.po';
		$aggregate_path = pgr_content_repository_path( $root, $aggregate_path_relative, 'aggregate provider source' );
		if ( ! is_file( $aggregate_path ) ) {
			throw new RuntimeException( 'Missing aggregate sparse provider source: ' . $product );
		}
		// The remainder PO is validated with protected-literal enforcement above. Do not
		// retroactively apply that new rule to historical accepted surface translations.
		$aggregate_catalog = pgr_content_load_sparse_po( $aggregate_path, $first['domain'], $first['locale'] );
		if ( $aggregate_catalog['ids'] !== $union_ids || $aggregate_catalog['translation_rows'] !== $union_rows ) {
			throw new RuntimeException( 'Aggregate sparse provider source is not the exact admitted union' );
		}
		$aggregate = array(
			'admitted_message_count'              => count( $union_ids ),
			'admitted_keyset_sha256'              => pgr_content_hash_lines( $union_ids ),
			'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $union_rows ) ),
			'provider_source_path'                 => $aggregate_path_relative,
			'provider_source_sha256'               => hash_file( 'sha256', $aggregate_path ),
			'native_js_handles_activated'          => 0,
			'js_translation_json_generated'        => 0,
		);

		$provenance_records = array();
		foreach ( $admissions as &$admission ) {
			$provenance_records[] = pgr_content_provenance_record( $admission );
			unset( $admission['translation_rows'], $admission['canonical_ids'] );
		}
		unset( $admission );

		$provenance_path = dirname( $aggregate_path ) . '/provenance.json';
		if ( ! is_file( $provenance_path ) ) {
			throw new RuntimeException( 'Missing product content provenance: ' . $product );
		}
		$provenance = pgr_admission_json( $provenance_path );
		foreach ( array( 'admitted_surface_id', 'admitted_message_count', 'admitted_keyset_sha256', 'admitted_surface_path_index_sha256', 'admitted_translation_content_sha256', 'surface_evidence_index_sha256', 'admitted_provider_po_sha256' ) as $deprecated_field ) {
			if ( array_key_exists( $deprecated_field, $provenance ) ) {
				throw new RuntimeException( 'Deprecated singular content provenance field: ' . $deprecated_field );
			}
		}
		$expected_aggregate = array(
			'admitted_message_count'              => $aggregate['admitted_message_count'],
			'admitted_keyset_sha256'              => $aggregate['admitted_keyset_sha256'],
			'admitted_translation_content_sha256' => $aggregate['admitted_translation_content_sha256'],
			'provider_source_path'                 => $aggregate['provider_source_path'],
			'provider_source_sha256'               => $aggregate['provider_source_sha256'],
		);
		if ( $product_revision !== ( $provenance['content_admission_revision'] ?? null ) ||
			$content_state !== ( $provenance['content_admission_state'] ?? null ) ||
			$provenance_records !== ( $provenance['content_admissions'] ?? null ) ||
			$expected_aggregate !== ( $provenance['content_aggregate'] ?? null ) ||
			0 !== ( $provenance['native_js_handles_activated'] ?? null ) ||
			0 !== ( $provenance['js_translation_json_generated'] ?? null ) ) {
			throw new RuntimeException( 'Product content provenance drift: ' . $product );
		}

		$result[ $product ] = array(
			'product'                    => $product,
			'domain'                     => $first['domain'],
			'locale'                     => $first['locale'],
			'target_version'             => $first['target_version'],
			'content_state'              => $content_state,
			'content_admission_revision' => $product_revision,
			'admissions'                 => $admissions,
			'aggregate'                  => $aggregate,
		);
	}

	return $result;
}
