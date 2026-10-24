<?php
/**
 * Stable PHP 8.2 runtime policy for the licensed CI integration lanes.
 *
 * This policy does not establish cross-patch product compatibility by itself;
 * authentic runtime and browser checks qualify the installed patch.
 */

declare(strict_types=1);

function pgr_ci_accepts_php82_runtime( int $version_id, string $version ): bool {
    return $version_id >= 80200
        && $version_id < 80300
        && 1 === preg_match( '/^8\\.2\\.[0-9]+$/D', $version );
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
    if ( ! pgr_ci_accepts_php82_runtime( PHP_VERSION_ID, PHP_VERSION ) ) {
        fwrite( STDERR, 'CI requires stable PHP 8.2.x; observed ' . PHP_VERSION . PHP_EOL );
        exit( 1 );
    }

    echo 'CI PHP runtime: ' . PHP_VERSION . ' (stable 8.2.x)' . PHP_EOL;
}
