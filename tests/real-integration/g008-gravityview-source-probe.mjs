import fs from 'node:fs';
import path from 'node:path';

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

function numberedExcerpt(source, startLine, endLine) {
  const lines = source.content.split(/\r?\n/);
  const start = Math.max(1, startLine);
  const end = Math.min(lines.length, endLine);
  return {
    file: source.relative,
    start_line: start,
    end_line: end,
    lines: lines.slice(start - 1, end).map((text, index) => ({ line: start + index, text })),
  };
}

function around(source, needle, before = 12, after = 24) {
  const lines = source.content.split(/\r?\n/);
  const index = lines.findIndex((line) => line.includes(needle));
  if (index < 0) throw new Error(`Required token not found in ${source.relative}: ${needle}`);
  return numberedExcerpt(source, index + 1 - before, index + 1 + after);
}

function walk(root) {
  const out = [];
  for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
    const full = path.join(root, entry.name);
    if (entry.isDirectory()) out.push(...walk(full));
    else if (/\.php$/i.test(entry.name)) out.push(full);
  }
  return out.sort();
}

function findWindows(root, needles, radius = 8) {
  const results = [];
  for (const file of walk(root)) {
    const relative = path.relative(root, file).replaceAll(path.sep, '/');
    const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
    for (let i = 0; i < lines.length; i += 1) {
      const matched = needles.filter((needle) => lines[i].includes(needle));
      if (matched.length === 0) continue;
      const start = Math.max(0, i - radius);
      const end = Math.min(lines.length, i + radius + 1);
      results.push({
        file: relative,
        line: i + 1,
        matched,
        excerpt: {
          start_line: start + 1,
          end_line: end,
          lines: lines.slice(start, end).map((text, offset) => ({ line: start + offset + 1, text })),
        },
      });
    }
  }
  return results;
}

const dateCreated = read(roots.gravityview, 'src/Field/Types/DateCreated.php');
const dateUpdated = read(roots.gravityview, 'src/Field/Types/DateUpdated.php');
const templateField = read(roots.gravityview, 'src/Template/TemplateField.php');
const gvCommon = read(roots.gravityview, 'src/Legacy/Utility/Common.php');
const formGf = read(roots.gravityview, 'src/Form/FormGravityForms.php');
const queryVisitor = read(roots.gravityview, 'src/Search/Querying/Visitors/QueryFilterVisitor.php');
const gfApi = read(roots.gravityforms, 'includes/api.php');

const sourceContract = {
  date_created: {
    field_name: /var\s+\$name\s*=\s*['"]date_created['"]/.test(dateCreated.content),
    searchable: /var\s+\$is_searchable\s*=\s*true/.test(dateCreated.content),
    operators: /var\s+\$search_operators\s*=\s*\[['"]less_than['"],\s*['"]greater_than['"],\s*['"]is['"],\s*['"]isnot['"]\]/.test(dateCreated.content),
    contexts: /var\s+\$contexts\s*=\s*\[['"]single['"],\s*['"]multiple['"],\s*['"]export['"]\]/.test(dateCreated.content),
    get_content_reads_raw_field_value: /get_content[\s\S]{0,700}GVCommon::format_date\(\s*\$field\['value'\]/.test(dateCreated.content),
  },
  date_updated: {
    extends_date_created: /class\s+DateUpdated\s+extends\s+\\GravityView_Field_Date_Created/.test(dateUpdated.content),
    field_name: /var\s+\$name\s*=\s*['"]date_updated['"]/.test(dateUpdated.content),
    searchable: /var\s+\$is_searchable\s*=\s*true/.test(dateUpdated.content),
    operators: /var\s+\$search_operators\s*=\s*\[['"]less_than['"],\s*['"]greater_than['"],\s*['"]is['"],\s*['"]isnot['"]\]/.test(dateUpdated.content),
    contexts: /var\s+\$contexts\s*=\s*\[['"]single['"],\s*['"]multiple['"],\s*['"]export['"]\]/.test(dateUpdated.content),
  },
  formatting: {
    gv_common_declares_no_site_timezone: gvCommon.content.includes("Formats date without applying site's timezone."),
    gv_common_is_copy_of_gf_formatter: gvCommon.content.includes('This is a copy of {@see GFCommon::format_date()}'),
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
    query_filter_handles_date_created: queryVisitor.content.includes("'date_created'"),
  },
  gravityforms_raw_contract: {
    date_created_utc_y_m_d_h_i_s: gfApi.content.includes("The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC)."),
    update_entry_property_api_present: /function\s+update_entry_property\s*\(/.test(gfApi.content),
  },
};

function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allTrue);
  return true;
}
if (!allTrue(sourceContract)) {
  throw new Error(`Exact GravityView/Gravity Forms source contract drifted: ${JSON.stringify(sourceContract)}`);
}

const evidence = {
  schema_version: '2.0.0',
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_gravityforms_version: process.env.WU008_GF_VERSION || null,
  exact_gravityforms_package_sha256: expectedGfSha,
  exact_persiangravity_head: expectedHead,
  source_contract: sourceContract,
  provenance: {
    date_created_field: numberedExcerpt(dateCreated, 17, 115),
    date_updated_field: numberedExcerpt(dateUpdated, 17, 70),
    template_output_filters: numberedExcerpt(templateField, 390, 526),
    gv_format_date: around(gvCommon, "Formats date without applying site's timezone.", 2, 85),
    gravityview_gfapi_query_bridge: numberedExcerpt(formGf, 103, 150),
    query_filter_date_created: around(queryVisitor, "'date_created'", 24, 34),
    gravityforms_date_created_contract: around(gfApi, "The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC).", 10, 20),
    gravityforms_update_entry_property: around(gfApi, 'update_entry_property', 12, 44),
  },
  gravityforms_date_updated_references: findWindows(
    roots.gravityforms,
    ['date_updated', "current_time( 'mysql', true )", 'current_time( \'mysql\', true )'],
    10,
  ).slice(0, 160),
};

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-source-probe.json'), JSON.stringify(evidence, null, 2) + '\n');
console.log('GravityView G-008 source contract proven for exact 3.3.4; targeted provenance captured.');
