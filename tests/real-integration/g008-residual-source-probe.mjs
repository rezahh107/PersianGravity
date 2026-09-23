import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!wpPath || !artifactDir) throw new Error('WU008_WP_PATH and WU008_ARTIFACT_DIR are required.');

const root = path.join(wpPath, 'wp-content/plugins/gravityflow');
const gfRoot = path.join(wpPath, 'wp-content/plugins/gravityforms');
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

function methodSource(source, methodName) {
  const pattern = new RegExp('(?:public\\s+|protected\\s+|private\\s+)?(?:static\\s+)?function\\s+' + methodName + '\\s*\\(');
  const match = pattern.exec(source.content);
  if (!match) return '';
  const rest = source.content.slice(match.index + match[0].length);
  const next = /\n\s*(?:public\s+|protected\s+|private\s+)?(?:static\s+)?function\s+[A-Za-z0-9_]+\s*\(/.exec(rest);
  return source.content.slice(match.index, next ? match.index + match[0].length + next.index : source.content.length);
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

const statusDueMethod = methodSource(status, 'column_due_date');
const statusExportMethod = methodSource(status, 'export');
const entryWorkflowBoxMethod = methodSource(flowMain, 'workflow_entry_detail_status_box');
const entryQueuedMethod = methodSource(flowMain, 'display_queued_step_details');
const dueGetterMethod = methodSource(step, 'get_due_date_timestamp');
const scheduleGetterMethod = methodSource(step, 'get_schedule_timestamp');
const expirationGetterMethod = methodSource(step, 'get_expiration_timestamp');
const overdueMethod = methodSource(step, 'is_overdue');
const expiredMethod = methodSource(step, 'is_expired');
const validateScheduleMethod = methodSource(step, 'validate_schedule');
const noteHeaderMethod = methodSource(entryDetail, 'get_note_header');
const noteBodyMethod = methodSource(entryDetail, 'get_note_body');
const timelineNotesMethod = methodSource(common, 'get_timeline_notes');
const initialNoteMethod = methodSource(common, 'get_initial_note');
const commonTimelineMethod = methodSource(common, 'get_timeline');
const printRenderMethod = methodSource(printEntries, 'render');

const sourceContract = {
  status_due_date: {
    table_reads_operational_due_getter_directly: statusDueMethod.includes('get_due_date_timestamp()'),
    table_formats_due_inside_column_method: statusDueMethod.includes('Gravity_Flow_Common::format_date'),
    table_echoes_direct_output: statusDueMethod.includes('echo $output;'),
    table_has_no_status_value_filter: !statusDueMethod.includes('gravityflow_field_value_status_table') && !statusDueMethod.includes('filter_field_value('),
    table_has_no_entry_url_proof_seam: !statusDueMethod.includes('get_entry_url('),
    export_has_separate_due_branch: statusExportMethod.includes("case 'due_date':") && statusExportMethod.includes('get_due_date_timestamp()'),
    export_uses_generic_status_filter: statusExportMethod.includes("gravityflow_field_value_status_table"),
    due_getter_is_operational_filter: dueGetterMethod.includes("apply_filters( 'gravityflow_step_due_date_timestamp'"),
    overdue_uses_same_due_getter: overdueMethod.includes('get_due_date_timestamp()') && overdueMethod.includes('time()'),
  },
  entry_detail_schedule_due_expiration: {
    due_is_direct_operational_getter_render: entryWorkflowBoxMethod.includes('get_due_date_timestamp()') && entryWorkflowBoxMethod.includes('gravityflow-status-box-field-due-date') && entryWorkflowBoxMethod.includes("'Due Date'"),
    expiration_is_direct_operational_getter_render: entryWorkflowBoxMethod.includes('get_expiration_timestamp()') && entryWorkflowBoxMethod.includes('gravityflow-status-box-field-expires') && entryWorkflowBoxMethod.includes("'Expires'"),
    below_workflow_hook_is_after_direct_date_output: entryWorkflowBoxMethod.indexOf('get_expiration_timestamp()') >= 0 && entryWorkflowBoxMethod.indexOf("do_action( 'gravityflow_below_workflow_info_entry_detail'") > entryWorkflowBoxMethod.indexOf('get_expiration_timestamp()'),
    due_and_expiration_have_no_value_filter: !entryWorkflowBoxMethod.includes('apply_filters('),
    schedule_reads_operational_getter_directly: entryQueuedMethod.includes('get_schedule_timestamp()'),
    schedule_prints_directly: entryQueuedMethod.includes('gravityflow-status-box-field-scheduled-date') && entryQueuedMethod.includes("'Scheduled'"),
    schedule_has_no_value_filter: !entryQueuedMethod.includes('apply_filters('),
    schedule_getter_is_operational_filter: scheduleGetterMethod.includes("apply_filters( 'gravityflow_step_schedule_timestamp'"),
    expiration_getter_is_operational_filter: expirationGetterMethod.includes("apply_filters( 'gravityflow_step_expiration_timestamp'"),
    schedule_validation_uses_same_getter: validateScheduleMethod.includes('get_schedule_timestamp()') && validateScheduleMethod.includes('time()'),
    expiration_state_uses_same_getter: expiredMethod.includes('get_expiration_timestamp()') && expiredMethod.includes('time()'),
  },
  timeline_history: {
    header_formats_note_date_directly: noteHeaderMethod.includes('Gravity_Flow_Common::format_date( $date_created') && !noteHeaderMethod.includes('apply_filters('),
    note_body_is_separate_escaped_content: noteBodyMethod.includes('nl2br( esc_html( $note->value ) )'),
    timeline_reads_gravityforms_notes: timelineNotesMethod.includes('RGFormsModel::get_lead_notes'),
    timeline_inserts_initial_entry_event: timelineNotesMethod.includes('array_unshift') && timelineNotesMethod.includes('get_initial_note'),
    initial_event_uses_entry_date_created: initialNoteMethod.includes("$note->date_created = $entry['date_created']"),
    timeline_order_is_host_owned: timelineNotesMethod.includes('array_reverse'),
    only_timeline_data_filter_mutates_note_array: timelineNotesMethod.includes("apply_filters( 'gravityflow_timeline_notes'"),
    common_text_timeline_reuses_note_dates: commonTimelineMethod.includes('get_timeline_notes') && commonTimelineMethod.includes('date_created'),
    gravityforms_notes_are_persisted_in_utc: gfFormsModel.content.includes('sub_type, date_created) values(%d, %d, %s, %s, %s, %s, utc_timestamp())'),
    gravityforms_notes_return_raw_date_created: gfFormsModel.content.includes('SELECT n.id, n.user_id, n.date_created, n.value, n.note_type, n.sub_type'),
  },
  print: {
    reuses_entry_detail_grid: printRenderMethod.includes('Gravity_Flow_Entry_Detail::entry_detail_grid'),
    optional_timeline_reuses_entry_detail_timeline: printRenderMethod.includes('Gravity_Flow_Entry_Detail::timeline'),
    no_print_specific_date_formatter: !printRenderMethod.includes('format_date('),
    print_style_hook_is_not_date_seam: printEntries.content.includes("do_action( 'gravityflow_print_styles'") && !printRenderMethod.includes('gravityflow_print_styles'),
  },
};

const evidence = {
  schema_version: '1.2.0',
  evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
  exact,
  source_contract: sourceContract,
  method_sources: {
    status_due_date: statusDueMethod,
    status_export: statusExportMethod,
    entry_workflow_box: entryWorkflowBoxMethod,
    entry_queued_details: entryQueuedMethod,
    due_getter: dueGetterMethod,
    schedule_getter: scheduleGetterMethod,
    expiration_getter: expirationGetterMethod,
    timeline_note_header: noteHeaderMethod,
    timeline_notes: timelineNotesMethod,
    initial_note: initialNoteMethod,
    common_timeline: commonTimelineMethod,
    print_render: printRenderMethod,
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
