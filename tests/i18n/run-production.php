<?php
/** Real WordPress Core contract for the committed bounded Gravity Forms provider. */
require __DIR__ . '/run.php';

$production_checks = 0;
function production_check( $condition, $label ) {
	global $production_checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$production_checks;
	echo "PASS: $label\n";
}

try {
	$root         = dirname( __DIR__, 2 );
	$provider_dir = WP_CONTENT_DIR . '/providers/gravityforms';
	$upstream_dir = WP_CONTENT_DIR . '/upstream-production-gravityforms';
	mkdir( $provider_dir, 0777, true );
	mkdir( $upstream_dir, 0777, true );

	$provider_base = $provider_dir . '/gravityforms-fa_IR';
	$repo_base     = $root . '/languages/providers/gravityforms/gravityforms-fa_IR';
	copy( $repo_base . '.mo', $provider_base . '.mo' );
	copy( $repo_base . '.l10n.php', $provider_base . '.l10n.php' );

	$upstream_base = $upstream_dir . '/gravityforms-fa_IR';
	write_fixture(
		$upstream_base,
		'gravityforms',
		array( 'The URL is not valid.' => 'کنترل بالادستی' )
	);

	foreach ( array( 'php', 'mo' ) as $format ) {
		reset_domain( 'gravityforms' );
		$format_filter = static fn() => $format;
		add_filter( 'translation_file_format', $format_filter );
		$GLOBALS['wp_textdomain_registry']->set_custom_path( 'gravityforms', $upstream_dir );

		production_check(
			__( 'Validation Message Placement', 'gravityforms' ) === 'پیام اعتبارسنجی',
			"Gravity Forms admitted identity resolves from committed PersianGravity $format provider"
		);
		production_check(
			__( 'The URL is not valid.', 'gravityforms' ) === 'کنترل بالادستی',
			"Gravity Forms non-admitted identity preserves upstream $format fallback"
		);
		remove_filter( 'translation_file_format', $format_filter );
	}

	unlink( $upstream_base . '.l10n.php' );
	unlink( $upstream_base . '.mo' );
	reset_domain( 'gravityforms' );
	production_check(
		__( 'The URL is not valid.', 'gravityforms' ) === 'The URL is not valid.',
		'Gravity Forms non-admitted identity falls through provider to source English when upstream is absent'
	);

	echo "PRODUCTION PROVIDER TOTAL: $production_checks checks passed.\n";
} finally {
	cleanup( WP_CONTENT_DIR );
}
