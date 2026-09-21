import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!wpPath || !artifactDir) {
  throw new Error('WU008_WP_PATH and WU008_ARTIFACT_DIR are required.');
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

const flowNeedles = [
  'gravityflow_inbox_field_value',
  'date_created_human_readable',
  'last_updated_human_readable',
  'due_date_human_readable',
  'gravityflow_field_value_status_table',
  'workflow_timestamp',
  'date_created',
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
  schema_version: '1.0.0',
  evidence_class: 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY',
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
