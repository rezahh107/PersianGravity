<?php
/**
 * SPL autoloader for Persian Gravity Forms Refactor classes.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'PGR_' ) ) {
			return;
		}

		$normalized = strtolower( str_replace( '_', '-', $class ) );
		$filenames  = array(
			'class-' . $normalized . '.php',
			'trait-' . $normalized . '.php',
			'interface-' . $normalized . '.php',
		);

		$paths = array(
			'includes' => PGR_PATH . 'includes/',
			'admin'    => PGR_PATH . 'admin/',
		);

		/**
		 * Filters the directories searched by the autoloader.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $paths Array of directory paths keyed by context.
		 * @param string $class Fully-qualified class name being loaded.
		 */
		$paths = apply_filters( 'pgr_autoload_paths', $paths, $class );

		foreach ( (array) $paths as $path ) {
			if ( empty( $path ) ) {
				continue;
			}

			$path = trailingslashit( $path );

			foreach ( $filenames as $filename ) {
				$file = $path . $filename;

				if ( is_readable( $file ) ) {
					require_once $file;

					return;
				}
			}
		}
	}
);
