import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedViewSha = process.env.WU008_VIEW_SHA256;
if (!wpPath || !artifactDir || !expectedViewSha) {
  throw new Error('WU008_WP_PATH, WU008_ARTIFACT_DIR and WU008_VIEW_SHA256 are required.');
}

const gvRoot = path.join(wpPath, 'wp-content/plugins/gravityview');
const gfRoot = path.join(wpPath, 'wp-content/plugins/gravityforms');

function read(root, relative) {
  const full = path.join(root, relative);
  const content = fs.readFileSync(full, 'utf8');
  return { relative, full, content, lines: content.split(/\r?\n/), sha256: crypto.createHash('sha256').update(content).digest('hex') };
}

function lineOf(file, needle) {
  const index = file.lines.findIndex((line) => line.includes(needle));
  if (index < 0) throw new Error(`${file.relative}: missing source contract: ${needle}`);
  return index + 1;
}

function span(file, startNeedle, endNeedle = startNeedle) {
  const start = lineOf(file, startNeedle);
  const end = lineOf(file, endNeedle);
  if (end < start) throw new Error(`${file.relative}: invalid source provenance span.`);
  return { file: file.relative, line_start: start, line_end: end, sha256: file.sha256 };
}

const plugin = read(gvRoot, 'gravityview.php');
const dateCreated = read(gvRoot, 'src/Field/Types/DateCreated.php');
const dateUpdated = read(gvRoot, 'src/Field/Types/DateUpdated.php');
const internalField = read(gvRoot, 'src/Field/InternalField.php');
const templateField = read(gvRoot, 'src/Template/TemplateField.php');
const templateContext = read(gvRoot, 'src/Template/TemplateContext.php');
const common = read(gvRoot, 'src/Legacy/Utility/Common.php');
const view = read(gvRoot, 'src/View/View.php');
const searchPolicy = read(gvRoot, 'src/Search/SearchPolicy.php');
const searchVisitor = read(gvRoot, 'src/Search/Querying/Visitors/AbstractSearchFilterVisitor.php');
const processDate = read(gvRoot, 'vendor_prefixed/gravitykit/query-filters/src/Filter/Visitor/ProcessDateVisitor.php');
const data = read(gvRoot, 'src/Data/Data.php');
const gfApi = read(gfRoot, 'includes/api.php');
const gfRest = read(gfRoot, 'includes/webapi/v2/includes/controllers/class-controller-form-entries.php');

lineOf(plugin, "define( 'GV_PLUGIN_VERSION', '3.3.4' );");
lineOf(dateCreated, "var $name = 'date_created';");
lineOf(dateCreated, 'var $is_searchable = true;');
lineOf(dateCreated, "return \\GVCommon::format_date( $field['value'], 'format=' . \\GV\\Utils::get( $field_settings, 'date_display' ) );");
lineOf(dateUpdated, 'class DateUpdated extends \\GravityView_Field_Date_Created');
lineOf(dateUpdated, "var $name = 'date_updated';");
lineOf(dateUpdated, 'var $is_searchable = true;');
lineOf(dateUpdated, "return \\GVCommon::format_date( $field['value'], 'format=' . \\GV\\Utils::get( $field_settings, 'date_display' ) );");
lineOf(internalField, '$value = \\GV\\Utils::get( $entry->as_entry(), $this->ID );');
lineOf(templateField, '$display_value = $value = $this->field->get_value( $this->view, $this->source, $entry );');
lineOf(templateField, "$context = Template_Context::from_template( $this, compact( 'display_value', 'value' ) );");
lineOf(templateField, 'return apply_filters( "gravityview/template/field/{$field->type}/output", $output, $context );');
lineOf(templateField, "add_filter( 'gravityview/template/field/output', $pre_link_compat_callback, 5, 2 );");
lineOf(templateField, "add_filter( 'gravityview/template/field/output', $post_link_compat_callback, 9, 2 );");
lineOf(templateContext, '$context->value         = \\GV\\Utils::get( $data, \'value\' );');
lineOf(templateContext, "$context->field   = \\GV\\Utils::get( $template, 'field' ) ? : \\GV\\Utils::get( $data, 'field' );");
lineOf(templateContext, "$context->entry   = \\GV\\Utils::get( $template, 'entry' ) ? : \\GV\\Utils::get( $data, 'entry' );");
lineOf(common, 'The date as stored by Gravity Forms');
lineOf(common, "$date_local_timestamp = GFCommon::get_local_timestamp( $date_gmt_time );");
lineOf(common, '$formatted_date = GFCommon::format_date( $date_string, $is_human, $format, $include_time );');
lineOf(view, '$query = new $query_class( $this->form->ID, $parameters[\'search_criteria\'], \\GV\\Utils::get( $parameters, \'sorting\' ) );');
lineOf(view, '$sort_field_ids  = array_keys( $_GET[\'sort\'] );');
lineOf(view, '$order = new GF_Query_Column( $field[\'id\'], $this->form->ID );');
lineOf(view, '$query->order( $order, $field[\'direction\'] );');
lineOf(searchPolicy, 'date_created');
lineOf(searchPolicy, 'stored in UTC format');
lineOf(searchVisitor, 'protected function resolve_date_range( SearchFilter $filter ): array');
lineOf(processDate, "'date_created',");
lineOf(processDate, "'date_updated',");
lineOf(processDate, 'These fields are all stored in GMT+0');
lineOf(data, "in_array( $source_field_id, [ 'date_created', 'date_updated', 'payment_date' ], true )");
lineOf(gfApi, "$date_created   = isset( $entry['date_created'] )");
lineOf(gfApi, "$date_updated   = isset( $entry['date_updated'] )");
lineOf(gfRest, "'date_created' => array(");
lineOf(gfRest, 'The date the entry was created, in UTC.');
lineOf(gfRest, "'date_updated' => array(");
lineOf(gfRest, 'The date the entry was updated, in UTC.');

const result = {
  schema_version: '1.0.0',
  evidence_class: 'EXACT_PACKAGE_SOURCE_QUALIFICATION',
  gravityview: {
    version: '3.3.4',
    package_sha256: expectedViewSha,
    main_file: span(plugin, "define( 'GV_PLUGIN_VERSION', '3.3.4' );"),
  },
  gravityforms: { version: '3.1.1.1' },
  shared_render_contract: {
    raw_entry_property: span(internalField, '$value = \\GV\\Utils::get( $entry->as_entry(), $this->ID );'),
    field_render_raw_capture: span(templateField, '$display_value = $value = $this->field->get_value( $this->view, $this->source, $entry );'),
    template_context_raw_and_identity: span(templateContext, '$context->value         = \\GV\\Utils::get( $data, \'value\' );', "$context->entry   = \\GV\\Utils::get( $template, 'entry' ) ? : \\GV\\Utils::get( $data, 'entry' );"),
    type_specific_output_seam: span(templateField, 'return apply_filters( "gravityview/template/field/{$field->type}/output", $output, $context );'),
    filter_ordering: span(templateField, "add_filter( 'gravityview/template/field/output', $pre_link_compat_callback, 5, 2 );", "add_filter( 'gravityview/template/field/output', $post_link_compat_callback, 9, 2 );"),
    native_timezone_formatting: span(common, 'The date as stored by Gravity Forms', '$formatted_date = GFCommon::format_date( $date_string, $is_human, $format, $include_time );'),
    bounded_configuration_requirement: 'The qualified modern type-specific output seam is after GravityView native pre-link formatting/link handling. A later production adapter must therefore fail closed for show_as_link/custom field_path contexts unless separately proven; the qualification prototype converts only default unlinked fields.',
  },
  candidates: {
    date_created: {
      field_definition: span(dateCreated, "var $name = 'date_created';", "var $contexts = ['single', 'multiple', 'export'];"),
      native_formatting: span(dateCreated, "return \\GVCommon::format_date( $field['value'], 'format=' . \\GV\\Utils::get( $field_settings, 'date_display' ) );"),
      gf_utc_contract: span(gfRest, "'date_created' => array(", 'The date the entry was created, in UTC.'),
      searchable: true,
    },
    date_updated: {
      field_definition: span(dateUpdated, 'class DateUpdated extends \\GravityView_Field_Date_Created', "var $contexts = ['single', 'multiple', 'export'];"),
      native_formatting: span(dateUpdated, "return \\GVCommon::format_date( $field['value'], 'format=' . \\GV\\Utils::get( $field_settings, 'date_display' ) );"),
      gf_utc_contract: span(gfRest, "'date_updated' => array(", 'The date the entry was updated, in UTC.'),
      searchable: true,
    },
  },
  machine_semantics: {
    gf_insert_uses_raw_or_utc_timestamp: span(gfApi, "$date_created   = isset( $entry['date_created'] )", "$date_updated   = isset( $entry['date_updated'] )"),
    view_query_constructed_before_render: span(view, '$query = new $query_class( $this->form->ID, $parameters[\'search_criteria\'], \\GV\\Utils::get( $parameters, \'sorting\' ) );'),
    view_sort_uses_field_id: span(view, '$sort_field_ids  = array_keys( $_GET[\'sort\'] );', '$query->order( $order, $field[\'direction\'] );'),
    date_search_timezone_policy: span(searchPolicy, 'date_created', 'stored in UTC format'),
    date_filter_resolver: span(searchVisitor, 'protected function resolve_date_range( SearchFilter $filter ): array'),
    query_filter_native_date_keys: span(processDate, "'date_created',", "'date_updated',"),
    query_filter_gmt_contract: span(processDate, 'These fields are all stored in GMT+0'),
    conditional_logic_raw_dates: span(data, "in_array( $source_field_id, [ 'date_created', 'date_updated', 'payment_date' ], true )"),
  },
  conclusion: {
    source_only: true,
    candidate_seam: 'gravityview/template/field/date_created/output and gravityview/template/field/date_updated/output',
    source_supports_raw_context_without_display_reparse: true,
    production_admission: false,
    runtime_required: true,
  },
};

fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-source-qualification.json'), `${JSON.stringify(result, null, 2)}\n`);
process.stdout.write(`${JSON.stringify(result)}\n`);
