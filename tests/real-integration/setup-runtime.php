<?php
/**
 * Deterministic setup and runtime assertions for WU-008 licensed integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'WU008_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );

function wu008_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function wu008_plugin_version( $relative_main_file ) {
	$path = WP_PLUGIN_DIR . '/' . $relative_main_file;
	wu008_assert( is_readable( $path ), 'Missing plugin main file: ' . $relative_main_file );
	$data = get_file_data( $path, array( 'Version' => 'Version' ) );
	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

/**
 * Reset translation and registry state without Core's explicit-unload sentinel.
 *
 * This mirrors the repository's WordPress Core contract tests so the next
 * gettext call exercises the real JIT registry path rather than a direct
 * load_textdomain() shortcut.
 */
function wu008_reset_domain( $domain ) {
	WP_Translation_Controller::get_instance()->unload_textdomain( $domain );
	unset( $GLOBALS['l10n'][ $domain ], $GLOBALS['l10n_unloaded'][ $domain ] );
	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
}

wu008_assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
wu008_assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
wu008_assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
wu008_assert( class_exists( 'Gravity_Flow_API' ), 'Gravity Flow runtime API is unavailable.' );

$expected_pgr_version = getenv( 'WU008_PGR_VERSION' );
wu008_assert( is_string( $expected_pgr_version ) && '' !== $expected_pgr_version, 'WU008_PGR_VERSION is required.' );

$versions = array(
	'gravityforms'   => wu008_plugin_version( 'gravityforms/gravityforms.php' ),
	'gravityflow'    => wu008_plugin_version( 'gravityflow/gravityflow.php' ),
	'gravityview'    => wu008_plugin_version( 'gravityview/gravityview.php' ),
	'persiangravity' => wu008_plugin_version( 'persian-gravityforms/persian-gravityforms.php' ),
);
wu008_assert( '3.1.1.1' === $versions['gravityforms'], 'Gravity Forms runtime version mismatch.' );
wu008_assert( '3.1.0' === $versions['gravityflow'], 'Gravity Flow runtime version mismatch.' );
wu008_assert( '3.3.4' === $versions['gravityview'], 'GravityView runtime version mismatch.' );
wu008_assert( $expected_pgr_version === $versions['persiangravity'], 'PersianGravity current-Head runtime version mismatch.' );

// Exercise the actual product domains after all exact products are active.
$gf_key   = 'There was a problem with your submission.';
$flow_key = 'No Pending Tasks';
$view_key = 'This View is in the Trash. %1$sClick to restore the View%2$s.';

$provider = array(
	'gravityforms' => __( $gf_key, 'gravityforms' ),
	'gravityflow'  => __( $flow_key, 'gravityflow' ),
	'gravityview'  => __( $view_key, 'gk-gravityview' ),
);
wu008_assert( 'مشکلی با این ارسال پیش آمده است.' === $provider['gravityforms'], 'Gravity Forms provider translation did not resolve.' );
wu008_assert( 'کاری در انتظار نیست' === $provider['gravityflow'], 'Gravity Flow provider translation did not resolve.' );
wu008_assert( 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.' === $provider['gravityview'], 'GravityView provider runtime evidence mismatch.' );

// Exercise provider-over-upstream precedence and upstream-only fallback through
// WordPress' real JIT registry path without changing any licensed vendor package.
$upstream_dir = $artifact_dir . '/upstream-gravityforms';
wp_mkdir_p( $upstream_dir );
$upstream_fixture = $upstream_dir . '/gravityforms-fa_IR.l10n.php';
$fixture_php = <<<'PHP'
<?php
return array(
    'content-type' => 'text/plain; charset=UTF-8',
    'language' => 'fa_IR',
    'plural-forms' => 'nplurals=2; plural=(n > 1);',
    'project-id-version' => 'WU008 synthetic upstream fallback fixture',
    'x-domain' => 'gravityforms',
    'messages' => array(
        'There was a problem with your submission.' => 'UPSTREAM_COLLISION_MUST_NOT_WIN',
        'WU008 upstream-only fallback sentinel' => 'WU008_UPSTREAM_ONLY_PASS',
    ),
);
PHP;
file_put_contents( $upstream_fixture, $fixture_php . "\n" );
wu008_reset_domain( 'gravityforms' );
$GLOBALS['wp_textdomain_registry']->set_custom_path( 'gravityforms', $upstream_dir );
wu008_assert(
	$upstream_dir . '/' === $GLOBALS['wp_textdomain_registry']->get( 'gravityforms', 'fa_IR' ),
	'Synthetic upstream registry path was not preserved.'
);
$fallback = array(
	'provider_collision' => __( $gf_key, 'gravityforms' ),
	'upstream_only'      => __( 'WU008 upstream-only fallback sentinel', 'gravityforms' ),
);
wu008_assert( 'مشکلی با این ارسال پیش آمده است.' === $fallback['provider_collision'], 'Provider did not retain precedence over upstream collision.' );
wu008_assert( 'WU008_UPSTREAM_ONLY_PASS' === $fallback['upstream_only'], 'Upstream-only fallback did not survive provider overlay.' );

$form = array(
	'title'       => 'WU008 Real Integration Form',
	'description' => 'Exact licensed runtime fixture.',
	'fields'      => array(
		array(
			'id'          => 1,
			'label'       => 'Required Runtime Field',
			'type'        => 'text',
			'isRequired'  => true,
			'description' => 'Leave empty to trigger authentic Gravity Forms validation.',
		),
	),
	'button' => array(
		'type' => 'text',
		'text' => 'Submit',
	),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WU008 Real Integration',
		'post_name'    => 'wu008-real-integration',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

$admin_url = admin_url();
$manifest = array(
	'schema_version' => '1.1.0',
	'evidence_class' => 'REAL_LICENSED_HEADLESS_BROWSER_RUNTIME',
	'locale'         => get_locale(),
	'rtl'            => is_rtl(),
	'versions'       => $versions,
	'provider'       => $provider,
	'fallback'       => $fallback,
	'form_id'        => (int) $form_id,
	'page_id'        => (int) $page_id,
	'page_url'       => add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) ),
	'login_url'      => wp_login_url( $admin_url ),
	'gravityflow_inbox_url' => admin_url( 'admin.php?page=gravityflow-inbox' ),
	'admin_url'      => $admin_url,
);
wu008_assert( true === $manifest['rtl'], 'WordPress fa_IR runtime is not RTL.' );

file_put_contents(
	$artifact_dir . '/runtime-manifest.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
