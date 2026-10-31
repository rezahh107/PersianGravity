# Scanner Profile identity / atomic delete — evidence checkpoint

**Classification: qualification-only. No production repair.**  
Fresh canonical `main` verified as `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7`.  
Draft qualification PR: [#104](https://github.com/rezahh107/PersianGravity/pull/104), submitted Head `f1503c2e060e349708bb645bf12d2f2e4b4b6212`.  
[Exact-Head WU008 run](https://github.com/rezahh107/PersianGravity/actions/runs/41234928792) and its **published evidence gate** completed successfully. The independent immutable [new runtime report](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/report.json) is bound to source Head `f1503c2e060e349708bb645bf12d2f2e4b4b6212` and evidence commit `9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2`.

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

The PR's new test-only MU hook injects a changed B at the exact second Registry read after handler validation. **New observed negative control:** The real authenticated WU008 POST, test-only hook, and actual Registry API persisted changed B between the handler check and Registry delete read; the old A confirmation deleted B. The [published marker](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/interleaving-marker.json) measured `mutation_elapsed_ms=1.404` for the injected mutation only (not the full HTTP operation or two-worker latency). The new evidence contains **8 original scenarios + 1 interleaving scenario, 24 JPEG screenshots**, complete [readable image index](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/README.md) and unchanged GFAPI mappings. This is a deterministic **same-request** interleaving, **not** a two-worker transaction; concurrent caches remain **NOT_PROVEN**.

## Approval gate

See [focused decision](https://github.com/rezahh107/PersianGravity/blob/qualification/scanner-incarnation-atomic-delete-20261031/docs/SCANNER_PROFILE_INCARNATION_ATOMICITY_DECISION.md). The Owner must authorize a canonical incarnation identity and explicit v1 migration/rollback policy **plus** a host-compatible conditional write method with verified cache consistency. No schema migration, UI change, production fix, merge, release or deploy is part of this qualification.

Machine-readable details: [report.json](report.json). No raw WP sessions, cookies or nonces are stored.
