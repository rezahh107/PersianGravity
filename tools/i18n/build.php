<?php
/** Deterministic provider build/check with source and bounded content-admission guards. */
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/admission.php';
require __DIR__ . '/content-admission.php';

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

$mode = $argv[1] ?? '--check';
if ( ! in_array( $mode, array( '--write', '--check' ), true ) ) {
	throw new RuntimeException( 'Use --check or --write' );
}

$products          = require ABSPATH . 'includes/localization/products.php';
$admission         = pgr_validate_admission( ABSPATH );
$content_admission = pgr_validate_content_admission( ABSPATH, $admission, $products );
$root              = ABSPATH . 'languages/providers';
$expected          = array();

foreach ( $products as $domain => $product ) {
	$dir        = $root . '/' . $product['product'];
	$source     = $dir . '/source';
	$provenance = json_decode( file_get_contents( $source . '/provenance.json' ), true, 512, JSON_THROW_ON_ERROR );
	$built      = pgr_compile_catalog( $source . '/fa_IR.po', $domain );
	$counts     = $built['counts'];
	$known      = array_sum( $counts );
	$status     = $provenance['source_status'] ?? '';
	$verified   = 'VERIFIED' === $status;
	$metadata_only = 'PACKAGE_INSPECTED_METADATA_ONLY' === $status;
	$unavailable   = 'PACKAGE_UNAVAILABLE' === $status;
	$content       = $content_admission[ $product['product'] ] ?? null;
	$partial       = is_array( $content ) && 'CONTENT_ADMITTED_PARTIAL' === ( $content['content_state'] ?? null );

	if ( ! $verified && ! $metadata_only && ! $unavailable ) {
		throw new RuntimeException( 'Unknown source status: ' . $domain );
	}
	if ( $product['target_version'] !== $provenance['target_product_version'] ) {
		throw new RuntimeException( 'Target version drift' );
	}
	if ( ( $metadata_only || $unavailable ) && ! $partial && ( 0 !== $known || array() !== $product['scripts'] ) ) {
		throw new RuntimeException( 'Non-content source state must remain runtime dormant: ' . $domain );
	}
	if ( $partial &&
		( ! $metadata_only ||
			array() !== $product['scripts'] ||
			$known !== $content['aggregate']['admitted_message_count'] ||
			hash_file( 'sha256', $source . '/fa_IR.po' ) !== $content['aggregate']['provider_source_sha256'] ) ) {
		throw new RuntimeException( 'Invalid partial-content aggregate runtime boundary: ' . $domain );
	}

	if ( $metadata_only ) {
		$record = $admission[ $product['product'] ] ?? null;
		if ( ! $record ||
			$provenance['source_product_version'] !== $record['observed_source_version'] ||
			$provenance['source_package_sha256'] !== $record['package_sha256'] ||
			$provenance['vendor_pot_sha256'] !== $record['vendor_pot_sha256'] ||
			null !== $provenance['source_pot_sha256'] ||
			'PASS' !== $provenance['source_pot_consistency'] ||
			is_file( $source . '/source.pot' ) ) {
			throw new RuntimeException( 'Invalid metadata-only source provenance: ' . $domain );
		}
	}

	if ( $verified ) {
		if ( ! is_file( $source . '/source.pot' ) ||
			hash_file( 'sha256', $source . '/source.pot' ) !== $provenance['source_pot_sha256'] ||
			$provenance['source_product_version'] !== $product['target_version'] ||
			! preg_match( '/^[a-f0-9]{64}$/D', $provenance['source_package_sha256'] ?? '' ) ) {
			throw new RuntimeException( 'Missing source POT/package provenance: ' . $domain );
		}
		$pot  = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $source . '/source.pot' );
		$po   = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $source . '/fa_IR.po' );
		$keys = static function ( $entries ) {
			$result = array();
			foreach ( $entries as $entry ) {
				if ( ! $entry->isDisabled() ) {
					$result[] = array( $entry->getContext(), $entry->getOriginal(), $entry->getPlural() );
				}
			}
			sort( $result );
			return $result;
		};
		if ( $keys( $pot ) !== $keys( $po ) ) {
			throw new RuntimeException( 'PO source census does not match authoritative POT: ' . $domain );
		}
	}

	$artifacts = array();
	if ( $counts['translated'] > 0 ) {
		$base                             = $product['prefix'] . '-fa_IR';
		$artifacts[ $base . '.mo' ]       = $built['mo'];
		$artifacts[ $base . '.l10n.php' ] = $built['php'];
	}

	foreach ( $product['scripts'] as $handle => $script_record ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $handle ) ||
			empty( $script_record['references'] ) ||
			empty( $script_record['evidence'] ) ||
			( $script_record['source_version'] ?? '' ) !== $product['target_version'] ) {
			throw new RuntimeException( 'Unverified script record: ' . $handle );
		}
		$subset = clone $built['catalog'];
		foreach ( $subset as $entry ) {
			$paths = array_keys( iterator_to_array( $entry->getReferences() ) );
			if ( ! array_intersect( $paths, $script_record['references'] ) ) {
				$entry->disable();
			}
		}
		$temp_po = tempnam( sys_get_temp_dir(), 'pgr-po-' );
		try {
			( new Gettext\Generator\PoGenerator() )->generateFile( $subset, $temp_po );
			$script = pgr_compile_catalog( $temp_po, $domain );
		} finally {
			unlink( $temp_po );
		}
		$artifacts[ $domain . '-fa_IR-' . $handle . '.json' ] = $script['json'];
	}

	$hashes = array();
	foreach ( $artifacts as $name => $bytes ) {
		$hashes[ $name ] = hash( 'sha256', $bytes );
	}

	$source_record       = $admission[ $product['product'] ] ?? null;
	$authoritative_total = $verified ? $known : ( $metadata_only ? $source_record['canonical_message_count'] : null );
	if ( $verified && $known > 0 ) {
		$coverage = round( 100 * $counts['translated'] / $known, 2 );
	} elseif ( $partial ) {
		$coverage = round( 100 * $counts['translated'] / $source_record['canonical_message_count'], 2 );
	} else {
		$coverage = $metadata_only ? 0 : null;
	}

	if ( $partial ) {
		$content_status = 'PARTIAL_TRANSLATION_CONTENT_SURFACE_ADMITTED';
	} elseif ( $verified ) {
		$content_status = 'PARTIAL_TRANSLATION_CONTENT';
	} elseif ( $metadata_only ) {
		$content_status = 'SOURCE_INSPECTED_METADATA_ONLY_NO_TRANSLATION_CONTENT';
	} else {
		$content_status = 'SOURCE_UNAVAILABLE_EMPTY_SCAFFOLD';
	}
	$surface_status = $verified ? 'SOURCE_POT_VERIFIED' : ( $metadata_only ? 'STATIC_SOURCE_CONFIRMED' : 'NOT_EXECUTED_PACKAGE_UNAVAILABLE' );

	$metadata = array(
		'product'                    => $product['product'],
		'domain'                     => $domain,
		'locale'                     => 'fa_IR',
		'provenance'                 => $provenance,
		'provider_po_sha256'         => hash_file( 'sha256', $source . '/fa_IR.po' ),
		'counts_in_committed_po'     => $counts,
		'authoritative_total'        => $authoritative_total,
		'coverage_percent'           => $coverage,
		'content_status'             => $content_status,
		'validated_script_handles'   => array_keys( $product['scripts'] ),
		'artifact_sha256'            => (object) $hashes,
		'vendor_surface_drift_check' => $surface_status,
		'generator'                  => 'gettext/gettext 5.7.3 + tools/i18n/catalog.php',
	);
	if ( $partial ) {
		$metadata['content_admission'] = array(
			'revision'   => 2,
			'state'      => $content['content_state'],
			'admissions' => array_map( 'pgr_content_provenance_record', $content['admissions'] ),
			'aggregate'  => $content['aggregate'],
		);
	}

	$artifacts['metadata.json'] = json_encode(
		$metadata,
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	) . "\n";

	foreach ( $artifacts as $name => $bytes ) {
		$expected[ $dir . '/' . $name ] = $bytes;
	}
}

$actual = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( $file->isFile() &&
		! str_contains( $file->getPathname(), '/source/' ) &&
		'README.md' !== $file->getFilename() ) {
		$actual[] = $file->getPathname();
	}
}
foreach ( array_diff( $actual, array_keys( $expected ) ) as $extra ) {
	throw new RuntimeException( 'Unapproved/orphan artifact: ' . $extra );
}

foreach ( $expected as $path => $bytes ) {
	if ( '--write' === $mode ) {
		file_put_contents( $path, $bytes );
		continue;
	}
	$temp = tempnam( sys_get_temp_dir(), 'pgr-artifact-' );
	try {
		file_put_contents( $temp, $bytes );
		if ( ! is_file( $path ) || hash_file( 'sha256', $temp ) !== hash_file( 'sha256', $path ) ) {
			throw new RuntimeException( 'Artifact drift: ' . $path );
		}
	} finally {
		unlink( $temp );
	}
}

echo 'Catalog ' . $mode . ': PASS; approved provider content and artifact state are deterministic.' . PHP_EOL;
