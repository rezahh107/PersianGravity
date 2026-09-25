import fs from 'node:fs';
import path from 'node:path';
import {
  assertMetadataOnlySourceEvidence,
  assertTargetFieldContracts,
  evaluateTargetFieldContracts,
  proveIndependentTargetFailClosed,
  sourceLocation,
} from './g008-gravityview-source-evidence.mjs';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedHead = process.env.WU008_PGR_SHA;
const expectedViewSha = process.env.WU008_VIEW_SHA256;
const expectedGfSha = process.env.WU008_GF_SHA256;

if (
  !wpPath ||
  !artifactDir ||
  !/^[a-f0-9]{40}$/.test(expectedHead ?? '') ||
  !/^[a-f0-9]{64}$/.test(expectedViewSha ?? '') ||
  !/^[a-f0-9]{64}$/.test(expectedGfSha ?? '')
) {
  throw new Error('Exact runtime paths, Head, GravityView SHA and Gravity Forms SHA are required.');
}

const roots = {
  gravityview: path.join(wpPath, 'wp-content/plugins/gravityview'),
  gravityforms: path.join(wpPath, 'wp-content/plugins/gravityforms'),
};
for (const [product, root] of Object.entries(roots)) {
  if (!fs.existsSync(root)) throw new Error(`Exact ${product} source root is missing.`);
}

function read(root, relative) {
  const file = path.join(root, relative);
  if (!fs.existsSync(file)) throw new Error(`Required exact source path drifted: ${relative}`);
  return { relative, content: fs.readFileSync(file, 'utf8') };
}

function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const values = Object.values(value);
    return values.length > 0 && values.every(allTrue);
  }
  return false;
}

const dateCreated = read(roots.gravityview, 'src/Field/Types/DateCreated.php');
const dateUpdated = read(roots.gravityview, 'src/Field/Types/DateUpdated.php');
const templateField = read(roots.gravityview, 'src/Template/TemplateField.php');
const gvCommon = read(roots.gravityview, 'src/Legacy/Utility/Common.php');
const formGf = read(roots.gravityview, 'src/Form/FormGravityForms.php');
const searchPolicy = read(roots.gravityview, 'src/Search/SearchPolicy.php');
const searchRequest = read(roots.gravityview, 'src/Search/Querying/SearchRequest.php');
const searchScope = read(roots.gravityview, 'src/Search/Querying/SearchScope.php');
const queryVisitor = read(roots.gravityview, 'src/Search/Querying/Visitors/QueryFilterVisitor.php');
const searchWidget = read(roots.gravityview, 'src/Widget/Types/SearchWidget.php');
const searchFieldCollection = read(roots.gravityview, 'src/Search/SearchFieldCollection.php');
const searchFieldEntryDate = read(roots.gravityview, 'src/Search/Fields/SearchFieldEntryDate.php');
const inspectorRoute = read(roots.gravityview, 'src/REST/InspectorRoute.php');
const sqlAdjustment = read(roots.gravityview, 'vendor_prefixed/gravitykit/query-filters/src/Sql/SqlAdjustmentCallbacks.php');
const gfApi = read(roots.gravityforms, 'includes/api.php');
const gfRestEntries = read(roots.gravityforms, 'includes/webapi/v2/includes/controllers/class-controller-form-entries.php');

const targetFieldContracts = evaluateTargetFieldContracts(dateCreated.content, dateUpdated.content);
assertTargetFieldContracts(targetFieldContracts);
const independentFailClosed = proveIndependentTargetFailClosed(dateCreated.content, dateUpdated.content);

const sourceContract = {
  ...targetFieldContracts,
  formatting: {
    gv_common_format_date_uses_gf_local_timestamp: /function\s+format_date\s*\([\s\S]{0,2200}GFCommon::get_local_timestamp\(\s*\$date_gmt_time\s*\)/.test(gvCommon.content),
    gv_common_format_date_reads_gmt_timestamp: /function\s+format_date\s*\([\s\S]{0,1800}mysql2date\(\s*'G'\s*,\s*\$date_string\s*\)/.test(gvCommon.content),
  },
  presentation_seam: {
    field_specific_output_filter: templateField.content.includes('apply_filters( "gravityview/template/field/{$field->type}/output", $output, $context )'),
    generic_output_filter: templateField.content.includes("apply_filters( 'gravityview/template/field/output', $output, $context )"),
    context_exposes_entry_to_compat_path: templateField.content.includes('$context->entry->as_entry()'),
  },
  query_path: {
    filters_merge_to_search_criteria: formGf.content.includes('$search_criteria = $filter::merge_search_criteria( $search_criteria, $filter->as_search_criteria() );'),
    sorts_translate_raw_field_id_direction_and_numeric_mode: /foreach\s*\(\s*\$sorts\s+as\s+\$sort\s*\)[\s\S]{0,420}\$sorting\s*=\s*\[[\s\S]{0,220}'key'\s*=>\s*\$sort->field->ID[\s\S]{0,180}'direction'\s*=>\s*\$sort->direction[\s\S]{0,180}'is_numeric'\s*=>\s*\\GV\\Entry_Sort::NUMERIC\s*==\s*\$sort->mode/.test(formGf.content),
    gfapi_consumes_search_and_sort: /GFAPI::get_entries\([\s\S]{0,260}\$search_criteria[\s\S]{0,120}\$sorting/.test(formGf.content),
    date_created_search_policy_marks_utc_storage: searchPolicy.content.includes('date_created') && searchPolicy.content.includes('stored in UTC format'),
    query_filter_handles_date_created_in_utc: queryVisitor.content.includes("'date_created'") && queryVisitor.content.includes("new DateTimeZone( 'UTC' )"),
    search_widget_builds_query_filters_from_request: searchWidget.content.includes('SearchRequest::from_request') && searchWidget.content.includes('SearchFilterBuilder::to_query_filters'),
    search_scope_request_key_is_present: searchScope.content.includes('gv_search_view'),
    date_updated_raw_sql_identity_is_preserved: sqlAdjustment.content.includes('date_updated') && sqlAdjustment.content.includes('date_created') && sqlAdjustment.content.includes('UNIX_TIMESTAMP'),
    search_bar_exposes_entry_date_not_direct_system_date_slots: searchFieldCollection.content.includes('new Search_Field_Entry_Date()') && searchFieldEntryDate.content.includes("get_request_value( 'gv_start'") && searchWidget.content.includes('gravityview_get_form_fields( $form_id, true, true )'),
    request_parser_accepts_registered_meta_filter_keys: searchRequest.content.includes("(?:filter|input)_") && searchRequest.content.includes('FieldRegistry::get_all()'),
    host_search_bar_api_validates_searchable_field_identity: inspectorRoute.content.includes('function add_search_bar') && inspectorRoute.content.includes('assert_searchable_id') && inspectorRoute.content.includes('gv_rest_invalid_search_input'),
  },
  gravityforms_raw_contract: {
    date_created_utc_y_m_d_h_i_s: gfApi.content.includes("The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC)."),
    update_entry_property_api_present: /function\s+update_entry_property\s*\(/.test(gfApi.content),
    date_updated_rest_contract_explicitly_utc: gfRestEntries.content.includes('The date the entry was updated, in UTC.'),
    add_entry_defaults_date_updated_to_utc_timestamp: /\$date_updated\s*=\s*isset\(\s*\$entry\['date_updated'\]\s*\)[\s\S]{0,220}utc_timestamp\(\)/.test(gfApi.content),
  },
};

if (!allTrue(sourceContract)) {
  throw new Error(`Exact GravityView/Gravity Forms source contract drifted: ${JSON.stringify(sourceContract)}`);
}

const provenance = {
  date_created_field: sourceLocation(
    dateCreated,
    'date_created',
    [
      'date_created.field_name',
      'date_created.searchable',
      'date_created.operators',
      'date_created.contexts',
    ],
  ),
  date_created_renderer: sourceLocation(
    dateCreated,
    'get_content',
    ['date_created.get_content_reads_raw_field_value'],
    'GVCommon::format_date',
  ),
  date_updated_field: sourceLocation(
    dateUpdated,
    'class DateUpdated',
    [
      'date_updated.extends_date_created',
      'date_updated.field_name',
      'date_updated.searchable',
      'date_updated.operators',
      'date_updated.contexts',
    ],
    'date_updated',
  ),
  template_field_output: sourceLocation(
    templateField,
    'gravityview/template/field/{$field->type}/output',
    [
      'presentation_seam.field_specific_output_filter',
      'presentation_seam.generic_output_filter',
      'presentation_seam.context_exposes_entry_to_compat_path',
    ],
  ),
  gv_format_date: sourceLocation(
    gvCommon,
    'function format_date',
    [
      'formatting.gv_common_format_date_uses_gf_local_timestamp',
      'formatting.gv_common_format_date_reads_gmt_timestamp',
    ],
    'GFCommon::get_local_timestamp',
  ),
  gravityview_gfapi_query_bridge: sourceLocation(
    formGf,
    '$filter::merge_search_criteria',
    [
      'query_path.filters_merge_to_search_criteria',
      'query_path.sorts_translate_raw_field_id_direction_and_numeric_mode',
      'query_path.gfapi_consumes_search_and_sort',
    ],
    'GFAPI::get_entries',
  ),
  date_created_search_policy: sourceLocation(
    searchPolicy,
    'stored in UTC format',
    ['query_path.date_created_search_policy_marks_utc_storage'],
  ),
  query_filter_date_created: sourceLocation(
    queryVisitor,
    "'date_created'",
    ['query_path.query_filter_handles_date_created_in_utc'],
    "new DateTimeZone( 'UTC' )",
  ),
  search_widget_query_builder: sourceLocation(
    searchWidget,
    'SearchRequest::from_request',
    ['query_path.search_widget_builds_query_filters_from_request'],
    'SearchFilterBuilder::to_query_filters',
  ),
  search_scope: sourceLocation(
    searchScope,
    'gv_search_view',
    ['query_path.search_scope_request_key_is_present'],
  ),
  date_updated_raw_sql_adjustment: sourceLocation(
    sqlAdjustment,
    'date_updated',
    ['query_path.date_updated_raw_sql_identity_is_preserved'],
    'UNIX_TIMESTAMP',
  ),
  search_field_collection_entry_date: sourceLocation(
    searchFieldCollection,
    'new Search_Field_Entry_Date()',
    ['query_path.search_bar_exposes_entry_date_not_direct_system_date_slots'],
  ),
  search_field_entry_date_request_keys: sourceLocation(
    searchFieldEntryDate,
    "get_request_value( 'gv_start'",
    ['query_path.search_bar_exposes_entry_date_not_direct_system_date_slots'],
  ),
  search_request_parser: sourceLocation(
    searchRequest,
    'FieldRegistry::get_all()',
    ['query_path.request_parser_accepts_registered_meta_filter_keys'],
  ),
  search_bar_host_api: sourceLocation(
    inspectorRoute,
    'function add_search_bar',
    ['query_path.host_search_bar_api_validates_searchable_field_identity'],
    'gv_rest_invalid_search_input',
  ),
  gravityforms_date_created_contract: sourceLocation(
    gfApi,
    'date_created value',
    ['gravityforms_raw_contract.date_created_utc_y_m_d_h_i_s'],
  ),
  gravityforms_update_entry_property: sourceLocation(
    gfApi,
    'function update_entry_property',
    ['gravityforms_raw_contract.update_entry_property_api_present'],
  ),
  gravityforms_date_updated_utc_rest_contract: sourceLocation(
    gfRestEntries,
    'date the entry was updated',
    ['gravityforms_raw_contract.date_updated_rest_contract_explicitly_utc'],
  ),
  gravityforms_add_entry_date_updated_utc_default: sourceLocation(
    gfApi,
    '$date_updated',
    ['gravityforms_raw_contract.add_entry_defaults_date_updated_to_utc_timestamp'],
    'utc_timestamp()',
  ),
};

const evidence = {
  schema_version: '3.0.0',
  evidence_class: 'G008_GRAVITYVIEW_METADATA_ONLY_SOURCE_PROBE',
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_gravityforms_version: process.env.WU008_GF_VERSION || null,
  exact_gravityforms_package_sha256: expectedGfSha,
  exact_persiangravity_head: expectedHead,
  source_contract: sourceContract,
  independent_fail_closed: independentFailClosed,
  evidence_boundary: {
    metadata_only: true,
    raw_source_persisted: false,
  },
  provenance,
};

assertMetadataOnlySourceEvidence(evidence);
const serialized = JSON.stringify(evidence, null, 2) + '\n';
assertMetadataOnlySourceEvidence(JSON.parse(serialized));

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-source-probe.json'), serialized);
console.log('GravityView G-008 exact source contract proven; persisted evidence is metadata-only.');
