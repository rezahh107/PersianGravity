<?php
/**
 * SPL autoloader for PGR_ classes.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pgr_autoloader' ) ) {
	/**
	 * Map PGR_ class names to file paths across includes/, admin/, and feature subdirs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Incoming class name.
	 * @return void
	 */
	function pgr_autoloader( $class ) {
		if ( 0 !== strpos( $class, 'PGR_' ) ) {
			return;
		}

		// PGR_Core -> class-pgr-core.php ; PGR_National_ID -> class-pgr-national-id.php
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$filename = 'class-' . $slug . '.php';

		$paths = array(
			__DIR__ . '/',                          // includes/
			dirname( __DIR__ ) . '/admin/',          // admin/
			__DIR__ . '/features/',                  // includes/features/
			__DIR__ . '/modules/',                   // includes/modules/
			__DIR__ . '/fields/',                    // includes/fields/
		);

		foreach ( $paths as $base ) {
			$file = $base . $filename;
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}

spl_autoload_register( 'pgr_autoloader' );
