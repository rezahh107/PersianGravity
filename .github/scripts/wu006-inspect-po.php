<?php
if ( $argc !== 2 ) { fwrite(STDERR, "usage: php inspect-po.php <po>\n"); exit(2); }
$root = getcwd();
require $root . '/tools/i18n/admission.php';
require $root . '/vendor/autoload.php';
require $root . '/tools/i18n/content-admission.php';
$provider = pgr_content_load_sparse_po( $argv[1], 'gk-gravityview', 'fa_IR' );
echo json_encode( $provider, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), "\n";
