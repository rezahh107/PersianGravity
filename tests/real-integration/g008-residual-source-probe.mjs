import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!wpPath || !artifactDir) throw new Error('WU008_WP_PATH and WU008_ARTIFACT_DIR are required.');

const root = path.join(wpPath, 'wp-content/plugins/gravityflow');
const gfRoot = path.join(wpPath, 'wp-content/plugins/gravityforms');
const wpRoot = wpPath;
const exact = {
  version: process.env.WU008_FLOW_VERSION || null,
  sha256: process.env.WU008_FLOW_SHA256 || null,
  gravityformsVersion: process.env.WU008_GF_VERSION || null,
  gravityformsSha256: process.env.WU008_GF_SHA256 || null,
  pgr_sha: process.env.WU008_PGR_SHA || null,
};
if (!/^[a-f0-9]{40}$/.test(exact.pgr_sha ?? '')) throw new Error('Residual probe must bind to the exact PersianGravity Head.');
if (exact.version !== '3.1.0' || exact.sha256 !== 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404') {
  throw new Error('Residual probe requires exact Gravity Flow 3.1.0 owner-supplied package.');
}
if (exact.gravityformsVersion !== '3.1.1.1' || exact.gravityformsSha256 !== '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b') {
  throw new Error('Residual timeline authority probe requires exact Gravity Forms 3.1.1.1 package.');
}

function readFrom(base, relative) {
  const file = path.join(base, relative);
  if (!fs.existsSync(file)) throw new Error(`Missing exact source path: ${relative}`);
  return { relative, content: fs.readFileSync(file, 'utf8') };
}

function read(relative) {
  return readFrom(root, relative);
}

function windows(source, needle, radius = 20) {
  const lines = source.content.split(/\r?\n/);
  const result = [];
  for (let i = 0; i < lines.length; i += 1) {
    if (!lines[i].includes(needle)) continue;
    const start = Math.max(0, i - radius);
    const end = Math.min(lines.length, i + radius + 1);
    result.push({
      file: source.relative,
      line: i + 1,
      start: start + 1,
      end,
      source: lines.slice(start, end).map((text, index) => ({ line: start + index + 1, text })),
    });
  }
  return result;
}

function walkPhp(base, current = base) {
  const files = [];
  for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
    const full = path.join(current, entry.name);
    if (entry.isDirectory()) files.push(...walkPhp(base, full));
    else if (entry.name.endsWith('.php')) files.push(full);
  }
  return files.sort();
}

function searchPhp(base, needle, radius = 16) {
  const result = [];
  for (const file of walkPhp(base)) {
    const relative = path.relative(base, file).replaceAll(path.sep, '/');
    const source = { relative, content: fs.readFileSync(file, 'utf8') };
    result.push(...windows(source, needle, radius));
  }
  return result;
}

function sourceContaining(base, needle) {
  const matches = [];
  for (const file of walkPhp(base)) {
    const content = fs.readFileSync(file, 'utf8');
    if (content.includes(needle)) {
      matches.push({
        relative: path.relative(base, file).replaceAll(path.sep, '/'),
        content,
      });
    }
  }
  if (matches.length !== 1) {
    throw new Error(`Expected exactly one PHP source containing ${needle}, found ${matches.length}: ${matches.map((item) => item.relative).join(', ')}`);
  }
  return matches[0];
}

function methodSource(source, methodName) {
  const pattern = new RegExp('(?:public\\s+|protected\\s+|private\\s+)?(?:static\\s+)?function\\s+' + methodName + '\\s*\\(');
  const match = pattern.exec(source.content);
  if (!match) return '';
  const rest = source.content.slice(match.index + match[0].length);
  const next = /\n\s*(?:public\s+|protected\s+|private\s+)?(?:static\s+)?function\s+[A-Za-z0-9_]+\s*\(/.exec(rest);
  return source.content.slice(match.index, next ? match.index + match[0].length + next.index : source.content.length);
}

function methodContaining(source, needle) {
  const needleIndex = source.content.indexOf(needle);
  if (needleIndex < 0) return '';
  const before = source.content.slice(0, needleIndex);
  const declarations = [...before.matchAll(/(?:^|\n)\s*(?:public\s+|protected\s+|private\s+)?(?:static\s+)?function\s+[A-Za-z0-9_]+\s*\(/g)];
  if (declarations.length === 0) return '';
  const start = declarations.at(-1).index;
  const rest = source.content.slice(needleIndex);
  const next = /\n\s*(?:public\s+|protected\s+|private\s+)?(?:static\s+)?function\s+[A-Za-z0-9_]+\s*\(/.exec(rest);
  return source.content.slice(start, next ? needleIndex + next.index : source.content.length);
}

function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allTrue);
  return true;
}

const status = read('includes/pages/class-status.php');
const entryDetail = read('includes/pages/class-entry-detail.php');
const printEntries = read('includes/pages/class-print-entries.php');
const step = read('includes/steps/class-step.php');
const common = read('includes/class-common.php');
const flowMain = read('class-gravity-flow.php');
const gfFormsModel = readFrom(gfRoot, 'forms_model.php');
const gfCommonSource = sourceContaining(gfRoot, 'function format_date(');
const wpFunctions = readFrom(wpRoot, 'wp-includes/functions.php');

const statusDueMethod = methodSource(status, 'column_due_date');
const statusExportMethod = methodSource(status, 'export');
const entryWorkflowBoxMethod = methodSource(flowMain, 'workflow_entry_detail_status_box');
const entryWorkflowInfoMethod = methodContaining(flowMain, 'gravityflow-status-box-field-due-date');
const entryQueuedMethod = methodSource(flowMain, 'display_queued_step_details');
const dueGetterMethod = methodSource(step, 'get_due_date_timestamp');
const scheduleGetterMethod = methodSource(step, 'get_schedule_timestamp');
const expirationGetterMethod = methodSource(step, 'get_expiration_timestamp');
const overdueMethod = methodSource(step, 'is_overdue');
const expiredMethod = methodSource(step, 'is_expired');
const validateScheduleMethod = methodSource(step, 'validate_schedule');
const timestampDateMethod = methodSource(step, 'get_timestamp_date');
const timestampDateFieldMethod = methodSource(step, 'get_timestamp_date_field');
const timestampDelayMethod = methodSource(step, 'get_timestamp_delay');
const stepTimestampMethod = methodSource(step, 'get_step_timestamp');
const entryStepStatusMethod = methodSource(flowMain, 'maybe_display_entry_detail_step_status');
const noteHeaderMethod = methodSource(entryDetail, 'get_note_header');
const noteBodyMethod = methodSource(entryDetail, 'get_note_body');
const timelineNotesMethod = methodSource(common, 'get_timeline_notes');
const initialNoteMethod = methodSource(common, 'get_initial_note');
const commonTimelineMethod = methodSource(common, 'get_timeline');
const printRenderMethod = methodSource(printEntries, 'render');
const flowFormatDateMethod = methodSource(common, 'format_date');
const gfFormatDateMethod = methodSource(gfCommonSource, 'format_date');
const wpDateI18nMethod = methodSource(wpFunctions, 'date_i18n');

const sourceContract = {
  status_due_date: {
    table_reads_operational_due_getter_directly: statusDueMethod.includes('get_due_date_timestamp()'),
    table_formats_due_inside_column_method: statusDueMethod.includes('Gravity_Flow_Common::format_date'),
    table_echoes_direct_output: statusDueMethod.includes('echo $output;'),
    table_native_empty_uses_dash_entity: statusDueMethod.includes("'&dash;'") || statusDueMethod.includes('&dash;'),
    table_has_no_status_value_filter: !statusDueMethod.includes('gravityflow_field_value_status_table') && !statusDueMethod.includes('filter_field_value('),
    table_has_no_entry_url_proof_seam: !statusDueMethod.includes('get_entry_url('),
    export_has_separate_due_branch: statusExportMethod.includes("case 'due_date':") && statusExportMethod.includes('get_due_date_timestamp()'),
    export_uses_generic_status_filter: statusExportMethod.includes("gravityflow_field_value_status_table"),
    due_getter_is_operational_filter: dueGetterMethod.includes("apply_filters( 'gravityflow_step_due_date_timestamp'"),
    overdue_uses_same_due_getter: overdueMethod.includes('get_due_date_timestamp()') && overdueMethod.includes('time()'),
  },
  entry_detail_schedule_due_expiration: {
    exact_status_box_wrapper:
      entryWorkflowBoxMethod.includes('<div id="gravityflow-status-box-container" class="postbox">')
      && entryWorkflowBoxMethod.includes('<div id="minor-publishing" class="gravityflow-status-box">'),
    workflow_info_precedes_step_status_in_exact_wrapper:
      entryWorkflowBoxMethod.indexOf('maybe_display_entry_detail_workflow_info') >= 0
      && entryWorkflowBoxMethod.indexOf('maybe_display_entry_detail_workflow_info') < entryWorkflowBoxMethod.indexOf('maybe_display_entry_detail_step_status'),
    workflow_info_uses_bounded_human_field_nodes:
      entryWorkflowInfoMethod.includes('gravityflow-status-box-field-entry-id')
      && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-submitted-time')
      && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-last-updated')
      && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-due-date')
      && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-expires')
      && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-value'),
    entry_id_href_keeps_ascii_numeric_authority:
      entryWorkflowInfoMethod.includes("admin.php?page=gf_entries&view=entry&id=")
      && entryWorkflowInfoMethod.includes("'&lid=' . absint( $entry['id'] )")
      && /\$entry_id\s*=\s*absint\( \$entry\['id'\] \)/.test(entryWorkflowInfoMethod),
    below_workflow_info_hook_is_supported_post_value_seam:
      entryWorkflowInfoMethod.includes("do_action( 'gravityflow_below_workflow_info_entry_detail', $form, $entry, $current_step )")
      && entryWorkflowInfoMethod.indexOf("do_action( 'gravityflow_below_workflow_info_entry_detail'") > entryWorkflowInfoMethod.indexOf('gravityflow-status-box-field-expires'),
    queued_step_reuses_bounded_human_field_nodes:
      entryQueuedMethod.includes('gravityflow-status-box-field-step-name')
      && entryQueuedMethod.includes('gravityflow-status-box-field-scheduled-date')
      && entryQueuedMethod.includes('gravityflow-status-box-field-value'),
    workflow_info_exposes_format_pattern_filter_only: entryWorkflowInfoMethod.includes("apply_filters( 'gravityflow_date_format_entry_detail', '' )"),
    format_pattern_filter_precedes_due_and_expiration: entryWorkflowInfoMethod.indexOf("apply_filters( 'gravityflow_date_format_entry_detail', '' )") >= 0
      && entryWorkflowInfoMethod.indexOf("apply_filters( 'gravityflow_date_format_entry_detail', '' )") < entryWorkflowInfoMethod.indexOf('get_due_date_timestamp()')
      && entryWorkflowInfoMethod.indexOf("apply_filters( 'gravityflow_date_format_entry_detail', '' )") < entryWorkflowInfoMethod.indexOf('get_expiration_timestamp()'),
    due_is_direct_operational_getter_render: entryWorkflowInfoMethod.includes('get_due_date_timestamp()') && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-due-date') && entryWorkflowInfoMethod.includes("'Due Date'"),
    expiration_is_direct_operational_getter_render: entryWorkflowInfoMethod.includes('get_expiration_timestamp()') && entryWorkflowInfoMethod.includes('gravityflow-status-box-field-expires') && entryWorkflowInfoMethod.includes("'Expires'"),
    below_workflow_hook_is_after_direct_date_output: entryWorkflowInfoMethod.indexOf('get_expiration_timestamp()') >= 0 && entryWorkflowInfoMethod.indexOf("do_action( 'gravityflow_below_workflow_info_entry_detail'") > entryWorkflowInfoMethod.indexOf('get_expiration_timestamp()'),
    date_format_hook_is_format_string_only_and_precedes_operational_values: entryWorkflowInfoMethod.includes("$date_format = apply_filters( 'gravityflow_date_format_entry_detail', '' );")
      && entryWorkflowInfoMethod.indexOf("gravityflow_date_format_entry_detail") < entryWorkflowInfoMethod.indexOf('get_due_date_timestamp()')
      && entryWorkflowInfoMethod.indexOf("gravityflow_date_format_entry_detail") < entryWorkflowInfoMethod.indexOf('get_expiration_timestamp()'),
    due_has_no_downstream_value_filter: !entryWorkflowInfoMethod.slice(
      entryWorkflowInfoMethod.indexOf('get_due_date_timestamp()'),
      entryWorkflowInfoMethod.indexOf("'Due Date'")
    ).includes('apply_filters('),
    expiration_has_no_downstream_value_filter: !entryWorkflowInfoMethod.slice(
      entryWorkflowInfoMethod.indexOf('get_expiration_timestamp()'),
      entryWorkflowInfoMethod.indexOf("'Expires'")
    ).includes('apply_filters('),
    schedule_reads_operational_getter_directly: entryQueuedMethod.includes('get_schedule_timestamp()'),
    schedule_prints_directly: entryQueuedMethod.includes('gravityflow-status-box-field-scheduled-date') && entryQueuedMethod.includes("'Scheduled'"),
    schedule_has_no_value_filter: !entryQueuedMethod.includes('apply_filters('),
    schedule_getter_is_operational_filter: scheduleGetterMethod.includes("apply_filters( 'gravityflow_step_schedule_timestamp'"),
    expiration_getter_is_operational_filter: expirationGetterMethod.includes("apply_filters( 'gravityflow_step_expiration_timestamp'"),
    schedule_validation_uses_same_getter: validateScheduleMethod.includes('get_schedule_timestamp()') && validateScheduleMethod.includes('time()'),
    expiration_state_uses_same_getter: expiredMethod.includes('get_expiration_timestamp()') && expiredMethod.includes('time()'),
    shared_format_hook_scopes_submitted_last_updated_due_expiration:
      entryWorkflowInfoMethod.includes("Gravity_Flow_Common::format_date( $entry['date_created'], $date_format, false, true )")
      && entryWorkflowInfoMethod.includes("Gravity_Flow_Common::format_date( $entry['workflow_timestamp'], $date_format, false, true )")
      && entryWorkflowInfoMethod.includes('Gravity_Flow_Common::format_date( $current_step->get_due_date_timestamp(), $date_format, false, false )')
      && entryWorkflowInfoMethod.includes('Gravity_Flow_Common::format_date( $current_step->get_expiration_timestamp(), $date_format, false, true )'),
    flow_format_date_delegates_to_gravityforms: flowFormatDateMethod.includes('GFCommon::format_date'),
    gravityforms_format_date_reaches_date_i18n: gfFormatDateMethod.includes('date_i18n('),
    wordpress_date_i18n_exposes_supported_filter: wpDateI18nMethod.includes("apply_filters( 'date_i18n'"),
    wordpress_date_i18n_treats_numeric_input_as_local_timestamp_with_offset:
      wpDateI18nMethod.includes("gmdate( 'Y-m-d H:i:s', $timestamp )")
      && wpDateI18nMethod.includes('date_create( $local_time, $timezone )')
      && wpDateI18nMethod.includes('wp_timezone()'),
    schedule_date_branch_uses_configured_civil_date: entryQueuedMethod.includes("case 'date':") && entryQueuedMethod.includes('$scheduled_date = $current_step->schedule_date;'),
    schedule_date_field_and_delay_localize_operational_timestamp:
      entryQueuedMethod.includes("case 'date_field':")
      && entryQueuedMethod.includes("case 'delay':")
      && entryQueuedMethod.includes("date( 'Y-m-d H:i:s', $scheduled_timestamp )")
      && entryQueuedMethod.includes('get_date_from_gmt( $scheduled_date_str )'),
    schedule_date_timestamp_reads_configured_date:
      timestampDateMethod.includes("$this->{\$setting_type . '_date'}")
      && timestampDateMethod.includes('get_gmt_from_date( $date )'),
    schedule_date_field_timestamp_reads_configured_field_and_offset:
      timestampDateFieldMethod.includes("$this->{\$setting_type . '_date_field'}")
      && timestampDateFieldMethod.includes("$this->{\$setting_type . '_date_field_offset'}")
      && timestampDateFieldMethod.includes("$this->{\$setting_type . '_date_field_before_after'}")
      && timestampDateFieldMethod.includes('get_gmt_from_date( $date )'),
    schedule_delay_timestamp_uses_step_timestamp_and_offset:
      timestampDelayMethod.includes('get_step_timestamp()')
      && timestampDelayMethod.includes("_delay_offset")
      && timestampDelayMethod.includes("_delay_unit"),
    step_timestamp_reads_step_scoped_entry_meta:
      stepTimestampMethod.includes("'workflow_step_' . $this->get_id() . '_timestamp'")
      && stepTimestampMethod.includes('gform_get_meta( $this->get_entry_id()'),
    queued_step_status_calls_schedule_renderer:
      entryStepStatusMethod.includes('display_queued_step_details') && entryStepStatusMethod.includes('queued'),
  },
  timeline_history: {
    header_formats_note_date_directly: noteHeaderMethod.includes('Gravity_Flow_Common::format_date( $date_created') && !noteHeaderMethod.includes('apply_filters('),
    gravityforms_reads_host_time_format: gfFormatDateMethod.includes('$time_format = self::get_default_time_format();'),
    gravityforms_renders_date_and_time_separately:
      gfFormatDateMethod.includes('date_i18n( $date_format, $local_time, true )')
      && gfFormatDateMethod.includes('date_i18n( $time_format, $local_time, true )'),
    note_body_is_separate_escaped_content: noteBodyMethod.includes('nl2br( esc_html( $note->value ) )'),
    timeline_reads_gravityforms_notes: timelineNotesMethod.includes('RGFormsModel::get_lead_notes'),
    timeline_inserts_initial_entry_event: timelineNotesMethod.includes('array_unshift') && timelineNotesMethod.includes('get_initial_note'),
    initial_event_uses_entry_date_created: initialNoteMethod.includes("$initial_note->date_created = $entry['date_created']"),
    timeline_order_is_host_owned: timelineNotesMethod.includes('array_reverse'),
    timeline_full_array_filter_runs_after_host_reverse: timelineNotesMethod.indexOf('array_reverse') >= 0
      && timelineNotesMethod.indexOf("apply_filters( 'gravityflow_timeline_notes'") > timelineNotesMethod.indexOf('array_reverse'),
    only_timeline_data_filter_mutates_note_array: timelineNotesMethod.includes("apply_filters( 'gravityflow_timeline_notes'"),
    common_text_timeline_reuses_note_dates: commonTimelineMethod.includes('get_timeline_notes') && commonTimelineMethod.includes('date_created'),
    gravityforms_notes_are_persisted_in_utc: gfFormsModel.content.includes('sub_type, date_created) values(%d, %d, %s, %s, %s, %s, utc_timestamp())'),
    gravityforms_notes_return_raw_date_created: gfFormsModel.content.includes('SELECT n.id, n.user_id, n.date_created, n.value, n.note_type, n.sub_type'),
  },
  print: {
    reuses_entry_detail_grid: printRenderMethod.includes('Gravity_Flow_Entry_Detail::entry_detail_grid'),
    optional_timeline_reuses_entry_detail_timeline: printRenderMethod.includes('Gravity_Flow_Entry_Detail::timeline'),
    no_print_specific_date_formatter: !printRenderMethod.includes('format_date('),
    print_style_hook_is_not_date_seam: printEntries.content.includes("apply_filters( 'gravityflow_print_styles'") && !printRenderMethod.includes('gravityflow_print_styles'),
    workflow_sidebar_not_rendered_by_print:
      !printRenderMethod.includes('workflow_entry_detail_status_box')
      && !printRenderMethod.includes('maybe_display_entry_detail_workflow_info')
      && !printRenderMethod.includes('display_queued_step_details'),
  },
};

const evidence = {
  schema_version: '1.4.0',
  evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
  exact,
  source_contract: sourceContract,
  method_sources: {
    status_due_date: statusDueMethod,
    status_export: statusExportMethod,
    entry_workflow_box: entryWorkflowBoxMethod,
    entry_workflow_info: entryWorkflowInfoMethod,
    entry_queued_details: entryQueuedMethod,
    due_getter: dueGetterMethod,
    schedule_getter: scheduleGetterMethod,
    expiration_getter: expirationGetterMethod,
    timeline_note_header: noteHeaderMethod,
    timeline_notes: timelineNotesMethod,
    initial_note: initialNoteMethod,
    common_timeline: commonTimelineMethod,
    print_render: printRenderMethod,
    flow_format_date: flowFormatDateMethod,
    gravityforms_format_date: gfFormatDateMethod,
    wordpress_date_i18n: wpDateI18nMethod,
    step_get_timestamp_date: timestampDateMethod,
    step_get_timestamp_date_field: timestampDateFieldMethod,
    step_get_timestamp_delay: timestampDelayMethod,
    step_get_step_timestamp: stepTimestampMethod,
    entry_step_status: entryStepStatusMethod,
  },
  targets: {
    status_due_date: {
      status_due_date: windows(status, 'due_date', 24),
      status_value_filter: windows(status, 'gravityflow_field_value_status_table', 20),
      status_export: windows(status, 'function export', 28),
      due_getter_call_sites: searchPhp(root, 'get_due_date_timestamp()', 16),
    },
    entry_detail_schedule_due_expiration: {
      entry_detail_due_date: windows(entryDetail, 'due_date', 26),
      entry_detail_schedule: windows(entryDetail, 'schedule', 26),
      entry_detail_expiration: windows(entryDetail, 'expiration', 26),
      due_getter_call_sites: searchPhp(root, 'get_due_date_timestamp()', 20),
      schedule_getter_call_sites: searchPhp(root, 'get_schedule_timestamp()', 20),
      expiration_getter_call_sites: searchPhp(root, 'get_expiration_timestamp()', 20),
      due_date_label_sites: searchPhp(root, "'Due Date'", 16),
      schedule_label_sites: searchPhp(root, "'Schedule'", 16),
      expiration_label_sites: searchPhp(root, "'Expiration'", 16),
      date_format_hook_sites: searchPhp(root, 'gravityflow_date_format_entry_detail', 24),
      gravityforms_date_i18n_sites: searchPhp(gfRoot, 'date_i18n(', 24),
      wordpress_date_i18n: windows(wpFunctions, "function date_i18n", 60),
      step_due_date: windows(step, 'get_due_date_timestamp', 24),
      step_schedule: windows(step, 'get_schedule_timestamp', 24),
      step_expiration: windows(step, 'get_expiration_timestamp', 24),
    },
    timeline_history: {
      timeline: windows(entryDetail, 'timeline', 32),
      date_created: windows(entryDetail, 'date_created', 24),
      notes: windows(entryDetail, 'notes', 24),
      common_timeline: windows(common, 'timeline', 28),
      flow_main_timeline: windows(flowMain, 'timeline', 28),
      gravityforms_get_lead_notes: searchPhp(gfRoot, 'get_lead_notes', 24),
      gravityforms_note_date_created: searchPhp(gfRoot, 'date_created', 12)
        .filter((occurrence) => occurrence.file.includes('note') || occurrence.source.some((line) => line.text.includes('lead_notes') || line.text.includes('entry_notes'))),
    },
    print: {
      print_timeline: windows(printEntries, 'timeline', 28),
      print_entry_detail: windows(printEntries, 'entry_detail', 28),
      print_styles: windows(printEntries, 'gravityflow_print_styles', 20),
    },
  },
};

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'g008-residual-source-probe.json'), `${JSON.stringify(evidence, null, 2)}\n`);
if (!allTrue(sourceContract)) {
  throw new Error(`Exact Gravity Flow residual no-admission source contract drifted: ${JSON.stringify(sourceContract)}`);
}
console.log('G008_RESIDUAL_SOURCE_PROBE exact Flow 3.1.0');
