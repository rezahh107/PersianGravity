<?php
/** Deterministic G-007 source/content admission validation. */

/**
 * Hash a sorted set of strings using the G-007 no-trailing-newline contract.
 *
 * @param array $rows Rows to hash.
 * @return string
 */
function pgr_g007_hash_rows( array $rows ) {
	sort( $rows, SORT_STRING );
	return hash( 'sha256', implode( "\n", $rows ) );
}

/**
 * Extract G-007 protected product/technical literals.
 *
 * @param string $value Source or translation text.
 * @return array
 */
function pgr_g007_protected_literals( $value ) {
	$pattern = '/Gravity Forms|Gravity Perks|WordPress|\bAPI\b|\bPHP\b|Spellbook|GP Google Sheets|GC Google Sheets|File Upload Pro|GP Advanced Select|Enhanced UI|Advanced Select/';
	if ( ! preg_match_all( $pattern, $value, $matches ) ) {
		return array();
	}
	$result = $matches[0];
	sort( $result, SORT_STRING );
	return $result;
}

/**
 * Validate the exact three G-007 products without broadening legacy source authority.
 *
 * @param string $root Repository root.
 * @return array Validated records keyed by product.
 */
function pgr_validate_g007_admission( $root ) {
	$root     = rtrim( $root, '/\\' ) . '/';
	$manifest = require $root . 'includes/localization/g007-products.php';
	$path     = $root . 'tools/i18n/admission/g007-gravity-perks.json';
	$data     = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	$expected = array(
		'gravityperks' => array(
			'product'        => 'gravityperks',
			'version'        => '2.3.16',
			'package'        => 'a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c',
			'pot'            => 'd56ae3bab39365193e17f9c4113ed30e65f33565d2697749df2931890b6f453d',
			'count'          => 83,
			'ref_count'      => 98,
			'keyset'         => '5d700169deb042f7848ffe551bfdd88595420c4db83d651f09d2dd48c7b52df5',
			'references'     => 'bd6565886444416b156616ee95dc7c4f486cd32549f9822f64cb375cb737fe3b',
			'provider_po'    => 'ec592347136925b2b724f9edd8c59f35c30401b0369631a94843492b5a365a89',
			'translations'   => '952531fdec3c08cc4165baa6d3ab7ad6dfaad2801d9c72774e12cb1e8e141d20',
			'foreign_counts' => array( 'gravity-perks' => 1 ),
			'dynamic_count'  => 4,
		),
		'gp-file-upload-pro' => array(
			'product'        => 'gp-file-upload-pro',
			'version'        => '1.5.13',
			'package'        => 'fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce',
			'pot'            => 'f2ccb660acd7b6952950eef9a617386da6b4bc19a22ede8f765765578ece1b7b',
			'count'          => 39,
			'ref_count'      => 44,
			'keyset'         => '594d4f31c1e8fd022ee19fdfeed2fb7b0acbc68ad4d88e870a23c93caada783a',
			'references'     => '1d6e77eacea1ca827be026acdfd902f933a23cbec2f8cc5103376d2b6f24e068',
			'provider_po'    => '27a0150cb4bdb34cb1211300eb5ac981b9d6b4f4715982c11a8f982443c4e8fc',
			'translations'   => 'd915c02e65131ca8f1ac42e75f8b8acf18c6a2cda325d66be936fa2e248a48e7',
			'foreign_counts' => array( 'gravityperks' => 1 ),
			'dynamic_count'  => 0,
		),
		'gp-advanced-select' => array(
			'product'        => 'gp-advanced-select',
			'version'        => '1.1.21',
			'package'        => 'd83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2',
			'pot'            => '1b595f32532235e931a5572b880fa03840c3420dbcfd40384cac52d566301923',
			'count'          => 5,
			'ref_count'      => 5,
			'keyset'         => 'ee9d5927678d70ccd66fbb66c80d2722e58830e23b4ebf2b0d3a556d83c6c5ec',
			'references'     => '6bf27c90006db2b831a1057764a2aa18855b56a642cbf93bb967f9332862d92e',
			'provider_po'    => '3a4e727e9d0c945508d5a9e519f5fa403d467a1036afb81c524b087dae3c477f',
			'translations'   => '7c280b7ce8b75b1796c1c6bb84fa7f6ed4dc0a4720de20ec1178eb5b47dd4960',
			'foreign_counts' => array( 'gp-advanced-phone-field' => 4, 'gp-populate-anything' => 1, 'gravityperks' => 1 ),
			'dynamic_count'  => 0,
		),
	);

	if ( 1 !== ( $data['schema_version'] ?? null ) || 'G-007' !== ( $data['goal'] ?? null ) || 'fa_IR' !== ( $data['locale'] ?? null ) ||
		array_keys( $expected ) !== array_keys( $manifest ) ) {
		throw new RuntimeException( 'G-007 manifest/admission scope drift' );
	}
	if ( count( $expected ) !== count( $data['products'] ?? array() ) ) {
		throw new RuntimeException( 'G-007 product count drift' );
	}

	$validated = array();
	foreach ( $data['products'] as $record ) {
		$domain = $record['domain'] ?? '';
		if ( ! isset( $expected[ $domain ] ) || isset( $validated[ $record['product'] ?? '' ] ) ) {
			throw new RuntimeException( 'Unexpected/duplicate G-007 domain: ' . $domain );
		}
		$want    = $expected[ $domain ];
		$product = $manifest[ $domain ];
		if ( $want['product'] !== ( $record['product'] ?? null ) ||
			$want['version'] !== ( $record['target_version'] ?? null ) ||
			$want['version'] !== ( $record['observed_source_version'] ?? null ) ||
			$want['package'] !== ( $record['package_sha256'] ?? null ) ||
			$want['pot'] !== ( $record['vendor_pot_sha256'] ?? null ) ||
			$want['count'] !== ( $record['canonical_message_count'] ?? null ) ||
			0 !== ( $record['context_message_count'] ?? null ) || 0 !== ( $record['plural_message_count'] ?? null ) ||
			$want['ref_count'] !== ( $record['source_reference_count'] ?? null ) ||
			$want['keyset'] !== ( $record['canonical_keyset_sha256'] ?? null ) ||
			$want['references'] !== ( $record['source_reference_index_sha256'] ?? null ) ||
			$want['provider_po'] !== ( $record['provider_source_sha256'] ?? null ) ||
			$want['translations'] !== ( $record['admitted_translation_content_sha256'] ?? null ) ||
			'OWNER_SUPPLIED_EXACT_PACKAGE' !== ( $record['project_source_authority'] ?? null ) ||
			'NOT_PROVEN' !== ( $record['vendor_authenticity'] ?? null ) ||
			'PACKAGE_INSPECTED_METADATA_ONLY' !== ( $record['source_status'] ?? null ) ||
			'SOURCE_BACKED_FULL_PRODUCT_DOMAIN_ADMISSION' !== ( $record['admission_mode'] ?? null ) ||
			'CONTENT_ADMITTED_FULL' !== ( $record['content_state'] ?? null ) ||
			$want['count'] !== ( $record['admitted_message_count'] ?? null ) ||
			$want['product'] !== ( $product['product'] ?? null ) ||
			$want['version'] !== ( $product['target_version'] ?? null ) ||
			$domain !== ( $product['prefix'] ?? null ) || array() !== ( $product['scripts'] ?? null ) ) {
			throw new RuntimeException( 'G-007 locked product record drift: ' . $domain );
		}

		$foreign = array();
		foreach ( $record['cross_domain_boundaries'] ?? array() as $foreign_domain => $boundary ) {
			$foreign[ $foreign_domain ] = $boundary['canonical_message_count'] ?? null;
			if ( 'EXCLUDED_CROSS_DOMAIN_REFERENCE' !== ( $boundary['scope'] ?? null ) ) {
				throw new RuntimeException( 'G-007 foreign-domain boundary drift: ' . $domain );
			}
		}
		ksort( $foreign, SORT_STRING );
		$foreign_want = $want['foreign_counts'];
		ksort( $foreign_want, SORT_STRING );
		if ( $foreign !== $foreign_want || $want['dynamic_count'] !== count( $record['dynamic_or_unmanaged_calls'] ?? array() ) ) {
			throw new RuntimeException( 'G-007 cross-domain census drift: ' . $domain );
		}

		$po_path = $root . $record['provider_source_path'];
		if ( ! is_file( $po_path ) || hash_file( 'sha256', $po_path ) !== $want['provider_po'] ) {
			throw new RuntimeException( 'G-007 provider source drift: ' . $domain );
		}
		$catalog = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $po_path );
		$headers = iterator_to_array( $catalog->getHeaders() );
		if ( 'fa_IR' !== ( $headers['Language'] ?? null ) || $domain !== ( $headers['X-Domain'] ?? null ) ||
			'nplurals=2; plural=(n > 1);' !== ( $headers['Plural-Forms'] ?? null ) ) {
			throw new RuntimeException( 'G-007 provider headers drift: ' . $domain );
		}

		$ids              = array();
		$translation_rows = array();
		$translations     = array();
		foreach ( $catalog as $entry ) {
			if ( $entry->isDisabled() || $entry->getFlags()->has( 'fuzzy' ) || null !== $entry->getPlural() ) {
				throw new RuntimeException( 'G-007 invalid admitted catalog entry: ' . $domain );
			}
			$value = $entry->getTranslation();
			if ( ! is_string( $value ) || '' === $value ) {
				throw new RuntimeException( 'G-007 empty admitted translation: ' . $domain );
			}
			$id = hash( 'sha256', (string) ( $entry->getContext() ?? '' ) . "\x1f" . $entry->getOriginal() . "\x1f" );
			if ( isset( $translations[ $id ] ) ) {
				throw new RuntimeException( 'G-007 duplicate admitted identity: ' . $domain );
			}
			if ( function_exists( 'pgr_content_tokens' ) && pgr_content_tokens( $entry->getOriginal() ) !== pgr_content_tokens( $value ) ) {
				throw new RuntimeException( 'G-007 functional token drift: ' . $entry->getOriginal() );
			}
			if ( pgr_g007_protected_literals( $entry->getOriginal() ) !== pgr_g007_protected_literals( $value ) ) {
				throw new RuntimeException( 'G-007 protected literal drift: ' . $entry->getOriginal() );
			}
			$ids[]                  = $id;
			$translation_rows[]     = $id . "\x1f" . $value;
			$translations[ $id ]    = hash( 'sha256', $value );
		}
		$translation_rows_sorted = $translation_rows;
		sort( $translation_rows_sorted, SORT_STRING );
		$translation_hash = hash( 'sha256', implode( "\n", $translation_rows_sorted ) . "\n" );
		if ( $want['count'] !== count( $ids ) || $want['keyset'] !== pgr_g007_hash_rows( $ids ) ||
			$want['translations'] !== $translation_hash ) {
			throw new RuntimeException( 'G-007 full-content fingerprint drift: ' . $domain );
		}

		$review_path = $root . $record['review_index_path'];
		if ( ! is_file( $review_path ) || hash_file( 'sha256', $review_path ) !== ( $record['review_index_sha256'] ?? null ) ) {
			throw new RuntimeException( 'G-007 review index drift: ' . $domain );
		}
		$review = json_decode( file_get_contents( $review_path ), true, 512, JSON_THROW_ON_ERROR );
		$corrected = $review['second_pass_corrected_identities'] ?? null;
		if ( 2 !== ( $review['schema_version'] ?? null ) || 'ACCEPTED_TRANSLATION_REVIEW' !== ( $review['review_scope'] ?? null ) ||
			'G-007' !== ( $review['goal'] ?? null ) || $domain !== ( $review['domain'] ?? null ) ||
			$want['version'] !== ( $review['target_version'] ?? null ) || $want['count'] !== ( $review['accepted_count'] ?? null ) ||
			0 !== ( $review['unreviewed_count'] ?? null ) || 0 !== ( $review['rejected_count'] ?? null ) ||
			2 > ( $review['semantic_review_passes'] ?? 0 ) || $want['keyset'] !== ( $review['reviewed_keyset_sha256'] ?? null ) ||
			$want['translations'] !== ( $review['reviewed_translation_content_sha256'] ?? null ) || ! is_array( $corrected ) ||
			count( $corrected ) !== ( $review['second_pass_corrections'] ?? null ) || count( $corrected ) !== count( array_unique( $corrected ) ) ||
			! is_array( $review['risk_flag_counts'] ?? null ) || ! is_int( $review['risk_reviewed_identity_count'] ?? null ) ) {
			throw new RuntimeException( 'G-007 review-state drift: ' . $domain );
		}
		foreach ( $corrected as $id ) {
			if ( ! isset( $translations[ $id ] ) ) {
				throw new RuntimeException( 'G-007 corrected-review identity drift: ' . $domain );
			}
		}

		$provenance_path = dirname( $po_path ) . '/provenance.json';
		$provenance      = json_decode( file_get_contents( $provenance_path ), true, 512, JSON_THROW_ON_ERROR );
		foreach ( array(
			'source_package_sha256'        => $want['package'],
			'vendor_pot_sha256'            => $want['pot'],
			'target_product_version'       => $want['version'],
			'source_product_version'       => $want['version'],
			'canonical_message_count'      => $want['count'],
			'canonical_keyset_sha256'      => $want['keyset'],
			'source_reference_index_sha256' => $want['references'],
			'translation_content_sha256'   => $want['translations'],
		) as $field => $value ) {
			if ( ( $provenance[ $field ] ?? null ) !== $value ) {
				throw new RuntimeException( 'G-007 provenance drift: ' . $domain . ':' . $field );
			}
		}

		$validated[ $record['product'] ] = $record;
	}

	if ( array_keys( $expected ) !== array_column( $data['products'], 'domain' ) ) {
		throw new RuntimeException( 'G-007 product order drift' );
	}
	return $validated;
}
