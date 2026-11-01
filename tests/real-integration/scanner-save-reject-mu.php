<?php
/**
 * Disposable WU008 ONLY: force a single, recognizable Registry write rejection.
 *
 * Loaded as a temporary must-use plugin by the licensed workflow, and deleted
 * immediately afterward. It never alters production plugin code or the
 * canonical Registry option.
 */
defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_update_option_pgr_scanner_profiles',
	static function ( $next, $previous ) {
		if ( ! is_array( $next ) || ! is_array( $next['profiles'] ?? null ) ) {
			return $next;
		}
		foreach ( $next['profiles'] as $profile ) {
			if ( is_array( $profile ) && 'WU008_DENY_SCANNER_SAVE' === ( $profile['label'] ?? '' ) ) {
				return $previous;
			}
		}
		return $next;
	},
	10,
	2
);
