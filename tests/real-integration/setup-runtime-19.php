<?php
/**
 * Deterministic WU-008 runtime fixtures and authority materialization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
    throw new RuntimeException( 'WU008_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );

function wu008_19_assert( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function wu008_19_plugin_version( $relative_main_file ) {
    $path = WP_PLUGIN_DIR . '/' . $relative_main_file;
    wu008_19_assert( is_readable( $path ), 'Missing plugin main file: ' . $relative_main_file );
    $data = get_file_data( $path, array( 'Version' => 'Version' ) );
    return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

function wu008_19_parse_po( $path ) {
    require_once ABSPATH . WPINC . '/pomo/po.php';
    $po = new PO();
    wu008_19_assert( $po->import_from_file( $path ), 'Unable to parse PO authority: ' . $path );
    $entries = array();
    foreach ( $po->entries as $entry ) {
        if ( ! is_object( $entry ) || ! isset( $entry->singular ) || '' === (string) $entry->singular ) {
            continue;
        }
        $entries[] = array(
            'msgid' => (string) $entry->singular,
            'context' => isset( $entry->context ) ? (string) $entry->context : '',
            'translations' => array_values( array_map( 'strval', (array) $entry->translations ) ),
        );
    }
    return $entries;
}

function wu008_19_insert_page( $slug, $title, $content ) {
    $id = wp_insert_post( array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
    ), true );
    if ( is_wp_error( $id ) ) {
        throw new RuntimeException( $id->get_error_message() );
    }
    return (int) $id;
}

wu008_19_assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
wu008_19_assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
wu008_19_assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
wu008_19_assert( class_exists( 'Gravity_Flow_API' ), 'Gravity Flow runtime API is unavailable.' );
wu008_19_assert( defined( 'PGR_PATH' ), 'PersianGravity runtime path is unavailable.' );

$versions = array(
    'wordpress' => get_bloginfo( 'version' ),
    'php' => PHP_VERSION,
    'gravityforms' => wu008_19_plugin_version( 'gravityforms/gravityforms.php' ),
    'gravityflow' => wu008_19_plugin_version( 'gravityflow/gravityflow.php' ),
    'gravityview' => wu008_19_plugin_version( 'gravityview/gravityview.php' ),
    'persiangravity' => wu008_19_plugin_version( 'persian-gravityforms/persian-gravityforms.php' ),
);
wu008_19_assert( '6.8.3' === $versions['wordpress'], 'WordPress runtime version mismatch.' );
wu008_19_assert( '3.1.1.1' === $versions['gravityforms'], 'Gravity Forms runtime version mismatch.' );
wu008_19_assert( '3.1.0' === $versions['gravityflow'], 'Gravity Flow runtime version mismatch.' );
wu008_19_assert( '3.3.4' === $versions['gravityview'], 'GravityView runtime version mismatch.' );
wu008_19_assert( '4.2.0' === $versions['persiangravity'], 'PersianGravity runtime version mismatch.' );

$expected_sha = getenv( 'WU008_PGR_SHA' );
$actual_sha = '';
$git_command = 'git -C ' . escapeshellarg( rtrim( PGR_PATH, '/' ) ) . ' rev-parse HEAD 2>/dev/null';
exec( $git_command, $git_output, $git_status );
if ( 0 === $git_status && ! empty( $git_output ) ) {
    $actual_sha = trim( (string) $git_output[0] );
}
wu008_19_assert( is_string( $expected_sha ) && '' !== $expected_sha, 'WU008_PGR_SHA is required.' );
wu008_19_assert( $actual_sha === $expected_sha, 'Installed PersianGravity git SHA mismatch.' );

$gf_key = 'There was a problem with your submission.';
$flow_key = 'No Pending Tasks';
$view_key = 'This View is in the Trash. %1$sClick to restore the View%2$s.';
$provider = array(
    'gravityforms' => __( $gf_key, 'gravityforms' ),
    'gravityflow' => __( $flow_key, 'gravityflow' ),
    'gravityview' => __( $view_key, 'gk-gravityview' ),
);
wu008_19_assert( 'مشکلی با این ارسال پیش آمده است.' === $provider['gravityforms'], 'Gravity Forms provider translation did not resolve.' );
wu008_19_assert( 'کاری در انتظار نیست' === $provider['gravityflow'], 'Gravity Flow provider translation did not resolve.' );
wu008_19_assert( 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.' === $provider['gravityview'], 'GravityView provider translation did not resolve.' );

$upstream_dir = $artifact_dir . '/synthetic-upstream-gravityforms';
wp_mkdir_p( $upstream_dir );
file_put_contents( $upstream_dir . '/gravityforms-fa_IR.l10n.php', <<<'PHPFILE'
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
PHPFILE
);
WP_Translation_Controller::get_instance()->unload_textdomain( 'gravityforms' );
unset( $GLOBALS['l10n']['gravityforms'], $GLOBALS['l10n_unloaded']['gravityforms'] );
$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
$GLOBALS['wp_textdomain_registry']->set_custom_path( 'gravityforms', $upstream_dir );
$fallback = array(
    'provider_collision' => __( $gf_key, 'gravityforms' ),
    'upstream_only' => __( 'WU008 upstream-only fallback sentinel', 'gravityforms' ),
);
wu008_19_assert( 'مشکلی با این ارسال پیش آمده است.' === $fallback['provider_collision'], 'Provider precedence control failed.' );
wu008_19_assert( 'WU008_UPSTREAM_ONLY_PASS' === $fallback['upstream_only'], 'Upstream-only fallback control failed.' );

$form = array(
    'title' => 'WU008 Evidence Form',
    'description' => 'Deterministic licensed-runtime evidence fixture.',
    'fields' => array(
        array( 'id' => 1, 'label' => 'Required Runtime Field', 'type' => 'text', 'isRequired' => true ),
        array( 'id' => 2, 'label' => 'Runtime Email', 'type' => 'email' ),
        array( 'id' => 3, 'label' => 'Runtime Notes', 'type' => 'textarea' ),
        array( 'id' => 4, 'label' => 'Runtime Choice', 'type' => 'select', 'choices' => array( array( 'text' => 'One', 'value' => 'one' ), array( 'text' => 'Two', 'value' => 'two' ) ) ),
        array( 'id' => 5, 'label' => 'Runtime Date', 'type' => 'date', 'dateFormat' => 'ymd', 'dateType' => 'datepicker' ),
    ),
    'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) {
    throw new RuntimeException( $form_id->get_error_message() );
}
$form_id = (int) $form_id;

$entry_id = GFAPI::add_entry( array(
    'form_id' => $form_id,
    '1' => 'WU008 Entry',
    '2' => 'runtime@example.invalid',
    '3' => 'RTL mixed content https://example.invalid ID-42',
    '4' => 'one',
    '5' => '2026-09-14',
    'created_by' => 1,
) );
if ( is_wp_error( $entry_id ) ) {
    throw new RuntimeException( $entry_id->get_error_message() );
}
$entry_id = (int) $entry_id;
gform_update_meta( $entry_id, 'workflow_final_status', 'complete', $form_id );
gform_update_meta( $entry_id, 'workflow_step', 0, $form_id );
if ( class_exists( 'GravityView_Entry_Approval' ) ) {
    GravityView_Entry_Approval::update_approved( $entry_id, 1, $form_id );
}

$gf_shortcode_page_id = wu008_19_insert_page( 'wu008-gf-shortcode', 'WU008 Gravity Forms Shortcode', sprintf( '[gravityform id="%d" title="true" description="true" ajax="false"]', $form_id ) );
$gf_block_page_id = wu008_19_insert_page( 'wu008-gf-block', 'WU008 Gravity Forms Block', sprintf( '<!-- wp:gravityforms/form {"formId":"%d","title":true,"description":true,"ajax":false} /-->', $form_id ) );
$flow_shortcode_page_id = wu008_19_insert_page( 'wu008-flow-shortcode', 'WU008 Gravity Flow Shortcode', sprintf( '[gravityflow page="inbox" form="%d" title="WU008 Workflow"]', $form_id ) );

wu008_19_assert( post_type_exists( 'gravityview' ), 'GravityView post type is unavailable.' );
$view_id = wp_insert_post( array( 'post_type' => 'gravityview', 'post_status' => 'publish', 'post_title' => 'WU008 Evidence View' ), true );
if ( is_wp_error( $view_id ) ) throw new RuntimeException( $view_id->get_error_message() );
$view_id = (int) $view_id;
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta( $view_id, '_gravityview_template_settings', array( 'page_size' => '25' ) );
update_post_meta( $view_id, '_gravityview_directory_fields', array(
    'directory_table-columns' => array(
        'wu008_field_1' => array( 'id' => '1', 'label' => 'Required Runtime Field', 'show_label' => '1', 'custom_label' => '', 'custom_class' => '', 'show_as_link' => '0', 'search_filter' => '0', 'only_loggedin' => '0', 'only_loggedin_cap' => 'read' ),
        'wu008_field_2' => array( 'id' => '2', 'label' => 'Runtime Email', 'show_label' => '1', 'custom_label' => '', 'custom_class' => '', 'show_as_link' => '0', 'search_filter' => '0', 'only_loggedin' => '0', 'only_loggedin_cap' => 'read' ),
    ),
) );
update_post_meta( $view_id, '_gravityview_directory_widgets', array() );

$trashed_view_id = wp_insert_post( array( 'post_type' => 'gravityview', 'post_status' => 'draft', 'post_title' => 'WU008 Trashed Evidence View' ), true );
if ( is_wp_error( $trashed_view_id ) ) throw new RuntimeException( $trashed_view_id->get_error_message() );
$trashed_view_id = (int) $trashed_view_id;
update_post_meta( $trashed_view_id, '_gravityview_form_id', $form_id );
update_post_meta( $trashed_view_id, '_gravityview_directory_template', 'default_table' );
wp_trash_post( $trashed_view_id );

$gv_shortcode_page_id = wu008_19_insert_page( 'wu008-gv-shortcode', 'WU008 GravityView Shortcode', sprintf( '[gravityview id="%d"]', $trashed_view_id ) );
$gv_block_page_id = wu008_19_insert_page( 'wu008-gv-block', 'WU008 GravityView Block', sprintf( '<!-- wp:gk-gravityview-blocks/view {"viewId":"%d"} /-->', $view_id ) );
$gv_search_page_id = wu008_19_insert_page( 'wu008-gv-search', 'WU008 GravityView Search Widget', sprintf( '[gravityview id="%d"]\n[gravityview_widget_search]', $view_id ) );

$surface_record_files = array(
    'gravityforms::frontend_runtime::shortcode:gravityform' => array( 'gravityforms', 'gravityforms', 'frontend-shortcode-fa_IR.po' ),
    'gravityforms::frontend_runtime::block:gravityforms/form' => array( 'gravityforms', 'gravityforms', 'frontend-block-fa_IR.po' ),
    'gravityforms::admin_builder::admin_page:gf_edit_forms' => array( 'gravityforms', 'gravityforms', 'admin-builder-fa_IR.po' ),
    'gravityforms::entry_management_runtime::admin_page:gf_entries' => array( 'gravityforms', 'gravityforms', 'entry-management-fa_IR.po' ),
    'gravityforms::settings_integrations::admin_page:gf_settings' => array( 'gravityforms', 'gravityforms', 'settings-integrations-fa_IR.po' ),
    'gravityforms::developer_diagnostics::admin_page:gf_system_status' => array( 'gravityforms', 'gravityforms', 'developer-diagnostics-fa_IR.po' ),
    'gravityflow::workflow_runtime::shortcode:gravityflow' => array( 'gravityflow', 'gravityflow', 'shortcode-fa_IR.po' ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-inbox' => array( 'gravityflow', 'gravityflow', 'inbox-fa_IR.po' ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-status' => array( 'gravityflow', 'gravityflow', 'status-fa_IR.po' ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-reports' => array( 'gravityflow', 'gravityflow', 'reports-fa_IR.po' ),
    'gravityflow::admin_builder::form_settings:gravityflow' => array( 'gravityflow', 'gravityflow', 'admin-builder-fa_IR.po' ),
    'gravityflow::settings_integrations::admin_page:gravityflow_settings' => array( 'gravityflow', 'gravityflow', 'settings-integrations-fa_IR.po' ),
    'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow' => array( 'gravityflow', 'gravityflow', 'entry-detail-sidebar-fa_IR.po' ),
    'gravityview::frontend_runtime::shortcode:gravityview' => array( 'gravityview', 'gk-gravityview', 'frontend-shortcode-fa_IR.po' ),
    'gravityview::frontend_runtime::block:gk-gravityview-blocks/view' => array( 'gravityview', 'gk-gravityview', 'gutenberg-view-block-fa_IR.po' ),
    'gravityview::admin_builder::post_type:gravityview' => array( 'gravityview', 'gk-gravityview', 'admin-builder-fa_IR.po' ),
    'gravityview::settings_integrations::foundation_settings:gravityview' => array( 'gravityview', 'gk-gravityview', 'foundation-settings-fa_IR.po' ),
    'gravityview::entry_management_runtime::gravityforms_entry_list:approval' => array( 'gravityview', 'gk-gravityview', 'entry-approval-fa_IR.po' ),
    'gravityview::frontend_runtime::widget:gravityview_widget_search' => array( 'gravityview', 'gk-gravityview', 'search-widget-fa_IR.po' ),
);

$navigation = array(
    'gravityforms::frontend_runtime::shortcode:gravityform' => get_permalink( $gf_shortcode_page_id ),
    'gravityforms::frontend_runtime::block:gravityforms/form' => get_permalink( $gf_block_page_id ),
    'gravityforms::admin_builder::admin_page:gf_edit_forms' => admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id ),
    'gravityforms::entry_management_runtime::admin_page:gf_entries' => admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $form_id ),
    'gravityforms::settings_integrations::admin_page:gf_settings' => admin_url( 'admin.php?page=gf_settings' ),
    'gravityforms::developer_diagnostics::admin_page:gf_system_status' => admin_url( 'admin.php?page=gf_system_status' ),
    'gravityflow::workflow_runtime::shortcode:gravityflow' => get_permalink( $flow_shortcode_page_id ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-inbox' => admin_url( 'admin.php?page=gravityflow-inbox' ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-status' => admin_url( 'admin.php?page=gravityflow-status' ),
    'gravityflow::workflow_runtime::admin_page:gravityflow-reports' => admin_url( 'admin.php?page=gravityflow-reports' ),
    'gravityflow::admin_builder::form_settings:gravityflow' => admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=gravityflow&id=' . $form_id ),
    'gravityflow::settings_integrations::admin_page:gravityflow_settings' => admin_url( 'admin.php?page=gravityflow_settings' ),
    'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow' => admin_url( 'admin.php?page=gravityflow-inbox&view=entry&id=' . $form_id . '&lid=' . $entry_id ),
    'gravityview::frontend_runtime::shortcode:gravityview' => get_permalink( $gv_shortcode_page_id ),
    'gravityview::frontend_runtime::block:gk-gravityview-blocks/view' => get_permalink( $gv_block_page_id ),
    'gravityview::admin_builder::post_type:gravityview' => admin_url( 'post.php?post=' . $view_id . '&action=edit' ),
    'gravityview::settings_integrations::foundation_settings:gravityview' => admin_url( 'edit.php?post_type=gravityview&page=gravityview_settings' ),
    'gravityview::entry_management_runtime::gravityforms_entry_list:approval' => admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $form_id ),
    'gravityview::frontend_runtime::widget:gravityview_widget_search' => get_permalink( $gv_search_page_id ),
);

$metadata = array();
foreach ( array( 'gravityforms', 'gravityflow', 'gravityview' ) as $product ) {
    $metadata_path = PGR_PATH . 'languages/providers/' . $product . '/metadata.json';
    wu008_19_assert( is_readable( $metadata_path ), 'Missing provider metadata: ' . $product );
    $metadata[ $product ] = json_decode( file_get_contents( $metadata_path ), true );
    wu008_19_assert( is_array( $metadata[ $product ] ), 'Invalid provider metadata: ' . $product );
}
$counts = array();
foreach ( $metadata as $data ) {
    foreach ( $data['provenance']['content_admissions'] ?? array() as $admission ) {
        $counts[ $admission['surface_id'] ] = (int) $admission['admitted_message_count'];
    }
}
wu008_19_assert( 19 === count( $counts ), 'Production admission count must be exactly 19.' );

$authority = array( 'surfaces' => array(), 'aggregate' => array() );
foreach ( $surface_record_files as $surface_id => $record ) {
    list( $product, $domain, $filename ) = $record;
    wu008_19_assert( array_key_exists( $surface_id, $counts ), 'Missing admission count for ' . $surface_id );
    $po_path = PGR_PATH . 'languages/providers/' . $product . '/source/records/' . $filename;
    $entries = wu008_19_parse_po( $po_path );
    wu008_19_assert( count( $entries ) === $counts[ $surface_id ], 'Surface PO count mismatch for ' . $surface_id );
    $authority['surfaces'][ $surface_id ] = array( 'product' => $product, 'domain' => $domain, 'admitted_message_count' => $counts[ $surface_id ], 'record_po' => str_replace( PGR_PATH, '', $po_path ), 'entries' => $entries );
}
foreach ( array( 'gravityforms' => 'gravityforms', 'gravityflow' => 'gravityflow', 'gravityview' => 'gk-gravityview' ) as $product => $domain ) {
    $authority['aggregate'][ $domain ] = wu008_19_parse_po( PGR_PATH . 'languages/providers/' . $product . '/source/fa_IR.po' );
}
file_put_contents( $artifact_dir . '/surface-authority.json', wp_json_encode( $authority, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

$products = require PGR_PATH . 'includes/localization/products.php';
$script_maps = array();
foreach ( array( 'gravityforms', 'gravityflow', 'gk-gravityview' ) as $domain ) {
    $script_maps[ $domain ] = array_keys( (array) ( $products[ $domain ]['scripts'] ?? array() ) );
    wu008_19_assert( array() === $script_maps[ $domain ], 'Unexpected approved JS script handle for ' . $domain );
}
$provider_json = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( PGR_PATH . 'languages/providers', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if ( $file->isFile() && preg_match( '/-fa_IR-.+\.json$/', $file->getFilename() ) ) {
        $provider_json[] = str_replace( PGR_PATH, '', $file->getPathname() );
    }
}
wu008_19_assert( array() === $provider_json, 'Unexpected PersianGravity provider translation JSON catalog.' );

$surfaces = array();
foreach ( $surface_record_files as $surface_id => $record ) {
    $surfaces[] = array( 'surface_id' => $surface_id, 'product' => $record[0], 'domain' => $record[1], 'admitted_message_count' => $counts[ $surface_id ], 'navigation' => $navigation[ $surface_id ] );
}

$manifest = array(
    'schema_version' => '2.0.0',
    'evidence_class' => 'REAL_LICENSED_HEADLESS_BROWSER_RUNTIME_19_SURFACES',
    'exact_persiangravity_commit_expected' => $expected_sha,
    'exact_persiangravity_commit_actual' => $actual_sha,
    'locale' => get_locale(), 'effective_locale' => determine_locale(), 'rtl' => is_rtl(), 'versions' => $versions,
    'provider_control' => $provider, 'fallback_control' => $fallback, 'script_maps' => $script_maps, 'provider_translation_json_catalogs' => $provider_json,
    'fixtures' => array( 'form_id' => $form_id, 'entry_id' => $entry_id, 'view_id' => $view_id, 'trashed_view_id' => $trashed_view_id, 'gf_shortcode_page_id' => $gf_shortcode_page_id, 'gf_block_page_id' => $gf_block_page_id, 'flow_shortcode_page_id' => $flow_shortcode_page_id, 'gv_shortcode_page_id' => $gv_shortcode_page_id, 'gv_block_page_id' => $gv_block_page_id, 'gv_search_page_id' => $gv_search_page_id ),
    'login_url' => wp_login_url( admin_url() ), 'admin_url' => admin_url(), 'surfaces' => $surfaces,
    'isolation' => array( 'database' => 'fresh MariaDB service database on ephemeral GitHub-hosted runner', 'uploads' => 'fresh disposable WordPress wp-content/uploads on ephemeral runner', 'cache' => 'no persistent application cache; fresh runner and WordPress tree', 'runtime_fixtures' => 'created deterministically by tests/real-integration/setup-runtime-19.php' ),
);
wu008_19_assert( true === $manifest['rtl'], 'WordPress fa_IR runtime is not RTL.' );
wu008_19_assert( 19 === count( $manifest['surfaces'] ), 'Runtime manifest must contain exactly 19 surfaces.' );
file_put_contents( $artifact_dir . '/runtime-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
file_put_contents( $artifact_dir . '/environment-manifest.json', wp_json_encode( array(
    'exact_persiangravity_commit_expected' => $expected_sha, 'exact_persiangravity_commit_actual' => $actual_sha,
    'wordpress_version' => $versions['wordpress'], 'php_version' => $versions['php'], 'locale' => get_locale(), 'effective_locale' => determine_locale(), 'rtl' => is_rtl(),
    'gravityforms_version' => $versions['gravityforms'], 'gravityflow_version' => $versions['gravityflow'], 'gravityview_version' => $versions['gravityview'],
    'browser' => null, 'runner' => array( 'os' => PHP_OS_FAMILY, 'php_uname' => php_uname() ), 'isolation' => $manifest['isolation'], 'vendor_authenticity' => 'NOT_PROVEN',
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
