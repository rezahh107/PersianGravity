<?php
/** G-007 extension of the shared deterministic localization build. */

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
			$base . '.mo'       => $built['mo'],
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
			'generator'                  => 'gettext-compatible deterministic G-007 build via tools/i18n/catalog.php contract',
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
