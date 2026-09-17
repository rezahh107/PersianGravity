<?php
/** G-007 extension of the shared deterministic localization build. */

/**
 * Preserve source PO header order in G-007 MO bytes after the shared compiler runs.
 *
 * gettext/gettext sorts its Headers object by name. Header order is semantically
 * irrelevant, but normalizing back to the repository-managed PO order keeps the
 * committed G-007 binary artifacts stable without replacing the shared compiler.
 *
 * @param array  $built   Shared compiler result.
 * @param string $po_path Provider PO path.
 * @return string
 */
function pgr_g007_source_order_mo( $built, $po_path ) {
	$mo      = $built['mo'];
	$headers = iterator_to_array( $built['catalog']->getHeaders() );
	$sorted  = array();
	foreach ( $headers as $name => $value ) {
		$sorted[] = $name . ': ' . $value;
	}
	$sorted_block = implode( "\n", $sorted );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local repository-managed PO source.
	$po = file_get_contents( $po_path );
	if ( false === $po ) {
		throw new RuntimeException( 'Unable to read G-007 provider PO: ' . $po_path );
	}

	preg_match_all( '/^"([^":]+):[^\r\n]*\\\\n"$/m', $po, $matches );
	$source_order = array();
	$seen         = array();
	foreach ( $matches[1] as $name ) {
		if ( isset( $headers[ $name ] ) && ! isset( $seen[ $name ] ) ) {
			$source_order[] = $name . ': ' . $headers[ $name ];
			$seen[ $name ]  = true;
		}
	}
	foreach ( $headers as $name => $value ) {
		if ( ! isset( $seen[ $name ] ) ) {
			$source_order[] = $name . ': ' . $value;
		}
	}
	$source_block = implode( "\n", $source_order );

	if ( strlen( $sorted_block ) !== strlen( $source_block ) || 1 !== substr_count( $mo, $sorted_block ) ) {
		throw new RuntimeException( 'Unable to normalize G-007 MO header order: ' . $po_path );
	}

	$offset = strpos( $mo, $sorted_block );
	return substr_replace( $mo, $source_block, $offset, strlen( $sorted_block ) );
}

/**
 * Return deterministic runtime artifacts for the exact G-007 product family.
 *
 * @param string $root Repository root.
 * @param string $provider_root Provider artifact root.
 * @return array Absolute output paths keyed to expected bytes.
 */
function pgr_g007_expected_artifacts( $root, $provider_root ) {
	$root          = rtrim( $root, '/\\' ) . '/';
	$provider_root = rtrim( $provider_root, '/\\' );
	$products      = require $root . 'includes/localization/g007-products.php';
	$records       = pgr_validate_g007_admission( $root );
	$expected      = array();

	foreach ( $products as $domain => $product ) {
		$record = $records[ $product['product'] ] ?? null;
		if ( ! is_array( $record ) ) {
			throw new RuntimeException( 'Missing G-007 validated admission: ' . $domain );
		}

		$dir        = $provider_root . '/' . $product['product'];
		$source     = $dir . '/source';
		$po_path    = $source . '/fa_IR.po';
		$provenance = json_decode( file_get_contents( $source . '/provenance.json' ), true, 512, JSON_THROW_ON_ERROR );
		$built      = pgr_compile_catalog( $po_path, $domain );
		$counts     = $built['counts'];
		$total      = $record['canonical_message_count'];

		if ( array( 'translated' => $total, 'untranslated' => 0, 'fuzzy' => 0 ) !== $counts ||
			hash_file( 'sha256', $po_path ) !== $record['provider_source_sha256'] ||
			array() !== $product['scripts'] ) {
			throw new RuntimeException( 'G-007 full-provider build boundary drift: ' . $domain );
		}

		$base      = $product['prefix'] . '-fa_IR';
		$artifacts = array(
			$base . '.mo'       => pgr_g007_source_order_mo( $built, $po_path ),
			$base . '.l10n.php' => $built['php'],
		);
		$hashes = array();
		foreach ( $artifacts as $name => $bytes ) {
			$hashes[ $name ] = hash( 'sha256', $bytes );
			$expected[ $dir . '/' . $name ] = $bytes;
		}

		$aggregate = array(
			'admitted_message_count'              => $total,
			'admitted_keyset_sha256'              => $record['canonical_keyset_sha256'],
			'admitted_translation_content_sha256' => $record['admitted_translation_content_sha256'],
			'provider_source_path'                => $record['provider_source_path'],
			'provider_source_sha256'              => $record['provider_source_sha256'],
			'native_js_handles_activated'         => 0,
			'js_translation_json_generated'       => 0,
		);
		$metadata = array(
			'product'                    => $product['product'],
			'domain'                     => $domain,
			'locale'                     => 'fa_IR',
			'provenance'                 => $provenance,
			'provider_po_sha256'         => $record['provider_source_sha256'],
			'counts_in_committed_po'     => $counts,
			'authoritative_total'        => $total,
			'coverage_percent'           => 100,
			'content_status'             => 'FULL_TRANSLATION_CONTENT_ACCEPTED',
			'validated_script_handles'   => array(),
			'artifact_sha256'            => $hashes,
			'vendor_surface_drift_check' => 'STATIC_SOURCE_CONFIRMED',
			'generator'                  => 'gettext/gettext 5.7.3 + G-007 source-order MO header normalization',
			'content_admission'          => array(
				'goal'                => 'G-007',
				'state'               => 'CONTENT_ADMITTED_FULL',
				'review_index_path'   => $record['review_index_path'],
				'review_index_sha256' => $record['review_index_sha256'],
				'aggregate'           => $aggregate,
			),
		);
		$expected[ $dir . '/metadata.json' ] = json_encode(
			$metadata,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		) . "\n";
	}

	return $expected;
}
