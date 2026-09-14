<?php
/**
 * Deterministic WU-008 fixture setup for the complete 19-surface browser matrix.
 */

defined( 'ABSPATH' ) || exit( 1 );

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

function wu008_git_head( $path ) {
	$output = array();
	$status = 0;
	exec( 'git -C ' . escapeshellarg( $path ) . ' rev-parse HEAD 2>/dev/null', $output, $status );
	return 0 === $status && isset( $output[0] ) ? trim( $output[0] ) : '';
}

function wu008_read_json( $path ) {
	wu008_assert( is_readable( $path ), 'Missing JSON evidence file: ' . $path );
	$data = json_decode( file_get_contents( $path ), true );
	wu008_assert( is_array( $data ), 'Invalid JSON evidence file: ' . $path );
	return $data;
}

function wu008_create_page( $title, $slug, $content ) {
	$page_id = wp_insert_post(
		array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		throw new RuntimeException( $page_id->get_error_message() );
	}
	return (int) $page_id;
}

wu008_assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
wu008_assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
wu008_assert( true === is_rtl(), 'WordPress fa_IR runtime must be RTL.' );
wu008_assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
wu008_assert( class_exists( 'Gravity_Flow_API' ), 'Gravity Flow runtime API is unavailable.' );
wu008_assert( function_exists( 'gravityview' ), 'GravityView runtime is unavailable.' );

$versions = array(
	'wordpress' => get_bloginfo( 'version' ),
	'gravityforms' => wu008_plugin_version( 'gravityforms/gravityforms.php' ),
	'gravityflow' => wu008_plugin_version( 'gravityflow/gravityflow.php' ),
	'gravityview' => wu008_plugin_version( 'gravityview/gravityview.php' ),
	'persiangravity' => wu008_plugin_version( 'persian-gravityforms/persian-gravityforms.php' ),
);
wu008_assert( '6.8.3' === $versions['wordpress'], 'WordPress runtime version mismatch.' );
wu008_assert( '3.1.1.1' === $versions['gravityforms'], 'Gravity Forms runtime version mismatch.' );
wu008_assert( '3.1.0' === $versions['gravityflow'], 'Gravity Flow runtime version mismatch.' );
wu008_assert( '3.3.4' === $versions['gravityview'], 'GravityView runtime version mismatch.' );
wu008_assert( '4.2.0' === $versions['persiangravity'], 'PersianGravity runtime version mismatch.' );

$pgr_path = WP_PLUGIN_DIR . '/persian-gravityforms';
$expected_pgr_sha = getenv( 'WU008_PGR_SHA' );
$actual_pgr_sha = wu008_git_head( $pgr_path );
wu008_assert( is_string( $expected_pgr_sha ) && '' !== $expected_pgr_sha, 'WU008_PGR_SHA is required.' );
wu008_assert( $expected_pgr_sha === $actual_pgr_sha, 'PersianGravity runtime git HEAD mismatch.' );
wu008_assert( 'd3d6460a07a2c38b483dac664603a443ce430da0' === $actual_pgr_sha, 'WU-008 must execute exact canonical PersianGravity commit.' );

$surface_registry = wu008_read_json( $pgr_path . '/tools/i18n/admission/surfaces.json' );
wu008_assert( isset( $surface_registry['summary'] ), 'Surface registry summary missing.' );
$summary = $surface_registry['summary'];
wu008_assert( 6 === (int) $summary['gravityforms']['surfaces'], 'Gravity Forms surface count mismatch.' );
wu008_assert( 1759 === (int) $summary['gravityforms']['classified_messages'], 'Gravity Forms admitted union mismatch.' );
wu008_assert( 2448 === (int) $summary['gravityforms']['unclassified_messages'], 'Gravity Forms non-admitted count mismatch.' );
wu008_assert( 7 === (int) $summary['gravityflow']['surfaces'], 'Gravity Flow surface count mismatch.' );
wu008_assert( 732 === (int) $summary['gravityflow']['classified_messages'], 'Gravity Flow admitted union mismatch.' );
wu008_assert( 366 === (int) $summary['gravityflow']['unclassified_messages'], 'Gravity Flow non-admitted count mismatch.' );
wu008_assert( 6 === (int) $summary['gravityview']['surfaces'], 'GravityView surface count mismatch.' );
wu008_assert( 461 === (int) $summary['gravityview']['classified_messages'], 'GravityView admitted union mismatch.' );
wu008_assert( 2666 === (int) $summary['gravityview']['unclassified_messages'], 'GravityView non-admitted count mismatch.' );
wu008_assert( 19 === count( $surface_registry['surfaces'] ), 'Accepted surface registry must contain exactly 19 records.' );

$metadata_paths = array(
	'gravityforms' => $pgr_path . '/languages/providers/gravityforms/metadata.json',
	'gravityflow' => $pgr_path . '/languages/providers/gravityflow/metadata.json',
	'gravityview' => $pgr_path . '/languages/providers/gravityview/metadata.json',
);
$admission_counts = array();
$js_authority = array();
foreach ( $metadata_paths as $product => $metadata_path ) {
	$metadata = wu008_read_json( $metadata_path );
	$admissions = $metadata['content_admission']['admissions'] ?? $metadata['provenance']['content_admissions'] ?? array();
	foreach ( $admissions as $admission ) {
		$admission_counts[ $admission['surface_id'] ] = (int) $admission['admitted_message_count'];
	}
	$js_authority[ $product ] = array(
		'native_js_handles_activated' => (int) ( $metadata['provenance']['native_js_handles_activated'] ?? 0 ),
		'js_translation_json_generated' => (int) ( $metadata['provenance']['js_translation_json_generated'] ?? 0 ),
		'validated_script_handles' => array_values( $metadata['validated_script_handles'] ?? array() ),
	);
	wu008_assert( 0 === $js_authority[ $product ]['native_js_handles_activated'], $product . ' unexpectedly activates PersianGravity JS handles.' );
	wu008_assert( 0 === $js_authority[ $product ]['js_translation_json_generated'], $product . ' unexpectedly generates provider JSON.' );
	wu008_assert( array() === $js_authority[ $product ]['validated_script_handles'], $product . ' validated script handle list must be empty.' );
}
wu008_assert( 19 === count( $admission_counts ), 'Content Admission must expose exactly 19 surface records.' );
$zero_surface = 'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow';
wu008_assert( array_key_exists( $zero_surface, $admission_counts ) && 0 === $admission_counts[ $zero_surface ], 'Gravity Flow entry-detail sidebar must have zero admitted identities.' );

$products_manifest = require $pgr_path . '/includes/localization/products.php';
foreach ( array( 'gravityforms', 'gravityflow', 'gk-gravityview' ) as $domain ) {
	wu008_assert( isset( $products_manifest[ $domain ] ), 'Missing product manifest domain: ' . $domain );
	wu008_assert( empty( $products_manifest[ $domain ]['scripts'] ), 'Product script map must remain empty: ' . $domain );
}
wu008_assert( ! isset( $products_manifest['gk-query-filters'] ), 'gk-query-filters must remain outside PersianGravity authority.' );
wu008_assert( ! isset( $products_manifest['action-scheduler'] ), 'action-scheduler must remain outside PersianGravity authority.' );
$dependency_boundary = array(
	'gk-query-filters' => __( 'WU008 gk-query-filters provider-boundary sentinel', 'gk-query-filters' ),
	'action-scheduler' => __( 'WU008 action-scheduler provider-boundary sentinel', 'action-scheduler' ),
);
wu008_assert( 'WU008 gk-query-filters provider-boundary sentinel' === $dependency_boundary['gk-query-filters'], 'gk-query-filters was unexpectedly captured by PersianGravity.' );
wu008_assert( 'WU008 action-scheduler provider-boundary sentinel' === $dependency_boundary['action-scheduler'], 'action-scheduler was unexpectedly captured by PersianGravity.' );

$provider = array(
	'gravityforms' => __( 'There was a problem with your submission.', 'gravityforms' ),
	'gravityflow' => __( 'No Pending Tasks', 'gravityflow' ),
	'gravityview' => __( 'This View is in the Trash. %1$sClick to restore the View%2$s.', 'gk-gravityview' ),
);
wu008_assert( 'مشکلی با این ارسال پیش آمده است.' === $provider['gravityforms'], 'Gravity Forms provider translation did not resolve.' );
wu008_assert( 'کاری در انتظار نیست' === $provider['gravityflow'], 'Gravity Flow provider translation did not resolve.' );
wu008_assert( 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.' === $provider['gravityview'], 'GravityView provider translation did not resolve.' );

$form = array(
	'title' => 'WU008 Evidence Form',
	'description' => 'Deterministic licensed runtime fixture.',
	'fields' => array(
		array( 'id' => 1, 'label' => 'Name', 'type' => 'text', 'isRequired' => true, 'description' => 'Required evidence field.' ),
		array( 'id' => 2, 'label' => 'Notes', 'type' => 'textarea' ),
		array( 'id' => 3, 'label' => 'Choice', 'type' => 'select', 'choices' => array( array( 'text' => 'Alpha', 'value' => 'alpha' ), array( 'text' => 'Beta', 'value' => 'beta' ) ) ),
		array( 'id' => 4, 'label' => 'Evidence Date', 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
		array( 'id' => 5, 'label' => 'Technical Email', 'type' => 'email' ),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) throw new RuntimeException( $form_id->get_error_message() );
$form_id = (int) $form_id;

$entry_id = GFAPI::add_entry(
	array(
		'form_id' => $form_id,
		'1' => 'WU008 Alpha',
		'2' => 'متن آزمایشی WU008',
		'3' => 'alpha',
		'4' => '09/14/2026',
		'5' => 'qa@example.invalid',
		'created_by' => 1,
	)
);
if ( is_wp_error( $entry_id ) ) throw new RuntimeException( $entry_id->get_error_message() );
$entry_id = (int) $entry_id;

$flow_api = new Gravity_Flow_API( $form_id );
$step_id = $flow_api->add_step(
	array(
		'step_name' => 'WU008 Approval',
		'step_type' => 'approval',
		'description' => 'Deterministic approval step for WU008 browser evidence.',
		'type' => 'select',
		'assignees' => array( 'user_id|1' ),
		'assignee_policy' => 'any',
		'instructions' => 'Review the evidence entry.',
	)
);
wu008_assert( is_numeric( $step_id ) && (int) $step_id > 0, 'Gravity Flow approval step fixture could not be created.' );
$step_id = (int) $step_id;
$flow_api->process_workflow( $entry_id );
$entry = GFAPI::get_entry( $entry_id );
wu008_assert( ! is_wp_error( $entry ), 'Gravity Flow fixture entry could not be reloaded.' );
$current_step = $flow_api->get_current_step( $entry );
wu008_assert( is_object( $current_step ), 'Gravity Flow fixture did not enter an active workflow step.' );

$gf_shortcode_page = wu008_create_page( 'WU008 Gravity Forms Shortcode', 'wu008-gf-shortcode', sprintf( '[gravityform id="%d" title="true" description="true" ajax="false"]', $form_id ) );
$gf_block_page = wu008_create_page( 'WU008 Gravity Forms Block', 'wu008-gf-block', '<!-- wp:gravityforms/form {"formId":"' . $form_id . '","title":true,"description":true,"ajax":false} /-->' );
$flow_shortcode_page = wu008_create_page( 'WU008 Gravity Flow Shortcode', 'wu008-flow-shortcode', '[gravityflow]' );

$view_id = wp_insert_post( array( 'post_type' => 'gravityview', 'post_status' => 'publish', 'post_title' => 'WU008 Evidence View', 'post_name' => 'wu008-evidence-view' ), true );
if ( is_wp_error( $view_id ) ) throw new RuntimeException( $view_id->get_error_message() );
$view_id = (int) $view_id;
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta( $view_id, '_gravityview_template_settings', array( 'page_size' => '25' ) );
update_post_meta(
	$view_id,
	'_gravityview_directory_fields',
	array(
		'directory_table-columns' => array(
			'wu008field1' => array(
				'id' => '1', 'label' => 'Name', 'show_label' => '1', 'custom_label' => '', 'custom_class' => '', 'show_as_link' => '0', 'search_filter' => '0', 'only_loggedin' => '0', 'only_loggedin_cap' => 'read',
			),
		),
	)
);
update_post_meta(
	$view_id,
	'_gravityview_directory_widgets',
	array(
		'header_top' => array(
			'wu008search' => array(
				'id' => 'search_bar',
				'label' => 'Search Bar',
				'search_fields' => wp_json_encode( array( array( 'field' => '1', 'input' => 'input_text' ), array( 'field' => 'search_all', 'input' => 'input_text' ) ) ),
				'search_layout' => 'horizontal',
				'search_clear' => '1',
			),
		),
	)
);

$trashed_view_id = wp_insert_post( array( 'post_type' => 'gravityview', 'post_status' => 'publish', 'post_title' => 'WU008 Trashed View' ), true );
if ( is_wp_error( $trashed_view_id ) ) throw new RuntimeException( $trashed_view_id->get_error_message() );
$trashed_view_id = (int) $trashed_view_id;
update_post_meta( $trashed_view_id, '_gravityview_form_id', $form_id );
update_post_meta( $trashed_view_id, '_gravityview_directory_template', 'default_table' );
wp_trash_post( $trashed_view_id );
wu008_assert( 'trash' === get_post_status( $trashed_view_id ), 'GravityView shortcode trash fixture was not created.' );

$gv_shortcode_page = wu008_create_page( 'WU008 GravityView Shortcode', 'wu008-gv-shortcode', sprintf( '[gravityview id="%d"]', $trashed_view_id ) );
$gv_block_page = wu008_create_page( 'WU008 GravityView Block', 'wu008-gv-block', '<!-- wp:gk-gravityview-blocks/view {"viewId":"' . $view_id . '"} /-->' );
$gv_search_page = wu008_create_page( 'WU008 GravityView Search', 'wu008-gv-search', sprintf( '[gravityview id="%d"]', $view_id ) );

$surface_rows = array(
	array( 'key' => 's01', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::frontend_runtime::shortcode:gravityform', 'fixture' => 'Gravity Forms form + shortcode page', 'navigation' => get_permalink( $gf_shortcode_page ) ),
	array( 'key' => 's02', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::frontend_runtime::block:gravityforms/form', 'fixture' => 'Gravity Forms form + dynamic Form block page', 'navigation' => get_permalink( $gf_block_page ) ),
	array( 'key' => 's03', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::admin_builder::admin_page:gf_edit_forms', 'fixture' => 'Gravity Forms form editor', 'navigation' => admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id ) ),
	array( 'key' => 's04', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::entry_management_runtime::admin_page:gf_entries', 'fixture' => 'Gravity Forms entry list with one entry', 'navigation' => admin_url( 'admin.php?page=gf_entries&id=' . $form_id ) ),
	array( 'key' => 's05', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::settings_integrations::admin_page:gf_settings', 'fixture' => 'Gravity Forms Settings', 'navigation' => admin_url( 'admin.php?page=gf_settings' ) ),
	array( 'key' => 's06', 'product' => 'gravityforms', 'surface_id' => 'gravityforms::developer_diagnostics::admin_page:gf_system_status', 'fixture' => 'Gravity Forms System Status', 'navigation' => admin_url( 'admin.php?page=gf_system_status' ) ),
	array( 'key' => 's07', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::workflow_runtime::shortcode:gravityflow', 'fixture' => 'Active approval task + [gravityflow] page', 'navigation' => get_permalink( $flow_shortcode_page ) ),
	array( 'key' => 's08', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-inbox', 'fixture' => 'Active approval task + native Inbox', 'navigation' => admin_url( 'admin.php?page=gravityflow-inbox' ) ),
	array( 'key' => 's09', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-status', 'fixture' => 'Native Status with workflow entry', 'navigation' => admin_url( 'admin.php?page=gravityflow-status' ) ),
	array( 'key' => 's10', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::workflow_runtime::admin_page:gravityflow-reports', 'fixture' => 'Native Reports with workflow entry', 'navigation' => admin_url( 'admin.php?page=gravityflow-reports' ) ),
	array( 'key' => 's11', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::admin_builder::form_settings:gravityflow', 'fixture' => 'Gravity Flow approval step settings', 'navigation' => admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=gravityflow&id=' . $form_id . '&fid=' . $step_id ) ),
	array( 'key' => 's12', 'product' => 'gravityflow', 'surface_id' => 'gravityflow::settings_integrations::admin_page:gravityflow_settings', 'fixture' => 'Gravity Flow global Settings', 'navigation' => admin_url( 'admin.php?page=gravityflow_settings' ) ),
	array( 'key' => 's13', 'product' => 'gravityflow', 'surface_id' => $zero_surface, 'fixture' => 'Workflow entry detail / sidebar with intentional zero admitted identities', 'navigation' => admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id ) ),
	array( 'key' => 's14', 'product' => 'gravityview', 'surface_id' => 'gravityview::frontend_runtime::shortcode:gravityview', 'fixture' => 'Authenticated shortcode rendering a deliberately trashed View', 'navigation' => get_permalink( $gv_shortcode_page ) ),
	array( 'key' => 's15', 'product' => 'gravityview', 'surface_id' => 'gravityview::frontend_runtime::block:gk-gravityview-blocks/view', 'fixture' => 'Dynamic GravityView View block page', 'navigation' => get_permalink( $gv_block_page ) ),
	array( 'key' => 's16', 'product' => 'gravityview', 'surface_id' => 'gravityview::admin_builder::post_type:gravityview', 'fixture' => 'Published GravityView post editor', 'navigation' => admin_url( 'post.php?post=' . $view_id . '&action=edit' ) ),
	array( 'key' => 's17', 'product' => 'gravityview', 'surface_id' => 'gravityview::settings_integrations::foundation_settings:gravityview', 'fixture' => 'GravityView Foundation settings', 'navigation' => admin_url( 'edit.php?post_type=gravityview&page=gravityview_settings' ) ),
	array( 'key' => 's18', 'product' => 'gravityview', 'surface_id' => 'gravityview::entry_management_runtime::gravityforms_entry_list:approval', 'fixture' => 'Gravity Forms Entries list with GravityView approval integration', 'navigation' => admin_url( 'admin.php?page=gf_entries&id=' . $form_id ) ),
	array( 'key' => 's19', 'product' => 'gravityview', 'surface_id' => 'gravityview::frontend_runtime::widget:gravityview_widget_search', 'fixture' => 'Published GravityView with Search Bar widget', 'navigation' => get_permalink( $gv_search_page ) ),
);
foreach ( $surface_rows as &$row ) {
	wu008_assert( array_key_exists( $row['surface_id'], $admission_counts ), 'Missing admission count for ' . $row['surface_id'] );
	$row['admitted_message_count'] = $admission_counts[ $row['surface_id'] ];
}
unset( $row );
wu008_assert( 19 === count( $surface_rows ), 'Runtime surface matrix must contain exactly 19 rows.' );
wu008_assert( 18 === count( array_filter( $surface_rows, static fn( $row ) => $row['admitted_message_count'] > 0 ) ), 'Runtime matrix must contain exactly 18 positive-admission surfaces.' );

$manifest = array(
	'schema_version' => '2.0.0',
	'evidence_class' => 'REAL_LICENSED_HEADLESS_BROWSER_RUNTIME',
	'locale' => get_locale(),
	'rtl' => is_rtl(),
	'persiangravity_expected_sha' => $expected_pgr_sha,
	'persiangravity_actual_sha' => $actual_pgr_sha,
	'versions' => $versions,
	'php_version' => PHP_VERSION,
	'provider' => $provider,
	'dependency_boundary' => $dependency_boundary,
	'authority_summary' => $summary,
	'js_authority' => $js_authority,
	'product_manifest_domains' => array_keys( $products_manifest ),
	'form_id' => $form_id,
	'entry_id' => $entry_id,
	'flow_step_id' => $step_id,
	'view_id' => $view_id,
	'trashed_view_id' => $trashed_view_id,
	'admin_url' => admin_url(),
	'login_url' => wp_login_url( admin_url() ),
	'surfaces' => $surface_rows,
);
file_put_contents( $artifact_dir . '/runtime-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
