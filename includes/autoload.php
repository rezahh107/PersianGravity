<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

spl_autoload_register( function( $class ) {
    if ( strpos( $class, 'PGR_' ) !== 0 ) {
        return;
    }
    $map = array(
        'PGR_Admin'                    => 'admin/class-pgr-admin.php',
        'PGR_Utils'                    => 'includes/class-pgr-utils.php',
        'PGR_GF_Field_National_ID'     => 'includes/fields/class-gf-field-national-id.php',
    );
    if ( isset( $map[ $class ] ) ) {
        $file = PGR_PATH . $map[ $class ];
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );
