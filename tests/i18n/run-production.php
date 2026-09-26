<?php
/** Real WordPress Core contract for the committed bounded product providers. */
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
	$root          = dirname( __DIR__, 2 );
	$provider_root = WP_CONTENT_DIR . '/providers';
	$products      = require $root . '/includes/localization/registry.php';
	$cases         = array(
		'gravityforms' => array(
			'product'       => 'gravityforms',
			'prefix'        => 'gravityforms',
			'admitted'      => 'Validation Message Placement',
			'translation'   => 'پیام اعتبارسنجی',
			'fallback_probe' => 'PersianGravity Gravity Forms out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی گرویتی فرمز',
		),
		'gravityflow' => array(
			'product'       => 'gravityflow',
			'prefix'        => 'gravityflow',
			'admitted'      => 'Workflow Inbox',
			'translation'   => 'کارهای من',
			'fallback_probe' => 'PersianGravity Gravity Flow out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی گرویتی فلو',
		),
		'gk-gravityview' => array(
			'product'       => 'gravityview',
			'prefix'        => 'gravityview',
			'admitted'      => 'This View is in the Trash. %1$sClick to restore the View%2$s.',
			'translation'   => 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.',
			'fallback_probe' => 'PersianGravity GravityView out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی گرویتی ویو',
		),
		'gravityperks' => array(
			'product'       => 'gravityperks',
			'prefix'        => 'gravityperks',
			'admitted'      => 'Manage Perks',
			'translation'   => 'مدیریت پرک‌ها',
			'fallback_probe' => 'PersianGravity Gravity Perks out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی Gravity Perks',
		),
		'gp-file-upload-pro' => array(
			'product'       => 'gp-file-upload-pro',
			'prefix'        => 'gp-file-upload-pro',
			'admitted'      => 'select files',
			'translation'   => 'انتخاب فایل‌ها',
			'fallback_probe' => 'PersianGravity GP File Upload Pro out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی File Upload Pro',
		),
		'gp-advanced-select' => array(
			'product'       => 'gp-advanced-select',
			'prefix'        => 'gp-advanced-select',
			'admitted'      => 'No results found',
			'translation'   => 'نتیجه‌ای یافت نشد',
			'fallback_probe' => 'PersianGravity GP Advanced Select out-of-census fallback probe.',
			'fallback_scope' => 'out-of-census identity',
			'upstream_only' => 'کنترل بالادستی Advanced Select',
		),
	);

	foreach ( $cases as $domain => &$case ) {
		$provider_dir = $provider_root . '/' . $case['product'];
		$upstream_dir = WP_CONTENT_DIR . '/upstream-production-' . $case['product'];
		if ( ! is_dir( $provider_dir ) ) {
			mkdir( $provider_dir, 0777, true );
		}
		if ( ! is_dir( $upstream_dir ) ) {
			mkdir( $upstream_dir, 0777, true );
		}

		$provider_base = $provider_dir . '/' . $case['prefix'] . '-fa_IR';
		$repo_base     = $root . '/languages/providers/' . $case['product'] . '/' . $case['prefix'] . '-fa_IR';
		copy( $repo_base . '.mo', $provider_base . '.mo' );
		copy( $repo_base . '.l10n.php', $provider_base . '.l10n.php' );

		$case['upstream_dir']  = $upstream_dir;
		$case['upstream_base'] = $upstream_dir . '/' . $case['prefix'] . '-fa_IR';
		write_fixture(
			$case['upstream_base'],
			$domain,
			array( $case['fallback_probe'] => $case['upstream_only'] )
		);
	}
	unset( $case );

	foreach ( $cases as $domain => $case ) {
		foreach ( array( 'php', 'mo' ) as $format ) {
			reset_domain( $domain );
			$format_filter = static fn() => $format;
			add_filter( 'translation_file_format', $format_filter );
			$GLOBALS['wp_textdomain_registry']->set_custom_path( $domain, $case['upstream_dir'] );

			production_check(
				__( $case['admitted'], $domain ) === $case['translation'],
				$case['product'] . " admitted identity resolves from committed PersianGravity $format provider"
			);
			production_check(
				__( $case['fallback_probe'], $domain ) === $case['upstream_only'],
				$case['product'] . ' ' . $case['fallback_scope'] . " preserves upstream $format fallback"
			);
			remove_filter( 'translation_file_format', $format_filter );
		}
	}

	foreach ( $cases as $source_domain => $source_case ) {
		foreach ( $cases as $target_domain => $target_case ) {
			if ( $source_domain === $target_domain ) {
				continue;
			}
			reset_domain( $target_domain );
			$GLOBALS['wp_textdomain_registry']->set_custom_path( $target_domain, $target_case['upstream_dir'] );
			production_check(
				__( $source_case['admitted'], $target_domain ) === $source_case['admitted'],
				$source_case['product'] . ' provider content does not leak into ' . $target_case['product'] . ' domain'
			);
		}
	}

	foreach ( $cases as $domain => $case ) {
		unlink( $case['upstream_base'] . '.l10n.php' );
		unlink( $case['upstream_base'] . '.mo' );
		reset_domain( $domain );
		production_check(
			__( $case['fallback_probe'], $domain ) === $case['fallback_probe'],
			$case['product'] . ' ' . $case['fallback_scope'] . ' falls through provider to source English when upstream is absent'
		);
	}

	$overlay = new PGR_Localization( $products, $provider_root );
	foreach ( array( 'gk-query-filters', 'action-scheduler', 'gravity-perks', 'gp-advanced-phone-field', 'gp-populate-anything' ) as $domain ) {
		production_check(
			false === $overlay->discover( false, $domain, 'fa_IR' ),
			$domain . ' remains outside provider discovery authority'
		);
		production_check(
			'/upstream/' . $domain . '.mo' === $overlay->php_file( '/upstream/' . $domain . '.mo', $domain, 'fa_IR' ),
			$domain . ' remains an unmanaged PHP translation domain'
		);
	}

	echo "PRODUCTION PROVIDER TOTAL: $production_checks checks passed across all six admitted product domains.\n";
} finally {
	cleanup( WP_CONTENT_DIR );
}
