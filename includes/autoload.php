<?php
/**
 * Autoloader for Persian Gravity Forms (PGR)
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PSR-4 inspired autoloader with explicit mapping for special cases.
 * 
 * Converts class name to file path: PGR_Example_Class -> class-pgr-example-class.php
 * Searches in predefined directories.
 *
 * @param string $class Class name to load.
 */
spl_autoload_register( function( $class ) {
    // Only handle PGR_ prefixed classes
    if ( strpos( $class, 'PGR_' ) !== 0 ) {
        return;
    }

    // Explicit mapping for classes that don't follow naming convention
    $map = array(
        'PGR_Admin'                => 'admin/class-pgr-admin.php',
        'PGR_Utils'                => 'includes/class-pgr-utils.php',
        'PGR_GF_Field_National_ID' => 'includes/fields/class-gf-field-national-id.php',
        'PGR_Core'                 => 'includes/class-pgr-core.php',
        'PGR_Persian_Date'         => 'includes/class-pgr-persian-date.php',
        'PGR_Installer'            => 'includes/class-pgr-installer.php',
    );

    // Check explicit map first
    if ( isset( $map[ $class ] ) ) {
        $file = PGR_PATH . $map[ $class ];
        if ( file_exists( $file ) ) {
            require_once $file;
        }
        return;
    }

    // Dynamic conversion: PGR_Example_Class -> class-pgr-example-class.php
    // Remove prefix and convert underscores to hyphens
    $relative_class = substr( $class, 4 ); // Remove 'PGR_'
    $relative_class = str_replace( '_', '-', strtolower( $relative_class ) );
    
    // Directories to search (ordered by priority)
    $directories = array(
        PGR_PATH . 'includes/',
        PGR_PATH . 'includes/fields/',
        PGR_PATH . 'admin/',
        PGR_PATH . 'public/',
    );

    $file_name = 'class-pgr-' . $relative_class . '.php';
    
    foreach ( $directories as $dir ) {
        $file = $dir . $file_name;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }

    // Optional: log error in debug mode
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            '[PGR Autoloader] Class %s not found. Searched in: %s',
            $class,
            implode( ', ', $directories ) . ' with filename ' . $file_name
        ) );
    }
} );
