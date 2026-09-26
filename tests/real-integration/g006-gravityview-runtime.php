<?php
/**
 * G-006 GravityView full-authority runtime assertions.
 *
 * Runs inside disposable real WordPress + exact Gravity Forms/GravityView runtime.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'G006_GV_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'G006_GV_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );

$expected_pgr_version = getenv( 'G006_PGR_EXPECTED_VERSION' );
if ( ! is_string( $expected_pgr_version ) || 1 !== preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', $expected_pgr_version ) ) {
	throw new RuntimeException( 'G006_PGR_EXPECTED_VERSION must be a stable SemVer resolved from repository release authority.' );
}

function g006_gv_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function g006_gv_plugin_version( $relative_main_file ) {
	$path = WP_PLUGIN_DIR . '/' . $relative_main_file;
	g006_gv_assert( is_readable( $path ), 'Missing plugin main file: ' . $relative_main_file );
	$data = get_file_data( $path, array( 'Version' => 'Version' ) );
	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

function g006_gv_reset_domain( $domain ) {
	WP_Translation_Controller::get_instance()->unload_textdomain( $domain );
	unset( $GLOBALS['l10n'][ $domain ], $GLOBALS['l10n_unloaded'][ $domain ] );
	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
}

function g006_gv_runtime_values( $runtime_key, array $expected, array $plural_by_key ) {
	$separator = strpos( $runtime_key, "\x04" );
	if ( false === $separator ) {
		$context  = null;
		$original = $runtime_key;
	} else {
		$context  = substr( $runtime_key, 0, $separator );
		$original = substr( $runtime_key, $separator + 1 );
	}
	$domain = 'gk-gravityview';

	if ( 1 === count( $expected ) ) {
		return array(
			null === $context
				? __( $original, $domain )
				: _x( $original, $context, $domain ),
		);
	}

	g006_gv_assert( 2 === count( $expected ), 'Unexpected GravityView runtime plural-form count.' );
	$plural = $plural_by_key[ $runtime_key ] ?? null;
	g006_gv_assert( is_string( $plural ) && '' !== $plural, 'Missing source plural for GravityView runtime key.' );

	return array(
		null === $context
			? _n( $original, $plural, 1, $domain )
			: _nx( $original, $plural, 1, $context, $domain ),
		null === $context
			? _n( $original, $plural, 2, $domain )
			: _nx( $original, $plural, 2, $context, $domain ),
	);
}

function g006_gv_controller_values( $runtime_key, array $expected, array $plural_by_key ) {
	$separator = strpos( $runtime_key, "\x04" );
	if ( false === $separator ) {
		$context  = '';
		$original = $runtime_key;
	} else {
		$context  = substr( $runtime_key, 0, $separator );
		$original = substr( $runtime_key, $separator + 1 );
	}
	$controller = WP_Translation_Controller::get_instance();
	$domain     = 'gk-gravityview';

	if ( 1 === count( $expected ) ) {
		$value = $controller->translate( $original, $context, $domain, 'fa_IR' );
		return array( false === $value ? $original : $value );
	}

	$plural = $plural_by_key[ $runtime_key ] ?? null;
	g006_gv_assert( is_string( $plural ) && '' !== $plural, 'Missing source plural for GravityView controller probe.' );
	$one = $controller->translate_plural( array( $original, $plural ), 1, $context, $domain, 'fa_IR' );
	$two = $controller->translate_plural( array( $original, $plural ), 2, $context, $domain, 'fa_IR' );
	return array(
		false === $one ? $original : $one,
		false === $two ? $plural : $two,
	);
}

g006_gv_assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
g006_gv_assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
g006_gv_assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
g006_gv_assert( post_type_exists( 'gravityview' ), 'GravityView post type is unavailable.' );
g006_gv_assert( class_exists( 'GVCommon' ), 'GravityView runtime API is unavailable.' );

$versions = array(
	'gravityforms'   => g006_gv_plugin_version( 'gravityforms/gravityforms.php' ),
	'gravityview'    => g006_gv_plugin_version( 'gravityview/gravityview.php' ),
	'persiangravity' => g006_gv_plugin_version( 'persian-gravityforms/persian-gravityforms.php' ),
);
g006_gv_assert( '3.1.1.1' === $versions['gravityforms'], 'Gravity Forms runtime version mismatch.' );
g006_gv_assert( '3.3.4' === $versions['gravityview'], 'GravityView runtime version mismatch.' );
g006_gv_assert( $expected_pgr_version === $versions['persiangravity'], 'PersianGravity runtime version mismatch.' );

$pgr_root = WP_PLUGIN_DIR . '/persian-gravityforms';
$provider_php = $pgr_root . '/languages/providers/gravityview/gravityview-fa_IR.l10n.php';
$remainder_evidence_path = $pgr_root . '/tools/i18n/admission/remainders/gravityview.json';
g006_gv_assert( is_readable( $provider_php ), 'Generated GravityView PHP provider is unavailable.' );
g006_gv_assert( is_readable( $remainder_evidence_path ), 'GravityView remainder evidence is unavailable.' );

$provider = require $provider_php;
g006_gv_assert( is_array( $provider ), 'Generated GravityView PHP provider must return an array.' );
g006_gv_assert( 'fa_IR' === ( $provider['language'] ?? null ), 'GravityView provider locale mismatch.' );
g006_gv_assert( 'nplurals=2; plural=(n > 1);' === ( $provider['plural-forms'] ?? null ), 'GravityView provider plural rule mismatch.' );
$messages = $provider['messages'] ?? null;
g006_gv_assert( is_array( $messages ), 'Generated GravityView PHP provider messages are unavailable.' );

$remainder_evidence = json_decode(
	file_get_contents( $remainder_evidence_path ),
	true,
	512,
	JSON_THROW_ON_ERROR
);
g006_gv_assert( 3127 === $remainder_evidence['canonical_message_count'], 'GravityView source-authority census changed.' );
g006_gv_assert( 2666 === $remainder_evidence['remainder_message_count'], 'GravityView remainder census changed.' );
g006_gv_assert( 2665 === $remainder_evidence['runtime_projection']['runtime_entry_count'], 'GravityView remainder runtime projection count changed.' );
g006_gv_assert( 1 === count( $remainder_evidence['runtime_projection']['source_aliases'] ), 'GravityView runtime projection alias census changed.' );

$plural_by_key = array();
foreach ( $remainder_evidence['entries'] as $source_entry ) {
	$plural = $source_entry['msgid_plural'] ?? null;
	if ( null === $plural ) {
		continue;
	}
	$context = $source_entry['msgctxt'] ?? null;
	$key = ( is_string( $context ) && '' !== $context ? $context . "\x04" : '' ) . $source_entry['msgid'];
	g006_gv_assert( ! isset( $plural_by_key[ $key ] ), 'Duplicate GravityView plural runtime key in source evidence.' );
	$plural_by_key[ $key ] = $plural;
}
g006_gv_assert( 42 === count( $plural_by_key ), 'GravityView source plural census mismatch.' );

$canonical_messages = array();
$plural_alias_count = 0;
foreach ( $messages as $runtime_key => $encoded_translation ) {
	g006_gv_assert( is_string( $runtime_key ) || is_int( $runtime_key ), 'Invalid GravityView runtime provider key type.' );
	$runtime_key = (string) $runtime_key;
	g006_gv_assert( '' !== $runtime_key, 'Invalid empty GravityView runtime provider key.' );
	g006_gv_assert( is_string( $encoded_translation ) && '' !== $encoded_translation, 'Empty GravityView runtime provider translation.' );

	$plural_separator = strpos( $runtime_key, "\0" );
	if ( false === $plural_separator ) {
		$canonical_messages[ $runtime_key ] = $encoded_translation;
		continue;
	}

	++$plural_alias_count;
	$base_key = substr( $runtime_key, 0, $plural_separator );
	$plural   = substr( $runtime_key, $plural_separator + 1 );
	g006_gv_assert( isset( $messages[ $base_key ] ), 'GravityView plural lookup alias has no canonical provider entry.' );
	g006_gv_assert( $messages[ $base_key ] === $encoded_translation, 'GravityView plural lookup alias translation drift.' );
	g006_gv_assert( ( $plural_by_key[ $base_key ] ?? null ) === $plural, 'GravityView plural lookup alias is not source-evidenced.' );
}
g006_gv_assert( 42 === $plural_alias_count, 'GravityView PHP plural lookup-alias census mismatch: ' . $plural_alias_count );
g006_gv_assert( 3126 === count( $canonical_messages ), 'GravityView canonical runtime-provider entry count mismatch.' );

$ids = array();
$runtime_mismatches = array();
$context_count = 0;
$plural_count = 0;
$tested_count = 0;

foreach ( $canonical_messages as $runtime_key => $encoded_translation ) {
	$separator = strpos( $runtime_key, "\x04" );
	if ( false === $separator ) {
		$context  = null;
		$original = $runtime_key;
	} else {
		++$context_count;
		$context  = substr( $runtime_key, 0, $separator );
		$original = substr( $runtime_key, $separator + 1 );
	}
	$expected = explode( "\0", $encoded_translation );
	$plural   = null;
	if ( 1 < count( $expected ) ) {
		++$plural_count;
		g006_gv_assert( 2 === count( $expected ), 'Incomplete GravityView plural translation.' );
		$plural = $plural_by_key[ $runtime_key ] ?? null;
		g006_gv_assert( is_string( $plural ) && '' !== $plural, 'Plural GravityView provider key is not source-evidenced.' );
		g006_gv_assert(
			isset( $messages[ $runtime_key . "\0" . $plural ] ) &&
			$messages[ $runtime_key . "\0" . $plural ] === $encoded_translation,
			'Missing exact GravityView PHP plural lookup alias.'
		);
	}
	foreach ( $expected as $value ) {
		g006_gv_assert( '' !== trim( $value ), 'Empty GravityView translation reached runtime qualification.' );
	}

	$id = hash(
		'sha256',
		(string) $context . "\x1f" .
		$original . "\x1f" .
		(string) $plural
	);
	$ids[] = $id;
	$actual = g006_gv_runtime_values( $runtime_key, $expected, $plural_by_key );
	if ( $actual !== $expected ) {
		$runtime_mismatches[] = array(
			'identity'   => $id,
			'msgid'      => $original,
			'expected'   => $expected,
			'controller' => g006_gv_controller_values( $runtime_key, $expected, $plural_by_key ),
			'actual'     => $actual,
			'filters'    => array(
				'ngettext'                => has_filter( 'ngettext' ),
				'ngettext_gk-gravityview' => has_filter( 'ngettext_gk-gravityview' ),
			),
		);
		if ( 10 <= count( $runtime_mismatches ) ) {
			break;
		}
	}
	++$tested_count;
}

sort( $ids, SORT_STRING );
g006_gv_assert( array() === $runtime_mismatches, 'GravityView provider/runtime mismatch: ' . wp_json_encode( $runtime_mismatches ) );
g006_gv_assert( 3126 === $tested_count, 'GravityView gettext runtime-entry count mismatch: ' . $tested_count );
g006_gv_assert( 127 === $context_count, 'GravityView context-count mismatch: ' . $context_count );
g006_gv_assert( 42 === $plural_count, 'GravityView plural-count mismatch: ' . $plural_count );
g006_gv_assert(
	'bebc8421688876f3d555246e0c1d8fb43abc7f3765065af3f830d7bc194e77d9' === hash( 'sha256', implode( "\n", $ids ) . "\n" ),
	'GravityView gettext runtime-provider keyset fingerprint mismatch.'
);

g006_gv_assert( '+[count] عملیات' === __( '+[count] action', 'gk-gravityview' ), 'Projected singular gettext lookup failed.' );
g006_gv_assert( '+[count] عملیات' === _n( '+[count] action', '+[count] actions', 2, 'gk-gravityview' ), 'Projected plural gettext lookup failed.' );

// Preserve a few historical accepted mappings while proving a formerly-residual identity is now provided.
$historical = array(
	'settings'      => __( 'Settings', 'gk-gravityview' ),
	'view_settings' => __( 'View Settings', 'gk-gravityview' ),
	'add_field'     => __( 'Add Field', 'gk-gravityview' ),
);
g006_gv_assert( 'تنظیمات' === $historical['settings'], 'Historical GravityView Settings translation regressed.' );
g006_gv_assert( 'تنظیمات نما' === $historical['view_settings'], 'Historical GravityView View Settings translation regressed.' );
g006_gv_assert( 'افزودن فیلد' === $historical['add_field'], 'Historical GravityView Add Field translation regressed.' );
$former_residual = __( 'API Key', 'gk-gravityview' );
g006_gv_assert( 'API Key' !== $former_residual, 'Former GravityView residual identity remained source English.' );

// Provider precedence and healthy fallback for a truly out-of-census identity.
$upstream_dir = $artifact_dir . '/synthetic-upstream-gravityview';
wp_mkdir_p( $upstream_dir );
file_put_contents(
	$upstream_dir . '/gravityview-fa_IR.l10n.php',
	<<<'PHPFIXTURE'
<?php
return array(
    'content-type' => 'text/plain; charset=UTF-8',
    'language' => 'fa_IR',
    'plural-forms' => 'nplurals=2; plural=(n > 1);',
    'project-id-version' => 'G006 synthetic GravityView upstream fallback fixture',
    'x-domain' => 'gk-gravityview',
    'messages' => array(
        'Settings' => 'UPSTREAM_COLLISION_MUST_NOT_WIN',
        'PersianGravity GravityView out-of-census fallback probe.' => 'G006_GV_UPSTREAM_FALLBACK_PASS',
    ),
);
PHPFIXTURE
	. "\n"
);
g006_gv_reset_domain( 'gk-gravityview' );
$GLOBALS['wp_textdomain_registry']->set_custom_path( 'gk-gravityview', $upstream_dir );
$fallback = array(
	'provider_collision' => __( 'Settings', 'gk-gravityview' ),
	'upstream_only'      => __( 'PersianGravity GravityView out-of-census fallback probe.', 'gk-gravityview' ),
);
g006_gv_assert( 'تنظیمات' === $fallback['provider_collision'], 'GravityView provider precedence over upstream regressed.' );
g006_gv_assert( 'G006_GV_UPSTREAM_FALLBACK_PASS' === $fallback['upstream_only'], 'GravityView out-of-census upstream fallback regressed.' );

unlink( $upstream_dir . '/gravityview-fa_IR.l10n.php' );
g006_gv_reset_domain( 'gk-gravityview' );
g006_gv_assert(
	'PersianGravity GravityView out-of-census fallback probe.' === __( 'PersianGravity GravityView out-of-census fallback probe.', 'gk-gravityview' ),
	'GravityView out-of-census source-English fallback regressed.'
);

$products = require $pgr_root . '/includes/localization/products.php';
g006_gv_assert( array() === $products['gk-gravityview']['scripts'], 'GravityView native JS authority was unexpectedly activated.' );
g006_gv_assert( array() === ( glob( $pgr_root . '/languages/providers/gravityview/gk-gravityview-fa_IR-*.json' ) ?: array() ), 'Unexpected GravityView provider JSON catalog exists.' );
g006_gv_assert( array() === ( glob( $pgr_root . '/languages/providers/gravityview/gravityview-fa_IR-*.json' ) ?: array() ), 'Unexpected GravityView prefixed provider JSON catalog exists.' );

$registry = require $pgr_root . '/includes/localization/registry.php';
$overlay = new PGR_Localization( $registry, $pgr_root . '/languages/providers' );
foreach ( array( 'gk-query-filters', 'action-scheduler' ) as $unmanaged_domain ) {
	g006_gv_assert( false === $overlay->discover( false, $unmanaged_domain, 'fa_IR' ), $unmanaged_domain . ' unexpectedly entered provider discovery authority.' );
	g006_gv_assert(
		'/upstream/' . $unmanaged_domain . '.mo' === $overlay->php_file( '/upstream/' . $unmanaged_domain . '.mo', $unmanaged_domain, 'fa_IR' ),
		$unmanaged_domain . ' unexpectedly entered provider PHP-file authority.'
	);
}

// Build a real GravityView frontend fixture for representative browser verification.
$form_id = GFAPI::add_form(
	array(
		'title'  => 'G006 GravityView Runtime',
		'fields' => array(
			array(
				'id'    => 1,
				'label' => 'Fixture Token',
				'type'  => 'text',
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'Submit' ),
	)
);
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}
$form_id = (int) $form_id;

$fixture_token = 'G006-GRAVITYVIEW-FULL-AUTHORITY-' . $form_id;
$entry_id = GFAPI::add_entry(
	array(
		'form_id' => $form_id,
		'status'  => 'active',
		'1'       => $fixture_token,
	)
);
if ( is_wp_error( $entry_id ) ) {
	throw new RuntimeException( $entry_id->get_error_message() );
}
$entry_id = (int) $entry_id;
if ( class_exists( 'GravityView_Entry_Approval' ) ) {
	GravityView_Entry_Approval::update_approved( $entry_id, 1, $form_id );
}

$view_id = wp_insert_post(
	array(
		'post_type'   => 'gravityview',
		'post_status' => 'publish',
		'post_title'  => 'G006 GravityView Full Authority',
	),
	true
);
if ( is_wp_error( $view_id ) ) {
	throw new RuntimeException( $view_id->get_error_message() );
}
$view_id = (int) $view_id;

$field_base = array(
	'show_label'        => '1',
	'custom_label'      => '',
	'custom_class'      => '',
	'show_as_link'      => '0',
	'search_filter'     => '0',
	'only_loggedin'     => '0',
	'only_loggedin_cap' => 'read',
);
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta(
	$view_id,
	'_gravityview_template_settings',
	array(
		'page_size'      => '25',
		'sort_columns'   => '1',
		'sort_field'     => 'date_created',
		'sort_direction' => 'ASC',
	)
);
update_post_meta(
	$view_id,
	'_gravityview_directory_fields',
	array(
		'directory_table-columns' => array(
			'g006_token' => array_merge(
				$field_base,
				array(
					'id'    => '1',
					'label' => 'Fixture Token',
				)
			),
		),
	)
);

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'G006 GravityView Full Authority',
		'post_name'    => 'g006-gravityview-full-authority',
		'post_content' => sprintf( '[gravityview id="%d"]', $view_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}
$page_id = (int) $page_id;

$manifest = array(
	'schema_version'                  => 1,
	'evidence_class'                  => 'G006_GRAVITYVIEW_EXACT_LICENSED_RUNTIME',
	'locale'                          => get_locale(),
	'expected_persiangravity_version' => $expected_pgr_version,
	'versions'                        => $versions,
	'canonical_message_count'         => $tested_count,
	'canonical_keyset_sha256'         => hash( 'sha256', implode( "\n", $ids ) . "\n" ),
	'context_count'                   => $context_count,
	'plural_count'                    => $plural_count,
	'runtime_mismatch_count'          => count( $runtime_mismatches ),
	'historical'                      => $historical,
	'former_residual'                 => $former_residual,
	'fallback'                        => $fallback,
	'native_js_handles'               => count( $products['gk-gravityview']['scripts'] ),
	'provider_json_count'             => count( glob( $pgr_root . '/languages/providers/gravityview/*-fa_IR-*.json' ) ?: array() ),
	'form_id'                         => $form_id,
	'entry_id'                        => $entry_id,
	'view_id'                         => $view_id,
	'page_id'                         => $page_id,
	'page_url'                        => get_permalink( $page_id ),
	'admin_edit_url'                  => admin_url( 'post.php?post=' . $view_id . '&action=edit' ),
	'fixture_token'                   => $fixture_token,
);
file_put_contents(
	$artifact_dir . '/g006-gravityview-runtime.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
