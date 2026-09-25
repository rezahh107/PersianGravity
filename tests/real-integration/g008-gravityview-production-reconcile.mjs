import fs from 'node:fs';
import path from 'node:path';
import {
  assertMetadataOnlySourceEvidence,
  assertSanitizedProvenance,
} from './g008-gravityview-source-evidence.mjs';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedHead = process.env.WU008_PGR_SHA;
const expectedTree = process.env.WU008_PGR_TREE;
const expectedPgrPackageSha = process.env.WU008_PGR_PACKAGE_SHA256;
const expectedViewSha = process.env.WU008_VIEW_SHA256;
const expectedGfSha = process.env.WU008_GF_SHA256;

if (
  !artifactDir
  || !/^[a-f0-9]{40}$/.test(expectedHead ?? '')
  || !/^[a-f0-9]{40}$/.test(expectedTree ?? '')
  || !/^[a-f0-9]{64}$/.test(expectedPgrPackageSha ?? '')
  || !/^[a-f0-9]{64}$/.test(expectedViewSha ?? '')
  || !/^[a-f0-9]{64}$/.test(expectedGfSha ?? '')
) {
  throw new Error('GravityView production admission reconciliation requires exact identity.');
}

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};
const allTrue = (value) => {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const values = Object.values(value);
    return values.length > 0 && values.every(allTrue);
  }
  return false;
};
const nativeStructure = (snapshot) => ({
  sortLinks: snapshot.sortLinks,
  rowAttributes: snapshot.rows.map((row) => ({ token: row.token, attributes: row.attributes })),
});
const canonicalEntries = (snapshot) => snapshot.entries.map((entry) => ({
  id: entry.id,
  token: entry.token,
  gfapi_date_created: entry.gfapi_date_created,
  gfapi_date_updated: entry.gfapi_date_updated,
  db_date_created: entry.db_date_created,
  db_date_updated: entry.db_date_updated,
  rest_date_created: entry.rest_date_created,
  rest_date_updated: entry.rest_date_updated,
}));

const source = read('g008-gravityview-source-probe.json');
const fixture = read('g008-gravityview-fixture-baseline.json');
const browser = Object.fromEntries(
  ['enabled', 'disabled', 'english', 'drift'].map((mode) => [
    mode,
    read(`g008-gravityview-production-browser-${mode}.json`),
  ]),
);
const state = Object.fromEntries(
  ['enabled', 'disabled', 'english', 'drift'].map((mode) => [
    mode,
    read(`g008-gravityview-production-state-${mode}.json`),
  ]),
);

assertMetadataOnlySourceEvidence(source);
assert(source.exact_version === '3.3.4', 'GravityView source version drifted.');
assert(source.exact_package_sha256 === expectedViewSha, 'GravityView source package SHA drifted.');
assert(source.exact_gravityforms_package_sha256 === expectedGfSha, 'Gravity Forms source package SHA drifted.');
assert(source.exact_persiangravity_head === expectedHead, 'GravityView source probe Head drifted.');
assert(allTrue(source.source_contract), 'GravityView exact source contract is incomplete.');
assert(source.independent_fail_closed?.date_created === true, 'date_created source drift is not independently fail-closed.');
assert(source.independent_fail_closed?.date_updated === true, 'date_updated source drift is not independently fail-closed.');
assertSanitizedProvenance(source.provenance);

assert(fixture.exact_persiangravity_head === expectedHead, 'GravityView fixture Head drifted.');
assert(fixture.exact_gravityview_sha256 === expectedViewSha, 'GravityView fixture package SHA drifted.');
assert(fixture.site_timezone === 'Asia/Tehran' && fixture.php_timezone === 'UTC', 'GravityView timezone fixture drifted.');
assert(fixture.entries.length === 3, 'GravityView fixture cardinality drifted.');

for (const mode of ['enabled', 'disabled', 'english', 'drift']) {
  assert(browser[mode].status === 'PASS', `Production browser mode ${mode} failed.`);
  assert(browser[mode].exact_persiangravity_head === expectedHead, `Browser ${mode} Head drifted.`);
  assert(browser[mode].exact_gravityview_version === '3.3.4', `Browser ${mode} package version drifted.`);
  assert(browser[mode].exact_gravityview_sha256 === expectedViewSha, `Browser ${mode} package SHA drifted.`);
  assert(state[mode].exact_persiangravity_head === expectedHead, `State ${mode} Head drifted.`);
  assert(state[mode].exact_gravityview_sha256 === expectedViewSha, `State ${mode} package SHA drifted.`);
  assert(state[mode].qualification_mu_prototype_absent === true, `Production mode ${mode} loaded the qualification MU prototype.`);
  assert(browser[mode].initial.rows.every((row) => row.diagnosticNodes === 0), `Production mode ${mode} emitted diagnostic markup.`);
}

assert(state.enabled.module_enabled === true, 'Enabled production mode did not enable jalali_presentation.');
assert(state.enabled.adapter_class_loaded === true, 'Enabled production mode did not load the GravityView adapter.');
assert(state.enabled.date_created_hook_registered === true, 'date_created production hook is not registered.');
assert(state.enabled.date_updated_hook_registered === true, 'date_updated production hook is not registered.');
assert(state.enabled.observed_plugin_header_version === '3.3.4', 'Enabled host version identity drifted.');

assert(state.disabled.module_enabled === false, 'Disabled production mode kept jalali_presentation enabled.');
assert(state.disabled.adapter_class_loaded === false, 'Disabled production mode loaded the GravityView adapter.');
assert(state.disabled.date_created_hook_registered === false, 'Disabled production mode registered date_created.');
assert(state.disabled.date_updated_hook_registered === false, 'Disabled production mode registered date_updated.');

assert(state.english.locale === 'en_US', 'English production control locale drifted.');
assert(state.english.adapter_class_loaded === true, 'English control did not load the gated adapter class.');
assert(browser.english.initial.html.computedDir === 'ltr', 'English production control did not render LTR.');

assert(state.drift.module_enabled === true, 'Version-drift mode must keep the module enabled.');
assert(state.drift.adapter_class_loaded === true, 'Version-drift mode must load the production adapter.');
assert(state.drift.observed_plugin_header_version === '3.3.5', 'Version-drift harness did not alter only the observed host header authority.');

for (const expected of fixture.entries) {
  const enabledRow = browser.enabled.initial.rows.find((row) => row.token === expected.token);
  const disabledRow = browser.disabled.initial.rows.find((row) => row.token === expected.token);
  const englishRow = browser.english.initial.rows.find((row) => row.token === expected.token);
  const driftRow = browser.drift.initial.rows.find((row) => row.token === expected.token);
  assert(enabledRow?.cells.includes(expected.expected_created_jalali), `Enabled date_created is not Jalali for ${expected.token}.`);
  assert(enabledRow?.cells.includes(expected.expected_updated_jalali), `Enabled date_updated is not Jalali for ${expected.token}.`);
  for (const [label, row] of [['disabled', disabledRow], ['english', englishRow], ['drift', driftRow]]) {
    assert(row?.cells.includes(expected.expected_created_native), `${label} date_created did not preserve native output for ${expected.token}.`);
    assert(row?.cells.includes(expected.expected_updated_native), `${label} date_updated did not preserve native output for ${expected.token}.`);
  }
  assert(enabledRow.text.includes(expected.token), `User-authored text changed for ${expected.token}.`);
}

for (const mode of ['disabled', 'english', 'drift']) {
  assert(same(canonicalEntries(state.enabled), canonicalEntries(state[mode])), `DB/GFAPI/REST semantics changed in ${mode}.`);
  assert(same(state.enabled.gfapi_sort, state[mode].gfapi_sort), `GFAPI sorting changed in ${mode}.`);
  for (const key of ['date_created_asc', 'date_created_desc', 'date_updated_asc', 'date_updated_desc']) {
    assert(
      same(browser.enabled.sorting[key].entry_ids, browser[mode].sorting[key].entry_ids),
      `GravityView sort ${key} changed in ${mode}.`,
    );
  }
  assert(
    same(
      browser.enabled.filtering.date_created_local_2026_03_21.entry_ids,
      browser[mode].filtering.date_created_local_2026_03_21.entry_ids,
    ),
    `date_created filter result changed in ${mode}.`,
  );
  assert(
    same(
      browser.enabled.filtering.date_updated_direct_request_noop.entry_ids,
      browser[mode].filtering.date_updated_direct_request_noop.entry_ids,
    ),
    `date_updated bounded no-op changed in ${mode}.`,
  );
}

assert(browser.enabled.filtering.date_created_local_2026_03_21.entry_ids.length === 1, 'date_created native entry_date filter did not isolate the boundary fixture.');
assert(browser.enabled.filtering.date_updated_direct_request_noop.entry_ids.length === fixture.entries.length, 'date_updated direct-request no-op boundary widened or changed.');
assert(new URL(browser.enabled.filtering.date_created_local_2026_03_21.url).searchParams.get('gv_start') === '03/21/2026', 'date_created query input drifted.');
assert(new URL(browser.enabled.filtering.date_updated_direct_request_noop.url).searchParams.get('filter_date_updated') === '2026-03-21 20:31:00', 'date_updated bounded request input drifted.');
assert(
  same(browser.enabled.initial.rows.map((row) => row.token), browser.enabled.repeated.rows.map((row) => row.token)),
  'Repeated production rendering changed result identity/order.',
);
assert(
  same(nativeStructure(browser.enabled.initial), nativeStructure(browser.disabled.initial)),
  'Production presentation changed row or sort-link structural attributes.',
);

const adapterSource = fs.readFileSync('includes/class-pgr-gravityview-jalali-presentation-adapter.php', 'utf8');
for (const forbidden of [
  'wu008_gv_qualification_',
  'data-raw',
  'data-native',
  '<span',
  'wp_date(',
  'filter_date_updated=',
  '$_GET',
  '$_REQUEST',
  'GFAPI::get_entries',
]) {
  assert(!adapterSource.includes(forbidden), `Production adapter contains forbidden/test-only behavior: ${forbidden}`);
}
assert(adapterSource.includes("gravityview/template/field/date_created/output"), 'Production adapter lost the exact date_created seam.');
assert(adapterSource.includes("gravityview/template/field/date_updated/output"), 'Production adapter lost the exact date_updated seam.');
assert(adapterSource.includes("PGR_Jalali_Presentation::format_datetime"), 'Production adapter does not reuse the typed Jalali facade.');
assert(adapterSource.includes("$entry[ $field_id ]"), 'Production adapter does not read the authoritative raw Entry property.');

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'G008_GRAVITYVIEW_PRODUCTION_ADMISSION_RECONCILIATION',
  status: 'PASS',
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_gravityforms_version: '3.1.1.1',
  exact_gravityforms_package_sha256: expectedGfSha,
  exact_persiangravity_head: expectedHead,
  exact_persiangravity_commit: expectedHead,
  exact_persiangravity_tree: expectedTree,
  exact_persiangravity_package_sha256: expectedPgrPackageSha,
  adapter_identity: 'PGR_GravityView_Jalali_Presentation_Adapter',
  surfaces: {
    'gravityview.date-created': 'ADMITTED_VERIFIED',
    'gravityview.date-updated': 'ADMITTED_VERIFIED',
  },
  evidence: {
    source: 'g008-gravityview-source-probe.json',
    fixture: 'g008-gravityview-fixture-baseline.json',
    browser: Object.keys(browser).map((mode) => `g008-gravityview-production-browser-${mode}.json`),
    state: Object.keys(state).map((mode) => `g008-gravityview-production-state-${mode}.json`),
  },
  source_provenance: source.provenance,
  source_evidence_boundary: source.evidence_boundary,
  independent_source_fail_closed: source.independent_fail_closed,
  runtime_findings: {
    production_adapter_loaded_and_registered: true,
    qualification_mu_prototype_absent: true,
    date_created_visible_jalali: true,
    date_updated_visible_jalali: true,
    timezone_boundary_proven: true,
    module_disabled_native: true,
    english_ltr_native: true,
    exact_version_drift_native: true,
    repeated_render_stable: true,
    raw_db_gfapi_rest_equal_across_modes: true,
    gfapi_sort_equal_across_modes: true,
    gravityview_sort_equal_across_modes: true,
    date_created_native_filter_equal_across_modes: true,
    date_updated_unconfigured_direct_request_noop_equal_across_modes: true,
    row_and_sort_link_attributes_preserved: true,
    unrelated_user_text_preserved: true,
    diagnostic_wrapper_absent: true,
  },
  production_boundary: {
    production_adapter_added: true,
    qualification_only_mu_prototype_used_for_admission: false,
    exact_version_fail_closed: true,
    locale_context_fail_closed: true,
    display_string_reparse: false,
    machine_semantics_mutation: false,
    query_filter_sort_mutation: false,
  },
};

assertSanitizedProvenance(evidence.source_provenance);
fs.writeFileSync(
  path.join(artifactDir, 'g008-gravityview-admission.json'),
  JSON.stringify(evidence, null, 2) + '\n',
);
console.log('PASS GravityView G-008 production admission reconciliation');
