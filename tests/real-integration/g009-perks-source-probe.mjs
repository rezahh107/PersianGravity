import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const pgrSha = process.env.WU008_PGR_SHA;
if (!wpPath || !artifactDir || !pgrSha) {
  throw new Error('WU008_WP_PATH, WU008_ARTIFACT_DIR and WU008_PGR_SHA are required.');
}

const fupPath = path.join(wpPath, 'wp-content/plugins/gp-file-upload-pro/class-gp-file-upload-pro.php');
const advancedPath = path.join(wpPath, 'wp-content/plugins/gp-advanced-select/class-gp-advanced-select.php');
for (const sourcePath of [fupPath, advancedPath]) {
  if (!fs.existsSync(sourcePath)) throw new Error(`Exact installed vendor source is missing: ${sourcePath}`);
}

const fup = fs.readFileSync(fupPath, 'utf8');
const advanced = fs.readFileSync(advancedPath, 'utf8');

function lineOf(source, needle) {
  const index = source.indexOf(needle);
  if (index < 0) return null;
  return source.slice(0, index).split('\n').length;
}

const observations = {
  file_upload_pro: {
    source_file: 'gp-file-upload-pro/class-gp-file-upload-pro.php',
    wp_localize_script_gpfup_constants_line: lineOf(fup, "wp_localize_script( 'gp-file-upload-pro', 'GPFUP_CONSTANTS'"),
    gettext_select_files_line: lineOf(fup, "__( 'select files', 'gp-file-upload-pro' )"),
    gettext_drop_files_here_line: lineOf(fup, "__( 'Drop files here', 'gp-file-upload-pro' )"),
    gettext_or_line: lineOf(fup, "__( 'or', 'gp-file-upload-pro' )"),
  },
  advanced_select: {
    source_file: 'gp-advanced-select/class-gp-advanced-select.php',
    exact_style_handle_line: lineOf(advanced, "'handle'  => 'gp-advanced-select-tom-select'"),
    exact_style_asset_line: lineOf(advanced, "'/styles/tom-select.bootstrap5.css'"),
  },
};

const required = [
  ...Object.values(observations.file_upload_pro).slice(1),
  ...Object.values(observations.advanced_select).slice(1),
];
if (required.some((value) => !Number.isInteger(value) || value <= 0)) {
  throw new Error('Exact installed Gravity Perks source contract probe is incomplete.');
}

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'G009_EXACT_INSTALLED_GRAVITY_PERKS_SOURCE_PROBE',
  exact_persiangravity_commit: pgrSha,
  exact_versions: {
    gravityperks: process.env.WU008_PERKS_VERSION,
    gpfileuploadpro: process.env.WU008_FUP_VERSION,
    gpadvancedselect: process.env.WU008_ADVS_VERSION,
  },
  exact_package_sha256: {
    gravityperks: process.env.WU008_PERKS_SHA256,
    gpfileuploadpro: process.env.WU008_FUP_SHA256,
    gpadvancedselect: process.env.WU008_ADVS_SHA256,
  },
  observations,
  licensed_source_exported: false,
};

fs.writeFileSync(
  path.join(artifactDir, 'g009-perks-source-probe.json'),
  `${JSON.stringify(evidence, null, 2)}\n`,
);
console.log('G-009 exact installed Gravity Perks source probe PASS');
