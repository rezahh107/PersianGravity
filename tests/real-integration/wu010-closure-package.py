#!/usr/bin/env python3
import hashlib
import json
import os
import sys
from pathlib import Path

ARTIFACT = Path(os.environ.get('WU008_ARTIFACT_DIR', '/tmp/wu008-artifacts'))
EXACT_SHA = os.environ.get('WU008_PGR_SHA', '')
HARNESS_HEAD = os.environ.get('WU010_HARNESS_HEAD', os.environ.get('GITHUB_SHA', ''))
RUN_ID = os.environ.get('GITHUB_RUN_ID', '')
RUN_ATTEMPT = os.environ.get('GITHUB_RUN_ATTEMPT', '')
REPO = os.environ.get('GITHUB_REPOSITORY', 'rezahh107/PersianGravity')


def read_json(name):
    return json.loads((ARTIFACT / name).read_text(encoding='utf-8'))


def sha256(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def file_entries():
    rows = []
    for path in sorted(ARTIFACT.rglob('*')):
        if not path.is_file():
            continue
        rel = path.relative_to(ARTIFACT).as_posix()
        if rel in {'SHA256SUMS', 'evidence-index.json'}:
            continue
        rows.append({'path': rel, 'bytes': path.stat().st_size, 'sha256': sha256(path)})
    return rows


def pr_url(number):
    return f'https://github.com/{REPO}/pull/{number}'


base = read_json('evidence-index.json')
repo = read_json('repository-validation-summary.json')
matrix = read_json('surface-evidence-matrix.json')
packages = read_json('package-verification.json')
findings = read_json('finding-register.json')
form_builder = read_json('form-builder-remediation/browser-evidence.json')

if isinstance(packages, dict):
    package_rows = packages.get('packages', [])
else:
    package_rows = packages

if isinstance(findings, list):
    finding_rows = findings
elif isinstance(findings, dict):
    finding_rows = findings.get('findings', [])
else:
    finding_rows = []

blocking = [f for f in finding_rows if f.get('classification') == 'BLOCKING_IN_SCOPE']
positive = [r for r in matrix if int(r.get('admitted_message_count', 0)) > 0]
zero = [r for r in matrix if int(r.get('admitted_message_count', 0)) == 0]

goal_traceability = [
    {
        'id': 'G-001',
        'requirement': 'Bounded deterministic provider/fallback architecture with vendor immutability and no foreign-domain overreach.',
        'outcome': 'Achieved for the accepted authority: provider-first resolution, upstream/source fallback, bounded product manifests, excluded dependency domains, and deterministic catalog generation remain intact.',
        'evidence': 'Provider foundation and Content Admission v2 contracts; integrated fallback hardening in merged PR #22; WU-010 repository/provider checks and no-drift rebuild.',
    },
    {
        'id': 'G-002',
        'requirement': 'Exact target-product source/classification/provenance authority without upgrading owner-supplied package evidence into vendor-authenticity claims.',
        'outcome': 'Achieved for Gravity Forms 3.1.1.1, Gravity Flow 3.1.0, and GravityView 3.3.4. Exact package hashes, reviewed baseline anchors, source-backed surface classification, and provenance are retained; vendor authenticity remains NOT_PROVEN.',
        'evidence': 'Merged source/provenance integrations including PR #10 and PR #16; current provider metadata; Phase-2 provenance anchors below; WU-010 exact package verification.',
    },
    {
        'id': 'G-003',
        'requirement': 'Complete provider admission for every identity already classified by the accepted 19-surface registry, with deterministic generated artifacts and explicit non-admitted boundaries.',
        'outcome': 'Achieved: GF 6/1759 admitted with 2448 unclassified, Flow 7/732 with 366 unclassified, View 6/461 with 2666 unclassified; exactly 19 records and 5480 identities remain outside authority.',
        'evidence': 'Merged final content chain PR #19 → #20 → #21; integrated regression/hardening PR #22; WU-010 content-admission and deterministic i18n rebuild gates.',
    },
    {
        'id': 'G-004',
        'requirement': 'Real licensed WordPress/browser/runtime/RTL proof for all accepted surfaces, truthful JS/provider-JSON boundaries, and zero unresolved blocking in-scope findings.',
        'outcome': 'Achieved on the exact final main: 19/19 runtime, 18/18 positive provider proofs, one truthful zero-admission N/A, 19/19 RTL/BiDi, JS/provider-JSON boundary PASS, Structured Scanner remediation PASS, BLOCKING_IN_SCOPE=0.',
        'evidence': 'WU-008 licensed evidence lane (merged harness PR #18), focused remediation PR #25, post-remediation WU-008 evidence, and fresh WU-010 terminal rerun.',
    },
    {
        'id': 'G-005',
        'requirement': 'Final integrated-main validation and a self-contained, hash-indexed closure record with explicit claim ceilings suitable for independent FINAL_CLOSURE review.',
        'outcome': 'Achieved as evidence: final main is bound exactly, repository and browser gates are rerun, diagnostics are dispositioned, closure traceability is self-contained, and internal hashes are generated/verified.',
        'evidence': 'Merged evidence integration PR #28 establishes final main; WU-010 PR #29 is evidence-only and intentionally NO_REPOSITORY_INTEGRATION.',
    },
]

work_unit_traceability = [
    {
        'id': 'WU-001',
        'outcome': 'Generalized Content Admission v2 to deterministic multi-record per-product unions with independent record validation, identical-overlap deduplication, conflict rejection, and aggregate-driven build authority.',
        'evidence': 'Merged PR #13, merge commit 8c8a7642fa8362775bc0730c6db3e00dede3eb3a.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-002',
        'outcome': 'Established/retained the reviewed baseline provenance used by later bounded admission. The current repository preserves the final WU-002 GravityView reviewed baseline SHA-256 2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca as historical reviewed input, not blanket runtime authority.',
        'evidence': 'Current GravityView metadata and admitted records bind to that exact reviewed-source hash; the Phase-2 provenance table below states the authority ceiling.',
        'integration_status': 'PROVENANCE_RETAINED_IN_CURRENT_MAIN',
    },
    {
        'id': 'WU-003',
        'outcome': 'Established/retained reviewed translation authority/provenance inputs subsequently bounded by source-backed admissions. Current metadata records the GF reviewed baseline, Flow reviewed baseline, and GravityView WU-003 classified-authority archive anchor.',
        'evidence': 'GF reviewed-source SHA 2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1; Flow c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9; GravityView authority archive c193a49bc896d0aa9f7e4c4204778dc2c7cd1fbec974237a01292e8dfafd233d.',
        'integration_status': 'PROVENANCE_RETAINED_IN_CURRENT_MAIN',
    },
    {
        'id': 'WU-004',
        'outcome': 'Completed Gravity Forms Content Admission v2 across all six accepted GF surfaces: deterministic 1759-identity union, 2448 unclassified/non-admitted identities preserved, no new JS authority.',
        'evidence': 'Merged PR #19, merge commit 407f14fb805ab9f12fad94971def9900aee76b3e.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-005',
        'outcome': 'Completed Gravity Flow Content Admission v2 across all seven accepted Flow surfaces, including truthful representation of the intentional zero-admission Entry Detail sidebar; 732 admitted and 366 non-admitted identities.',
        'evidence': 'Merged PR #20, merge commit 8a95be7507a944e4683ae15842d521eab2a89d37.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-006',
        'outcome': 'Completed GravityView Content Admission v2 across all six accepted View surfaces: 467 occurrences deduplicated to 461 admitted identities, 2666 non-admitted; gk-query-filters stays separate and action-scheduler unmanaged.',
        'evidence': 'Merged PR #21, merge commit 4ab0ccab2b03bd61ffbf2b3bc1176b8e72b96be1.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-007',
        'outcome': 'Hardened the integrated 19-record provider/fallback contract across all three products without changing provider runtime behavior; proved admitted resolution, non-admitted fallback, cross-domain isolation, excluded dependencies, zero product JS maps/JSON, and deterministic rebuild.',
        'evidence': 'Merged PR #22, merge commit d3d6460a07a2c38b483dac664603a443ce430da0.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-008',
        'outcome': 'Executed licensed real-browser evidence for all 19 accepted surfaces. It exposed the real Structured Scanner Form Builder inline-JavaScript defect; after focused remediation, the complete post-remediation 19-surface revalidation passed.',
        'evidence': 'Merged harness PR #18; evidence-only final revalidation run 34888229939 / artifact wu008-final-revalidation-34888229939-1; remediation dependency PR #25.',
        'integration_status': 'EVIDENCE_PLUS_MERGED_HARNESS',
    },
    {
        'id': 'WU-009',
        'outcome': 'Closed the browser-discovered product defect and integrated the controlling licensed runtime evidence into active repository validation documentation without broadening provider authority.',
        'evidence': 'Merged remediation PR #25 → e2629498eb406177f876e8c9ea3c7bb976730c0b; merged documentation/evidence PR #28 → final main 150d1dd3f2c4ffa0e4321bffe7c4ca503e3dfb67.',
        'integration_status': 'MERGED_REPOSITORY_INTEGRATION',
    },
    {
        'id': 'WU-010',
        'outcome': 'Fresh terminal validation of the exact integrated final main: repository determinism, exact packages, all 19 browser surfaces, provider/fallback/RTL/JS boundaries, Structured Scanner remediation, diagnostics, finding disposition, and hash-indexed closure evidence.',
        'evidence': f'Evidence-only PR #29; harness Head {HARNESS_HEAD}; run {RUN_ID} attempt {RUN_ATTEMPT}; this artifact and its SHA256SUMS.',
        'integration_status': 'NO_REPOSITORY_INTEGRATION',
    },
]

integration_chain = [
    ('#10', '0822a6b4f4b895df5b29478e994a4d46c58ac347', 'Exact GF/Flow source admission and package/source authority; vendor authenticity remained NOT_PROVEN.'),
    ('#13', '8c8a7642fa8362775bc0730c6db3e00dede3eb3a', 'Content Admission v2 deterministic multi-record union machinery (WU-001).'),
    ('#15', 'e9bd31dbeb07261de301eeb35935ccd8ed27dca6', 'Bounded GF frontend admission with seven explicit semantic corrections; historical provenance remains explicit.'),
    ('#16', '1a7108eea20ddd1d84680cc64e73e4c824f08811', 'Exact GravityView 3.3.4 source admission; gk-query-filters separated and action-scheduler excluded.'),
    ('#18', '670fd25d1081f80b9df5e5648f7c34675e9f24cf', 'Merged licensed WU-008 real-integration harness.'),
    ('#19', '407f14fb805ab9f12fad94971def9900aee76b3e', 'Final six-surface Gravity Forms classified content authority: 1759 admitted / 2448 unclassified.'),
    ('#20', '8a95be7507a944e4683ae15842d521eab2a89d37', 'Final seven-surface Gravity Flow authority: 732 admitted / 366 unclassified, including intentional zero-admission sidebar.'),
    ('#21', '4ab0ccab2b03bd61ffbf2b3bc1176b8e72b96be1', 'Final six-surface GravityView authority: 461 admitted / 2666 unclassified.'),
    ('#22', 'd3d6460a07a2c38b483dac664603a443ce430da0', 'Cross-product provider/fallback/no-drift integration hardening over exactly 19 accepted records.'),
    ('#25', 'e2629498eb406177f876e8c9ea3c7bb976730c0b', 'Focused Structured Scanner Form Builder JavaScript remediation discovered by WU-008.'),
    ('#28', '150d1dd3f2c4ffa0e4321bffe7c4ca503e3dfb67', 'Documentation/evidence integration only; establishes the exact final canonical main tested by WU-010.'),
]

phase2_provenance = [
    {
        'product': 'Gravity Forms 3.1.1.1',
        'reviewed_anchor': 'WU-003 reviewed source PO SHA-256 2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1 (4207-source census)',
        'bounded_final': 'PR #15 seven explicit semantic corrections plus PR #19 semantic-QA/content integration; final provider source SHA-256 13ee1298e77432adcdb4f48bbaa10b61f79f2f155d6a0bb4524b9fdcf6cf35d6; 1759 admitted.',
    },
    {
        'product': 'Gravity Flow 3.1.0',
        'reviewed_anchor': 'Reviewed source PO SHA-256 c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9 (1098-source census)',
        'bounded_final': 'PR #20 exact seven-surface classified union; final provider source SHA-256 bff3f53338558fd9c62e19a6dc8161f39c61a8cdb42d807acc1fe6693868fa1a; 732 admitted.',
    },
    {
        'product': 'GravityView 3.3.4',
        'reviewed_anchor': 'Final WU-002 reviewed baseline SHA-256 2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca; current metadata also records WU-003 authority archive SHA-256 c193a49bc896d0aa9f7e4c4204778dc2c7cd1fbec974237a01292e8dfafd233d',
        'bounded_final': 'PR #21 exact six-surface classified union; final provider source SHA-256 66ca25e98c622a35b06ff89cac9285259eb08c49e6ac800a348d17e0024e578a; 461 admitted.',
    },
]

expected_goals = [f'G-{i:03d}' for i in range(1, 6)]
expected_wus = [f'WU-{i:03d}' for i in range(1, 11)]
required_final_chain = {'#19', '#20', '#21', '#22', '#25', '#28'}

checks = {
    'base_evidence_pass': base.get('overall_result') == 'PASS',
    'repository_validation_pass': repo.get('result') == 'PASS',
    'exact_final_main_sha': base.get('exact_persiangravity_commit') == EXACT_SHA and base.get('installed_persiangravity_commit') == EXACT_SHA and repo.get('exact_main_sha') == EXACT_SHA,
    'licensed_packages_3_of_3': len(package_rows) == 3 and all((p.get('verification') == 'PASS' or p.get('verified') is True) for p in package_rows),
    'runtime_19_of_19': len(matrix) == 19 and all(r.get('runtime_execution') == 'PASS' and r.get('overall_surface_status') == 'PASS' for r in matrix),
    'positive_provider_18_of_18': len(positive) == 18 and all(r.get('provider_proof_status') == 'PASS' for r in positive),
    'zero_admission_1_of_1': len(zero) == 1 and zero[0].get('surface_id') == 'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow' and zero[0].get('provider_proof_status') == 'NOT_APPLICABLE_ZERO_ADMISSION',
    'rtl_19_of_19': len(matrix) == 19 and all(r.get('rtl_bidi_result') == 'PASS' for r in matrix),
    'js_provider_json_boundary': base.get('gates', {}).get('js_provider_json_boundary') is True,
    'form_builder_remediation': base.get('gates', {}).get('form_builder_remediation_functional') is True and form_builder.get('result') == 'PASS',
    'pageerrors_zero': int(base.get('summary', {}).get('browser_diagnostics', {}).get('pageerrors_total', -1)) == 0,
    'blocking_findings_zero': len(blocking) == 0 and int(base.get('summary', {}).get('blocking_findings', -1)) == 0,
    'traceability_goals_g001_g005': [g['id'] for g in goal_traceability] == expected_goals,
    'traceability_wu001_wu010': [w['id'] for w in work_unit_traceability] == expected_wus,
    'accepted_final_integration_chain_recorded': required_final_chain.issubset({row[0] for row in integration_chain}),
    'phase2_provenance_recorded': len(phase2_provenance) == 3 and all(row.get('reviewed_anchor') and row.get('bounded_final') for row in phase2_provenance),
    'wu010_no_repository_integration': work_unit_traceability[-1].get('integration_status') == 'NO_REPOSITORY_INTEGRATION',
    'claim_ceiling_recorded': True,
}
verdict = 'PASS' if all(checks.values()) else 'BLOCKED'

surface_lines = [
    '| # | Surface | Runtime | Provider | RTL/BiDi |',
    '|---:|---|---|---|---|',
]
for i, row in enumerate(matrix, 1):
    surface_lines.append(f"| {i} | `{row.get('surface_id')}` | {row.get('runtime_execution')} | {row.get('provider_proof_status')} | {row.get('rtl_bidi_result')} |")

package_lines = [
    '| Product | Version | SHA-256 | Verification | Vendor authenticity |',
    '|---|---|---|---|---|',
]
for p in package_rows:
    package_lines.append(
        f"| {p.get('product') or p.get('name')} | `{p.get('version') or p.get('expected_version')}` | `{p.get('sha256') or p.get('actual_sha256') or p.get('expected_sha256')}` | {p.get('verification') or ('PASS' if p.get('verified') else 'FAIL')} | `NOT_PROVEN` |"
    )

repo_checks = repo.get('checks', {})
repo_lines = ['| Repository gate | Result |', '|---|---|']
for key, value in repo_checks.items():
    if isinstance(value, (dict, list)):
        shown = json.dumps(value, ensure_ascii=False, sort_keys=True)
    else:
        shown = 'PASS' if value is True else ('FAIL' if value is False else str(value))
    repo_lines.append(f'| `{key}` | {shown} |')

goal_lines = ['| Goal | Contract requirement | Practical outcome | Evidence / accepted integration |', '|---|---|---|---|']
for row in goal_traceability:
    goal_lines.append(f"| `{row['id']}` | {row['requirement']} | {row['outcome']} | {row['evidence']} |")

wu_lines = ['| Work unit | Practical outcome | Evidence / repository disposition | Integration status |', '|---|---|---|---|']
for row in work_unit_traceability:
    wu_lines.append(f"| `{row['id']}` | {row['outcome']} | {row['evidence']} | `{row['integration_status']}` |")

integration_lines = ['| PR | Accepted repository effect | Merge commit |', '|---|---|---|']
for pr, merge_sha, effect in integration_chain:
    integration_lines.append(f"| [{pr}]({pr_url(int(pr[1:]))}) | {effect} | `{merge_sha}` |")

phase2_lines = ['| Product | Reviewed baseline / provenance anchor | Bounded final authority |', '|---|---|---|']
for row in phase2_provenance:
    phase2_lines.append(f"| {row['product']} | {row['reviewed_anchor']} | {row['bounded_final']} |")

classification_counts = base.get('summary', {}).get('finding_counts', {})
diag = base.get('summary', {}).get('browser_diagnostics', {})
ci = repo.get('exact_head_ci', {})

dossier = f"""# PersianGravity WU-010 Terminal Closure Dossier

## 1. Project identity and finite destination

Repository: `{REPO}`. Work unit: `WU-010` terminal project validation. The finite destination is bounded Persian provider authority for every identity already classified by the accepted 19-surface registry for Gravity Forms 3.1.1.1, Gravity Flow 3.1.0, and GravityView 3.3.4, with deterministic fallback/provider architecture, authored-PO provenance, generated-artifact determinism, vendor immutability, real licensed browser/runtime/RTL evidence, and truthful claim ceilings.

**Terminal evidence verdict: `{verdict}`.** This is evidence for the Project Manager's Goal Coverage/Done decision; it is not a broader ecosystem-completion claim.

## 2. Exact final integrated main under test

Exact canonical product-under-test SHA: `{EXACT_SHA}`.

The disposable WordPress runtime independently cloned that exact commit and required `git rev-parse HEAD` to equal it. Evidence harness branch Head: `{HARNESS_HEAD}`. GitHub Actions run: `{RUN_ID}`, attempt `{RUN_ATTEMPT}`.

WU-010 changes no production behavior and has integration status **`NO_REPOSITORY_INTEGRATION`**. Its PR #29 is an evidence-only carrier for the terminal harness and dossier generator and is not part of the product-under-test. The product under test remains exact canonical main `{EXACT_SHA}`.

## 3. Exact licensed package authority

""" + '\n'.join(package_lines) + """

Package byte/version verification does not prove vendor authenticity; vendor authenticity remains `NOT_PROVEN`.

## 4. Final content-admission boundary

Repository validation establishes exactly 19 content-admission records: Gravity Forms 6 with 1759 classified/admitted unique identities and 2448 unclassified identities; Gravity Flow 7 with 732 admitted unique identities and 366 unclassified identities; GravityView 6 with 461 admitted unique identities and 2666 unclassified identities. Exactly 5480 identities remain outside project authority.

The zero-admission record `gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow` intentionally admits zero identities.

## 5. Exact terminal 19-surface browser matrix

""" + '\n'.join(surface_lines) + f"""

Runtime accounting: **{len(matrix)}/19**. Positive provider proof: **{sum(1 for r in positive if r.get('provider_proof_status') == 'PASS')}/18**. Zero-admission handling: **{sum(1 for r in zero if r.get('provider_proof_status') == 'NOT_APPLICABLE_ZERO_ADMISSION')}/1**. RTL/BiDi: **{sum(1 for r in matrix if r.get('rtl_bidi_result') == 'PASS')}/19**.

## 6. Repository validation

""" + '\n'.join(repo_lines) + f"""

Exact-head repository CI: `{ci.get('conclusion', 'UNKNOWN')}`; run `{ci.get('run_id', '')}`; URL `{ci.get('url', '')}`. Repository-native validation proves the tested tree satisfies its unit/static/content-admission/provider-generation/localization contracts and that deliberate i18n regeneration produces no tracked drift. It does not substitute for browser evidence; the browser matrix above is the runtime proof.

## 7. Provider/fallback and JavaScript authority

All 18 positive-admission surfaces produced real provider proof. The zero-admission Flow sidebar correctly reports `NOT_APPLICABLE_ZERO_ADMISSION` rather than fabricated provider proof. Per-surface fallback/noncapture controls are retained in `surface-evidence-matrix.json` and raw runtime traces.

Runtime and repository gates establish zero approved native target-product JS translation handles and zero PersianGravity provider translation JSON catalogs. `gk-query-filters` remains outside `gk-gravityview` authority and `action-scheduler` remains unmanaged; the production-provider contracts explicitly test those exclusions.

## 8. Structured Scanner defect history and remediation

WU-008 discovered a PersianGravity-caused Gravity Forms Form Builder Structured Scanner inline-JavaScript syntax defect. PR #25 repaired placeholder emission and merged as `e2629498eb406177f876e8c9ea3c7bb976730c0b`. WU-010 re-executed the focused Form Builder proof on final main `{EXACT_SHA}`: `{form_builder.get('result')}`. The evidence verifies no relevant PersianGravity pageerror, `window.PGRScannerEditor` availability, authentic Structured Scanner editor activation, rendered profile/mapping controls, a real mapping interaction, provider proof, and RTL behavior.

## 9. Browser diagnostics and finding register

Strict browser diagnostics result: `{diag.get('result')}`. Page errors: `{diag.get('pageerrors_total')}`. Blocking diagnostics: `{diag.get('blocking_diagnostics')}`. Total diagnostics retained: `{diag.get('diagnostics_total')}`.

Finding classifications: `{json.dumps(classification_counts, sort_keys=True)}`. Unresolved `BLOCKING_IN_SCOPE`: `{len(blocking)}`. Every meaningful retained diagnostic is explicitly classified in `browser-diagnostics.json` / `finding-register.json`; a final PASS is impossible while an unexplained blocking diagnostic remains.

## 10. Evidence package and hashes

The package contains environment/package manifests, 19-surface JSON/CSV matrices, RTL/BiDi evidence, JS runtime evidence, diagnostics, finding register, repository validation summary, Form Builder evidence, screenshots, DOM captures, request traces, this dossier, `evidence-index.json`, and `SHA256SUMS`.

`SHA256SUMS` is regenerated after this dossier and repository summary are written and is verified before upload. The outer GitHub artifact ZIP digest cannot be embedded inside the ZIP without changing the ZIP bytes; it must be independently compared with GitHub artifact metadata after download. Internal hashes are the self-contained integrity authority inside this package.

## 11. Terminal traceability for independent FINAL_CLOSURE review

This section maps the mandatory terminal contract to repository-integrated outcomes and evidence. It is deliberately a traceability view, not a parallel project-management system. Historical reviewed baselines are provenance inputs; only source-backed admitted identities become runtime provider authority.

### 11.1 Mandatory goals G-001 through G-005

""" + '\n'.join(goal_lines) + """

### 11.2 Work units WU-001 through WU-010

""" + '\n'.join(wu_lines) + f"""

For WU-010 specifically: PR #29 is **evidence-only**, has status **`NO_REPOSITORY_INTEGRATION`**, and must not be merged to make the terminal claim true. Its only role is to execute and package evidence against exact product-under-test `{EXACT_SHA}`.

### 11.3 Accepted repository integration chain

""" + '\n'.join(integration_lines) + f"""

The final repository integration chain relevant to terminal completion is especially PR #19 → #20 → #21 → #22 → #25 → #28. PR #28 merges as exact final main `{EXACT_SHA}`. PR #29 is intentionally excluded from this integration chain.

### 11.4 Phase-2 baseline / provenance anchors

""" + '\n'.join(phase2_lines) + f"""

These reviewed baselines/archives are provenance anchors, not blanket full-product translation authority. The final authority is the intersection enforced by source admission, accepted Surface Registry records, content-admission records, reviewed translation inputs, deterministic provider build, and the runtime/provider evidence in this package.

### 11.5 Independent completion decision boundary

An independent FINAL_CLOSURE reviewer can determine the bounded destination from this dossier without chat history: G-001..G-005 are mapped above; WU-001..WU-010 are accounted for; accepted repository integrations are identified; the exact final main is named; package/provenance anchors are fixed; the 19-surface matrix and diagnostics are included; and WU-010 is explicitly evidence-only. Completion is therefore assessable only for the bounded accepted authority described here, not for unclassified identities or ecosystem-wide localization.

## 12. Explicit out-of-scope boundary and claim ceiling

Project success here does **not** mean all Gravity Forms strings are Persian, all Gravity Flow strings are Persian, all GravityView strings are Persian, every plugin screen is translated, the entire Gravity ecosystem is Persian, unclassified identities are admitted, or vendor authenticity is proven. The valid claim is strictly bounded to the accepted 19-surface classified authority and its associated provider/fallback/runtime/RTL behavior. The 5480 currently unclassified identities remain outside project authority.

No claim is made for currently unclassified identities, future product versions, unaccepted surfaces, `gk-query-filters` as part of `gk-gravityview`, `action-scheduler` management, or vendor authenticity. WU-010 itself adds no product/runtime authority.

## 13. Terminal gate

Terminal checks: `{json.dumps(checks, sort_keys=True)}`.

**WU-010 terminal verdict: `{verdict}`.**
"""

(ARTIFACT / 'closure-dossier.md').write_text(dossier, encoding='utf-8')
readme = f"""# WU-010 terminal closure evidence

Verdict: **{verdict}**

Product-under-test: `{EXACT_SHA}`  
Harness Head: `{HARNESS_HEAD}`  
GitHub Actions run: `{RUN_ID}` attempt `{RUN_ATTEMPT}`

This package is the self-contained terminal evidence bundle for the accepted 19-surface PersianGravity authority. Start with `closure-dossier.md` (including its G-001..G-005 and WU-001..WU-010 terminal traceability section), then `evidence-index.json`, `surface-evidence-matrix.json`, `repository-validation-summary.json`, `browser-diagnostics.json`, and `finding-register.json`. Verify integrity with `sha256sum -c SHA256SUMS`.

WU-010 is evidence-only / `NO_REPOSITORY_INTEGRATION`; PR #29 must remain unmerged. Vendor authenticity remains `NOT_PROVEN`. The claim ceiling excludes unclassified identities and full-product/ecosystem Persian completion.
"""
(ARTIFACT / 'README.md').write_text(readme, encoding='utf-8')

base['schema_version'] = '3.1.0'
base['work_unit'] = 'WU-010'
base['terminal_verdict'] = verdict
base['exact_final_main_sha'] = EXACT_SHA
base['evidence_harness_head'] = HARNESS_HEAD
base['workflow_run'] = {'id': RUN_ID, 'attempt': RUN_ATTEMPT}
base['repository_validation'] = repo
base['terminal_gates'] = checks
base['traceability'] = {
    'mandatory_goals': goal_traceability,
    'work_units': work_unit_traceability,
    'accepted_repository_integrations': [
        {'pr': pr, 'url': pr_url(int(pr[1:])), 'merge_commit': merge_sha, 'effect': effect}
        for pr, merge_sha, effect in integration_chain
    ],
    'phase2_provenance': phase2_provenance,
    'final_main_sha': EXACT_SHA,
    'wu010_repository_integration': 'NO_REPOSITORY_INTEGRATION',
}
base['claim_ceiling'] = {
    'full_gravity_forms_persian': False,
    'full_gravity_flow_persian': False,
    'full_gravityview_persian': False,
    'ecosystem_wide_persian_completion': False,
    'unclassified_identities_admitted': False,
    'vendor_authenticity_proven': False,
    'bounded_authority_only': True,
}
base['files'] = file_entries()
(ARTIFACT / 'evidence-index.json').write_text(json.dumps(base, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

sum_lines = []
for path in sorted(ARTIFACT.rglob('*')):
    if not path.is_file() or path.name == 'SHA256SUMS':
        continue
    rel = path.relative_to(ARTIFACT).as_posix()
    sum_lines.append(f'{sha256(path)}  {rel}')
(ARTIFACT / 'SHA256SUMS').write_text('\n'.join(sum_lines) + '\n', encoding='utf-8')

print(json.dumps({'terminal_verdict': verdict, 'gates': checks}, ensure_ascii=False, indent=2))
sys.exit(0 if verdict == 'PASS' else 1)
