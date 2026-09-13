<?php
require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

$root         = dirname( __DIR__ );
$record_paths = glob( $root . '/languages/providers/gravityforms/source/records/*-fa_IR.po' );
sort( $record_paths, SORT_STRING );
$out = array( 'records' => array() );
foreach ( $record_paths as $path ) {
    $provider = pgr_content_load_sparse_po( $path, 'gravityforms', 'fa_IR' );
    $relative = substr( $path, strlen( $root ) + 1 );
    $out['records'][ $relative ] = array(
        'provider_source_sha256'              => hash_file( 'sha256', $path ),
        'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $provider['translation_rows'] ) ),
        'admitted_message_count'              => count( $provider['ids'] ),
        'admitted_keyset_sha256'              => pgr_content_hash_lines( $provider['ids'] ),
    );
}
$aggregate_path = $root . '/languages/providers/gravityforms/source/fa_IR.po';
$aggregate      = pgr_content_load_sparse_po( $aggregate_path, 'gravityforms', 'fa_IR' );
$out['aggregate'] = array(
    'provider_source_sha256'              => hash_file( 'sha256', $aggregate_path ),
    'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $aggregate['translation_rows'] ) ),
    'admitted_message_count'              => count( $aggregate['ids'] ),
    'admitted_keyset_sha256'              => pgr_content_hash_lines( $aggregate['ids'] ),
);
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
