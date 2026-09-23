import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!wpPath || !artifactDir) {
  throw new Error('WU008_WP_PATH and WU008_ARTIFACT_DIR are required.');
}

const exactPersianGravityIdentity = {
  commit: process.env.WU008_PGR_SHA || null,
  tree: process.env.WU008_PGR_TREE || null,
  package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
};
if (!/^[a-f0-9]{40}$/.test(exactPersianGravityIdentity.commit ?? '')) {
  throw new Error('WU008_PGR_SHA must bind source discovery to the exact PersianGravity Head.');
}
if (!/^[a-f0-9]{40}$/.test(exactPersianGravityIdentity.tree ?? '')) {
  throw new Error('WU008_PGR_TREE must bind source discovery to the exact PersianGravity tree.');
}
if (!/^[a-f0-9]{64}$/.test(exactPersianGravityIdentity.package_sha256 ?? '')) {
  throw new Error('WU008_PGR_PACKAGE_SHA256 must bind source discovery to the exact PersianGravity package.');
}

const roots = {
  gravityforms: path.join(wpPath, 'wp-content/plugins/gravityforms'),
  gravityflow: path.join(wpPath, 'wp-content/plugins/gravityflow'),
  gravityview: path.join(wpPath, 'wp-content/plugins/gravityview'),
};

for (const [product, root] of Object.entries(roots)) {
  if (!fs.existsSync(root)) throw new Error(`Missing exact installed source root for ${product}: ${root}`);
}

function walk(root) {
  const out = [];
  for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
    const full = path.join(root, entry.name);
    if (entry.isDirectory()) out.push(...walk(full));
    else if (/\.(php|js|css)$/i.test(entry.name)) out.push(full);
  }
  return out.sort();
}

function find(root, needles) {
  const results = {};
  for (const needle of needles) results[needle] = [];
  for (const file of walk(root)) {
    const relative = path.relative(root, file).replaceAll(path.sep, '/');
    const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
    for (let i = 0; i < lines.length; i += 1) {
      for (const needle of needles) {
        if (!lines[i].includes(needle)) continue;
        const operation = /wp_enqueue_style\s*\(/.test(lines[i])
          ? 'wp_enqueue_style'
          : /wp_register_style\s*\(/.test(lines[i])
            ? 'wp_register_style'
            : /apply_filters\s*\(/.test(lines[i])
              ? 'apply_filters'
              : /add_filter\s*\(/.test(lines[i])
                ? 'add_filter'
                : 'reference';
        results[needle].push({ file: relative, line: i + 1, operation });
      }
    }
  }
  return results;
}

function inspectSemanticTokens(file, needles, tokens, radius = 12) {
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
  const result = {};

  for (const needle of needles) {
    const occurrences = [];
    for (let i = 0; i < lines.length; i += 1) {
      if (!lines[i].includes(needle)) continue;
      const start = Math.max(0, i - radius);
      const end = Math.min(lines.length, i + radius + 1);
      const window = lines.slice(start, end).join('\n');
      const present = {};
      for (const token of tokens) {
        present[token] = window.includes(token);
      }
      occurrences.push({
        line: i + 1,
        window_start: start + 1,
        window_end: end,
        tokens: present,
      });
    }
    result[needle] = occurrences;
  }

  return result;
}

function requireSourceFile(root, relative, label) {
  const file = path.join(root, relative);
  if (!fs.existsSync(file)) throw new Error(`${label} path drifted: ${relative}`);
  return { file, relative, content: fs.readFileSync(file, 'utf8') };
}

function allContractValuesTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allContractValuesTrue);
  return true;
}

const flowNeedles = [
  'gravityflow_inbox_field_value',
  'date_created_human_readable',
  'last_updated_human_readable',
  'due_date_human_readable',
  'gravityflow_status_args',
  'gravityflow_entry_url_status_table',
  'gravityflow_field_value_status_table',
  'workflow_timestamp',
  'date_created',
  'last_updated',
  'due_date',
  'gravityflow_print_styles',
  'timeline',
  'gform_admin',
];
const viewNeedles = [
  'date_created',
  'date_updated',
  'apply_filters(',
];
const formsNeedles = [
  'gform_admin',
  'admin.min.css',
  'wp_register_style(',
  'wp_enqueue_style(',
];

const raw = {
  gravityforms: find(roots.gravityforms, formsNeedles),
  gravityflow: find(roots.gravityflow, flowNeedles),
  gravityview: find(roots.gravityview, viewNeedles),
};

function found(product, needle) {
  return (raw[product][needle] || []).length > 0;
}

const flowInboxTask = requireSourceFile(roots.gravityflow, 'includes/inbox/models/class-task.php', 'Exact Gravity Flow Inbox task model');
const flowCommon = requireSourceFile(roots.gravityflow, 'includes/class-common.php', 'Exact Gravity Flow common formatter');
const flowMain = requireSourceFile(roots.gravityflow, 'class-gravity-flow.php', 'Exact Gravity Flow entry-meta authority');
const gfApi = requireSourceFile(roots.gravityforms, 'includes/api.php', 'Exact Gravity Forms Entry API contract');
const gfCommon = requireSourceFile(roots.gravityforms, 'common.php', 'Exact Gravity Forms date formatter');
const flowStatus = requireSourceFile(roots.gravityflow, 'includes/pages/class-status.php', 'Exact Gravity Flow Status table');

const flowInboxSemanticTokens = [
  "$entry['date_created']",
  "$entry['workflow_timestamp']",
  "$entry['date_updated']",
  'date_created_human_readable',
  'last_updated_human_readable',
  'Gravity_Flow_Common::format_date',
  'get_date_from_gmt',
  'get_gmt_from_date',
  'wp_date',
  'date_i18n',
  'gmdate',
  'current_time',
  'strtotime',
  "get_option( 'date_format'",
  "get_option( 'time_format'",
  'get_date_format',
  'get_time_format',
  'gravityflow_inbox_field_value',
];
const flowInboxSourceProbe = inspectSemanticTokens(
  flowInboxTask.file,
  ['date_created_human_readable', 'last_updated_human_readable', 'gravityflow_inbox_field_value'],
  flowInboxSemanticTokens
);

const flowInboxSourceContract = {
  paths: {
    inbox_task_model: flowInboxTask.relative,
    flow_common_formatter: flowCommon.relative,
    flow_entry_meta_authority: flowMain.relative,
    gravityforms_entry_api: gfApi.relative,
    gravityforms_common_formatter: gfCommon.relative,
  },
  date_created: {
    gravityforms_entry_contract_is_utc_y_m_d_h_i_s: gfApi.content.includes("The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC)."),
    inbox_display_reads_entry_date_created: /case\s+'date_created_human_readable':[\s\S]{0,260}Gravity_Flow_Common::format_date\(\s*\$entry\['date_created'\]/.test(flowInboxTask.content),
    inbox_raw_compare_reads_same_entry_date_created: /case\s+'date_created':[\s\S]{0,180}strtotime\(\s*\$entry\['date_created'\]\s*\)/.test(flowInboxTask.content),
    raw_and_display_are_separate_column_identities: /'date_created'[\s\S]{0,180}'displayKey'\s*=>\s*'date_created_human_readable'/.test(flowInboxTask.content),
  },
  last_updated: {
    workflow_timestamp_is_numeric_entry_meta: /\$entry_meta\['workflow_timestamp'\]\s*=\s*array\([\s\S]{0,260}'is_numeric'\s*=>\s*true/.test(flowMain.content),
    workflow_timestamp_callback_returns_epoch: /function\s+callback_update_entry_meta_timestamp[\s\S]{0,500}strtotime\(\s*\$entry\['date_created'\]\s*\)\s*:\s*time\(\)/.test(flowMain.content),
    inbox_display_reads_workflow_timestamp: /case\s+'last_updated_human_readable':[\s\S]{0,220}date\(\s*'Y-m-d H:i:s',\s*\$entry\['workflow_timestamp'\]\s*\)/.test(flowInboxTask.content),
    inbox_raw_compare_reads_same_workflow_timestamp: /case\s+'last_updated':[\s\S]{0,160}\(int\)\s*\$entry\['workflow_timestamp'\]/.test(flowInboxTask.content),
    raw_and_display_are_separate_column_identities: /'last_updated'[\s\S]{0,180}'displayKey'\s*=>\s*'last_updated_human_readable'/.test(flowInboxTask.content),
  },
  timezone_and_formatting: {
    flow_numeric_timestamp_uses_php_date_intermediate: /is_numeric\(\s*\$date_or_timestamp\s*\)\s*\?\s*date\(\s*'Y-m-d H:i:s',\s*\$date_or_timestamp\s*\)/.test(flowCommon.content),
    flow_delegates_to_gravityforms_formatter: flowCommon.content.includes('return GFCommon::format_date( $date_time, $is_human, $format, $include_time );'),
    gravityforms_formatter_declares_utc_input: gfCommon.content.includes('@param string $gmt_datetime The UTC date/time value to be formatted.'),
    gravityforms_formatter_localizes_before_display: gfCommon.content.includes('$local_time = self::get_local_timestamp( $gmt_time );'),
  },
  presentation_seam: {
    filter_receives_display_form_id_field_id_and_entry: /apply_filters\(\s*'gravityflow_inbox_field_value',\s*\$value,\s*\$form\['id'\],\s*\$id,\s*\$entry\s*\)/.test(flowInboxTask.content),
  },
};

if (!allContractValuesTrue(flowInboxSourceContract)) {
  throw new Error(`Exact Gravity Flow Inbox source/timezone contract drifted: ${JSON.stringify(flowInboxSourceContract)}`);
}

const flowStatusExportStart = flowStatus.content.indexOf('public function export()');
const flowStatusExportEnd = flowStatus.content.indexOf('public function sanitize_date', flowStatusExportStart);
const flowStatusExportSource = (
  flowStatusExportStart >= 0 &&
  flowStatusExportEnd > flowStatusExportStart
)
  ? flowStatus.content.slice(flowStatusExportStart, flowStatusExportEnd)
  : '';

const flowStatusSourceContract = {
  paths: {
    status_table: flowStatus.relative,
    flow_common_formatter: flowCommon.relative,
    flow_entry_meta_authority: flowMain.relative,
    gravityforms_entry_api: gfApi.relative,
    gravityforms_common_formatter: gfCommon.relative,
  },
  date_created: {
    gravityforms_entry_contract_is_utc_y_m_d_h_i_s: gfApi.content.includes("The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC)."),
    status_column_reads_entry_date_created: /function\s+column_date_created[\s\S]{0,650}Gravity_Flow_Common::format_date\(\s*\$item\['date_created'\]/.test(flowStatus.content),
    status_column_filters_as_date_created: /function\s+column_date_created[\s\S]{0,900}filter_field_value\(\s*\$label,\s*\$item,\s*'date_created'\s*\)/.test(flowStatus.content),
  },
  workflow_timestamp: {
    workflow_timestamp_is_numeric_entry_meta: /\$entry_meta\['workflow_timestamp'\]\s*=\s*array\([\s\S]{0,260}'is_numeric'\s*=>\s*true/.test(flowMain.content),
    workflow_timestamp_callback_returns_epoch: /function\s+callback_update_entry_meta_timestamp[\s\S]{0,500}strtotime\(\s*\$entry\['date_created'\]\s*\)\s*:\s*time\(\)/.test(flowMain.content),
    status_column_reads_workflow_timestamp: /function\s+column_workflow_timestamp[\s\S]{0,700}Gravity_Flow_Common::format_date\(\s*\$item\['workflow_timestamp'\]/.test(flowStatus.content),
    status_column_filters_as_workflow_timestamp: /function\s+column_workflow_timestamp[\s\S]{0,1000}filter_field_value\(\s*\$last_updated,\s*\$item,\s*'workflow_timestamp'\s*\)/.test(flowStatus.content),
  },
  timezone_and_formatting: {
    flow_numeric_timestamp_uses_php_date_intermediate: /is_numeric\(\s*\$date_or_timestamp\s*\)\s*\?\s*date\(\s*'Y-m-d H:i:s',\s*\$date_or_timestamp\s*\)/.test(flowCommon.content),
    flow_delegates_to_gravityforms_formatter: flowCommon.content.includes('return GFCommon::format_date( $date_time, $is_human, $format, $include_time );'),
    gravityforms_formatter_declares_utc_input: gfCommon.content.includes('@param string $gmt_datetime The UTC date/time value to be formatted.'),
    gravityforms_formatter_localizes_before_display: gfCommon.content.includes('$local_time = self::get_local_timestamp( $gmt_time );'),
  },
  presentation_vs_export_context: {
    exact_status_table_class: /class\s+Gravity_Flow_Status_Table\s+extends\s+WP_List_Table/.test(flowStatus.content),
    status_args_filter_receives_normalized_defaults: /function\s+render\s*\(\s*\$args\s*=\s*array\(\)\s*\)[\s\S]{0,1200}\$args\s*=\s*array_merge\(\s*self::get_defaults\(\),\s*\$args\s*\)[\s\S]{0,1800}\$args\s*=\s*apply_filters\(\s*'gravityflow_status_args',\s*\$args\s*\)/.test(flowStatus.content),
    status_format_branches_table_vs_export_after_context_filter: /apply_filters\(\s*'gravityflow_status_args',\s*\$args\s*\)[\s\S]{0,1200}if\s*\(\s*\$args\['format'\]\s*==\s*'table'\s*\)[\s\S]{0,400}self::status_page\(\s*\$args\s*\)[\s\S]{0,400}self::process_export\(\s*\$args\s*\)/.test(flowStatus.content),
    entry_url_filter_is_table_only_proof_seam: /function\s+get_entry_url\s*\([\s\S]{0,700}apply_filters\(\s*'gravityflow_entry_url_status_table',\s*\$entry_url,\s*\$entry\['form_id'\],\s*\$entry\['id'\],\s*\$entry\s*\)/.test(flowStatus.content),
    date_created_entry_url_precedes_value_filter: /function\s+column_date_created[\s\S]{0,500}get_entry_url\(\s*\$item\s*\)[\s\S]{0,500}filter_field_value\(\s*\$label,\s*\$item,\s*'date_created'\s*\)/.test(flowStatus.content),
    workflow_timestamp_entry_url_precedes_value_filter: /function\s+column_workflow_timestamp[\s\S]{0,700}get_entry_url\(\s*\$item\s*\)[\s\S]{0,500}filter_field_value\(\s*\$last_updated,\s*\$item,\s*'workflow_timestamp'\s*\)/.test(flowStatus.content),
    table_wrapper_applies_status_filter: /function\s+filter_field_value[\s\S]{0,700}apply_filters\(\s*'gravityflow_field_value_status_table',\s*\$value,\s*\$form_id,\s*\$column_name,\s*\$entry\s*\)/.test(flowStatus.content),
    export_applies_status_filter_directly: /function\s+export\s*\([\s\S]{0,9000}apply_filters\(\s*'gravityflow_field_value_status_table',\s*\$col_val,\s*\$item\['form_id'\],\s*\$column_key,\s*\$item\s*\)/.test(flowStatus.content),
    export_value_apply_has_no_entry_url: flowStatusExportSource.includes("apply_filters( 'gravityflow_field_value_status_table'") && !flowStatusExportSource.includes('get_entry_url('),
    exact_status_filter_apply_sites: (flowStatus.content.match(/apply_filters\(\s*'gravityflow_field_value_status_table'/g) || []).length === 2,
    ajax_export_selects_csv_format: /function\s+ajax_export_status[\s\S]{0,1800}\$args\['format'\]\s*=\s*'csv'[\s\S]{0,800}Gravity_Flow_Status::render\(\s*\$args\s*\)/.test(flowMain.content),
  },
  operational_channels: {
    date_created_sort_uses_raw_column_key: /'date_created'\s*=>\s*array\(\s*'date_created',\s*false\s*\)/.test(flowStatus.content),
    workflow_timestamp_sort_uses_raw_meta_key: /\$sortable_columns\['workflow_timestamp'\]\s*=\s*array\(\s*'workflow_timestamp',\s*false\s*\)/.test(flowStatus.content),
    sorting_passes_raw_orderby_to_gfapi: /\$sorting\s*=\s*array\(\s*'key'\s*=>\s*\$orderby,\s*'direction'\s*=>\s*\$order\s*\)[\s\S]{0,1800}GFAPI::get_entries\(\s*\$form_ids,\s*\$search_criteria,\s*\$sorting,/.test(flowStatus.content),
    start_filter_compares_raw_date_created: /function\s+get_start_clause[\s\S]{0,500}l\.date_created\s*>=\s*%s/.test(flowStatus.content),
    end_filter_compares_raw_date_created: /function\s+get_end_clause[\s\S]{0,500}l\.date_created\s*<=\s*%s/.test(flowStatus.content),
    start_filter_converts_site_civil_to_gmt: /function\s+prepare_start_date_gmt[\s\S]{0,700}get_gmt_from_date\(\s*\$start_date_str\s*\)/.test(flowStatus.content),
    end_filter_converts_site_civil_to_gmt: /function\s+prepare_end_date_gmt[\s\S]{0,1000}get_gmt_from_date\(\s*\$end_date\s*\)/.test(flowStatus.content),
  },
};

if (!allContractValuesTrue(flowStatusSourceContract)) {
  throw new Error(`Exact Gravity Flow Status source/timezone/context contract drifted: ${JSON.stringify(flowStatusSourceContract)}`);
}

const classifications = {
  gform_admin: {
    gravityflow_source_reference: found('gravityflow', 'gform_admin'),
    gravityforms_source_reference: found('gravityforms', 'gform_admin'),
    gravityforms_admin_css_reference: found('gravityforms', 'admin.min.css'),
    source_disposition: found('gravityflow', 'gform_admin') && found('gravityforms', 'gform_admin')
      ? 'SOURCE_REACHABILITY_SUPPORTED_RUNTIME_CAUSALITY_REQUIRED'
      : 'NOT_PROVEN',
  },
  g008: {
    flow_inbox: {
      display_filter: found('gravityflow', 'gravityflow_inbox_field_value'),
      date_created_human_readable: found('gravityflow', 'date_created_human_readable'),
      last_updated_human_readable: found('gravityflow', 'last_updated_human_readable'),
      due_date_human_readable: found('gravityflow', 'due_date_human_readable'),
      task_model_path: flowInboxTask.relative,
      source_semantics_probe: flowInboxSourceProbe,
      source_contract: flowInboxSourceContract,
      discovery_state: found('gravityflow', 'gravityflow_inbox_field_value')
        && found('gravityflow', 'date_created_human_readable')
        && found('gravityflow', 'last_updated_human_readable')
        ? 'SOURCE_PROVEN_CANDIDATE'
        : 'NOT_PROVEN',
    },
    flow_status: {
      display_filter: found('gravityflow', 'gravityflow_field_value_status_table'),
      workflow_timestamp: found('gravityflow', 'workflow_timestamp'),
      date_created: found('gravityflow', 'date_created'),
      status_table_path: flowStatus.relative,
      source_contract: flowStatusSourceContract,
      discovery_state: found('gravityflow', 'gravityflow_field_value_status_table')
        && found('gravityflow', 'workflow_timestamp')
        && found('gravityflow', 'date_created')
        ? 'SOURCE_PROVEN_CANDIDATE'
        : 'NOT_PROVEN',
    },
    flow_due_schedule_history_print: {
      due_date: found('gravityflow', 'due_date'),
      print_style_hook: found('gravityflow', 'gravityflow_print_styles'),
      timeline_reference: found('gravityflow', 'timeline'),
      discovery_state: 'NOT_PROVEN',
    },
    gravityview: {
      date_created: found('gravityview', 'date_created'),
      date_updated: found('gravityview', 'date_updated'),
      output_filter_inventory_present: found('gravityview', 'apply_filters('),
      discovery_state: found('gravityview', 'date_created') && found('gravityview', 'date_updated')
        ? 'SOURCE_PRESENT_SEAM_NOT_PROVEN'
        : 'NOT_PROVEN',
    },
  },
};

const evidence = {
  schema_version: '1.6.0',
  evidence_class: 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY',
  exact_persiangravity_commit: exactPersianGravityIdentity.commit,
  exact_persiangravity_tree: exactPersianGravityIdentity.tree,
  exact_persiangravity_package_sha256: exactPersianGravityIdentity.package_sha256,
  exact_versions: {
    gravityforms: process.env.WU008_GF_VERSION || null,
    gravityflow: process.env.WU008_FLOW_VERSION || null,
    gravityview: process.env.WU008_VIEW_VERSION || null,
  },
  exact_package_sha256: {
    gravityforms: process.env.WU008_GF_SHA256 || null,
    gravityflow: process.env.WU008_FLOW_SHA256 || null,
    gravityview: process.env.WU008_VIEW_SHA256 || null,
  },
  classifications,
  references: raw,
};

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'source-discovery.json'), `${JSON.stringify(evidence, null, 2)}\n`);

for (const [key, value] of Object.entries(classifications.g008)) {
  console.log(`G008_SOURCE_DISCOVERY ${key} ${value.discovery_state}`);
}
console.log(`G009_GFORM_ADMIN_SOURCE ${classifications.gform_admin.source_disposition}`);

if (!classifications.gform_admin.gravityflow_source_reference) {
  throw new Error('Exact Gravity Flow source no longer contains the observed gform_admin reference; qualification must fail closed.');
}
