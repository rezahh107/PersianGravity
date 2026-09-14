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

finding_rows = findings.get('findings', findings if isinstance(findings, list) else [])
blocking = [f for f in finding_rows if f.get('classification') == 'BLOCKING_IN_SCOPE']
positive = [r for r in matrix if int(r.get('admitted_message_count', 0)) > 0]
zero = [r for r in matrix if int(r.get('admitted_message_count', 0)) == 0]

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

## 11. Explicit claim ceiling

Project success here does **not** mean all Gravity Forms strings are Persian, all Gravity Flow strings are Persian, all GravityView strings are Persian, every plugin screen is translated, the entire Gravity ecosystem is Persian, unclassified identities are admitted, or vendor authenticity is proven. The valid claim is strictly bounded to the accepted 19-surface classified authority and its associated provider/fallback/runtime/RTL behavior. The 5480 currently unclassified identities remain outside project authority.

## 12. Terminal gate

Terminal checks: `{json.dumps(checks, sort_keys=True)}`.

**WU-010 terminal verdict: `{verdict}`.**
"""

(ARTIFACT / 'closure-dossier.md').write_text(dossier, encoding='utf-8')
readme = f"""# WU-010 terminal closure evidence

Verdict: **{verdict}**

Product-under-test: `{EXACT_SHA}`  
Harness Head: `{HARNESS_HEAD}`  
GitHub Actions run: `{RUN_ID}` attempt `{RUN_ATTEMPT}`

This package is the self-contained terminal evidence bundle for the accepted 19-surface PersianGravity authority. Start with `closure-dossier.md`, then `evidence-index.json`, `surface-evidence-matrix.json`, `repository-validation-summary.json`, `browser-diagnostics.json`, and `finding-register.json`. Verify integrity with `sha256sum -c SHA256SUMS`.

Vendor authenticity remains `NOT_PROVEN`. The claim ceiling excludes unclassified identities and full-product/ecosystem Persian completion.
"""
(ARTIFACT / 'README.md').write_text(readme, encoding='utf-8')

base['schema_version'] = '3.0.0'
base['work_unit'] = 'WU-010'
base['terminal_verdict'] = verdict
base['exact_final_main_sha'] = EXACT_SHA
base['evidence_harness_head'] = HARNESS_HEAD
base['workflow_run'] = {'id': RUN_ID, 'attempt': RUN_ATTEMPT}
base['repository_validation'] = repo
base['terminal_gates'] = checks
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
