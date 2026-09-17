<?php
/**
 * G-006 Gravity Forms full-authority runtime assertions.
 *
 * Runs inside a disposable real WordPress + exact Gravity Forms/Gravity Flow runtime.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'G006_GF_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'G006_GF_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );

function g006_gf_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function g006_gf_plugin_version( $relative_main_file ) {
	$path = WP_PLUGIN_DIR . '/' . $relative_main_file;
	g006_gf_assert( is_readable( $path ), 'Missing plugin main file: ' . $relative_main_file );
	$data = get_file_data( $path, array( 'Version' => 'Version' ) );
	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

function g006_gf_reset_domain( $domain ) {
	WP_Translation_Controller::get_instance()->unload_textdomain( $domain );
	unset( $GLOBALS['l10n'][ $domain ], $GLOBALS['l10n_unloaded'][ $domain ] );
	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
}

g006_gf_assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
g006_gf_assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
g006_gf_assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
g006_gf_assert( class_exists( 'GF_Fields' ), 'Gravity Forms field registry is unavailable.' );
g006_gf_assert( class_exists( 'Gravity_Flow_API' ), 'Gravity Flow runtime API is unavailable.' );

$versions = array(
	'gravityforms'   => g006_gf_plugin_version( 'gravityforms/gravityforms.php' ),
	'gravityflow'    => g006_gf_plugin_version( 'gravityflow/gravityflow.php' ),
	'persiangravity' => g006_gf_plugin_version( 'persian-gravityforms/persian-gravityforms.php' ),
);
g006_gf_assert( '3.1.1.1' === $versions['gravityforms'], 'Gravity Forms runtime version mismatch.' );
g006_gf_assert( '3.1.0' === $versions['gravityflow'], 'Gravity Flow runtime version mismatch.' );
g006_gf_assert( '4.4.0' === $versions['persiangravity'], 'PersianGravity runtime version mismatch.' );

// Newly admitted G-006 identity through the real Gravity Forms field implementation.
$text_field = GF_Fields::get( 'text' );
g006_gf_assert( $text_field instanceof GF_Field_Text, 'Gravity Forms text field implementation is unavailable.' );
$new_identity = $text_field->get_form_editor_field_title();
g006_gf_assert( 'متن تک خطی' === $new_identity, 'Newly admitted Gravity Forms identity did not resolve through the field runtime.' );

// Exercise another newly admitted string on an actual validation path.
$validation_field = new GF_Field_Text();
$validation_field->id = 1;
$validation_field->maxLength = 3;
$validation_field->validate( 'چهار', array( 'id' => 1 ) );
g006_gf_assert( true === $validation_field->failed_validation, 'Gravity Forms text validation path did not fail as expected.' );
g006_gf_assert(
	'متن واردشده از حداکثر تعداد نویسه‌ها بیشتر است.' === $validation_field->validation_message,
	'Newly admitted Gravity Forms validation translation did not resolve.'
);

// Existing accepted authority must remain stable.
$existing = array(
	'submission_error' => __( 'There was a problem with your submission.', 'gravityforms' ),
	'required'         => __( 'This field is required.', 'gravityforms' ),
	'submit'           => __( 'Submit', 'gravityforms' ),
);
g006_gf_assert( 'مشکلی با این ارسال پیش آمده است.' === $existing['submission_error'], 'Existing Gravity Forms submission translation regressed.' );
g006_gf_assert( 'این فیلد ضروری است.' === $existing['required'], 'Existing Gravity Forms required-field translation regressed.' );
g006_gf_assert( 'ارسال' === $existing['submit'], 'Existing Gravity Forms submit translation regressed.' );

// Gravity Flow remains fully authoritative after the shared validator extension.
$flow = __( 'No Pending Tasks', 'gravityflow' );
g006_gf_assert( 'کاری در انتظار نیست' === $flow, 'Gravity Flow provider translation regressed.' );

// Provider precedence + healthy fallback for a truly out-of-census identity.
$upstream_dir = $artifact_dir . '/synthetic-upstream-gravityforms';
wp_mkdir_p( $upstream_dir );
file_put_contents(
	$upstream_dir . '/gravityforms-fa_IR.l10n.php',
	<<<'PHPFIXTURE'
<?php
return array(
    'content-type' => 'text/plain; charset=UTF-8',
    'language' => 'fa_IR',
    'plural-forms' => 'nplurals=2; plural=(n > 1);',
    'project-id-version' => 'G006 synthetic upstream fallback fixture',
    'x-domain' => 'gravityforms',
    'messages' => array(
        'There was a problem with your submission.' => 'UPSTREAM_COLLISION_MUST_NOT_WIN',
        'PersianGravity Gravity Forms out-of-census fallback probe.' => 'G006_UPSTREAM_FALLBACK_PASS',
    ),
);
PHPFIXTURE
	. "\n"
);
g006_gf_reset_domain( 'gravityforms' );
$GLOBALS['wp_textdomain_registry']->set_custom_path( 'gravityforms', $upstream_dir );
$fallback = array(
	'provider_collision' => __( 'There was a problem with your submission.', 'gravityforms' ),
	'upstream_only'      => __( 'PersianGravity Gravity Forms out-of-census fallback probe.', 'gravityforms' ),
	'stale_pot_only'     => __( "I'm a Button!", 'gravityforms' ),
);
g006_gf_assert( 'مشکلی با این ارسال پیش آمده است.' === $fallback['provider_collision'], 'Provider precedence over upstream regressed.' );
g006_gf_assert( 'G006_UPSTREAM_FALLBACK_PASS' === $fallback['upstream_only'], 'Out-of-census upstream fallback did not survive the full provider overlay.' );
g006_gf_assert( "I'm a Button!" === $fallback['stale_pot_only'], 'Known stale POT-only key became authoritative.' );

// Create and render a real form to prove the exact Gravity Forms runtime remains usable.
$form_id = GFAPI::add_form(
	array(
		'title'  => 'G006 Gravity Forms Runtime',
		'fields' => array(
			array(
				'id'         => 1,
				'label'      => 'نام',
				'type'       => 'text',
				'isRequired' => true,
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'Submit' ),
	)
);
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}
ob_start();
gravity_form( (int) $form_id, false, false, false, null, false, 0, true );
$form_html = (string) ob_get_clean();
g006_gf_assert( false !== strpos( $form_html, 'gform_wrapper' ), 'Gravity Forms frontend form did not render.' );

$pgr_root = WP_PLUGIN_DIR . '/persian-gravityforms';
$products = require $pgr_root . '/includes/localization/products.php';
g006_gf_assert( array() === $products['gravityforms']['scripts'], 'Gravity Forms native JS authority was unexpectedly activated.' );
g006_gf_assert( array() === $products['gravityflow']['scripts'], 'Gravity Flow native JS authority was unexpectedly activated.' );
g006_gf_assert( array() === ( glob( $pgr_root . '/languages/providers/gravityforms/gravityforms-fa_IR-*.json' ) ?: array() ), 'Unexpected Gravity Forms provider JSON catalog exists.' );

$manifest = array(
	'schema_version'  => 1,
	'evidence_class'  => 'G006_GF_EXACT_LICENSED_RUNTIME',
	'locale'          => get_locale(),
	'versions'        => $versions,
	'new_identity'    => $new_identity,
	'existing'        => $existing,
	'flow'            => $flow,
	'fallback'        => $fallback,
	'form_id'         => (int) $form_id,
	'form_rendered'   => true,
	'gf_native_js'    => 0,
	'gf_provider_json'=> 0,
);
file_put_contents(
	$artifact_dir . '/g006-gravityforms-runtime.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
