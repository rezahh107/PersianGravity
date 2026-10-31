# Scanner Profile identity / atomic delete — evidence checkpoint

**Classification: qualification-only. No production repair.**  
Fresh canonical `main` verified as `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7`.  
Draft qualification PR: [#104](https://github.com/rezahh107/PersianGravity/pull/104), submitted Head `f1503c2e060e349708bb645bf12d2f2e4b4b6212`.  
[New WU008 run](https://github.com/rezahh107/PersianGravity/actions/runs/41234928792) was **IN_PROGRESS at this checkpoint**; no new WU008 PASS or race observation is claimed.

## Historical real-runtime negative controls

Read the [immutable genuine WU008 report](https://github.com/rezahh107/PersianGravity/blob/eab6ba4e8fa0a282fa399034d4f4700304d5aefd/scanner-incarnation-evidence/report.json) and [screenshots](https://github.com/rezahh107/PersianGravity/tree/eab6ba4e8fa0a282fa399034d4f4700304d5aefd/scanner-incarnation-evidence). That earlier qualification executed **8 cases and 22 screenshots**, with genuine WordPress 6.8.3, PHP 8.2.35, GF 3.1.1.1, real Registry methods and authenticated browser session. It was bound to qualification Head `c0c36ee7dab609b04fe585ba775052e2e7159040` and an earlier reviewed production Head, **not** the new PR Head.

| Case | Historical observed outcome | Classification |
| --- | --- | --- |
| unchanged A | A deleted | Legitimate success |
| A already missing | Old confirmation refused | Safe control |
| recreated B, changed label and definition | B survived | Safe control |
| recreated B, same label but different definition | B survived | Safe control |
| in-place enabled-only edit | Modified A survived | Safe control |
| in-place output-label edit | Modified A survived | Safe control |
| fresh B confirmation | Old A refused; fresh B deleted | Safe control |
| recreated B, same ID **and identical content** | **Old A confirmation deleted B** | **CONFIRMED DEFECT** |

**Measurements grounded in historic evidence:** 8 executed cases, 22 JPEG snapshots, WordPress 6.8.3, PHP 8.2.35. No new timing, performance, cross-worker concurrency or persistent-cache result is fabricated.

## Current implementation boundary

At exact reviewed main, the Registry persists version-1 custom profiles in a single non-autoloaded option and normalizes away unknown fields. Its fingerprint equals content, not object incarnation. The admin handler verifies A, then invokes an ID-only Registry delete that re-reads the option; `update_option` has no expected-old-value condition. See [Registry](https://github.com/rezahh107/PersianGravity/blob/c7c5377e93f5f4f704acc797dc67ea8554b5dfd7/includes/class-pgr-scanner-profile-registry.php) and [admin handler](https://github.com/rezahh107/PersianGravity/blob/c7c5377e93f5f4f704acc797dc67ea8554b5dfd7/admin/class-pgr-admin.php).

The PR's new test-only MU hook injects a changed B at the exact second Registry read after handler validation. **This new interleaving test is not yet proven executed**; if WU008 runs and passes the publication gate, look for a separate source-bound run-attempt evidence branch, `interleaving-marker.json`, two JPEGs, and `boundary_interleaving` in that report. This is a deterministic same-request interleaving, not a two-worker transaction.

## Approval gate

See [focused decision](https://github.com/rezahh107/PersianGravity/blob/qualification/scanner-incarnation-atomic-delete-20261031/docs/SCANNER_PROFILE_INCARNATION_ATOMICITY_DECISION.md). The Owner must authorize a canonical incarnation identity and explicit v1 migration/rollback policy **plus** a host-compatible conditional write method with verified cache consistency. No schema migration, UI change, production fix, merge, release or deploy is part of this qualification.

Machine-readable details: [report.json](report.json). No raw WP sessions, cookies or nonces are stored.
