# G-010 / WU-06 — current-facing Goal Coverage and handoff truth

**Assessment date:** 2026-10-25. **Inspected merged base:** `main@1c7bc7248cf28b634517627082eceb37f1eaa9fb` (PR #80 merge). **Scope:** documentation/agent reconciliation only. This is an engineering evidence assessment for Owner review, not a declaration that the overall project is Done or that a documentation PR has already merged.

## Authority and source hierarchy

The active Owner decisions in the project's complete `GOAL_AUTHORITY`, including the September 27 G-010 Entry Detail four-date exception, forward Owner-supplied package requalification protocol, four-parent WU-05 generalization, and October 23 G-011 addition/refinement, take precedence over dated planning handoffs. Current repository source, exact merged PRs and workflow results provide implementation/execution evidence. Historical snapshots remain historical and are never silently rewritten as current. Relevant repository rules: `AGENTS.md`.

## Complete goal coverage — reviewed against current destination

| Goal / work | Current evidence and disposition | Important limit |
| --- | --- | --- |
| Earlier G-001–G-005 surface/real-UI project | Prior accepted closure evidence retained under Owner authority; not reopened or re-executed by this documentation batch | This WU-06 review is not a fresh 19-surface browser qualification |
| G-006 full-source translation | Accepted exact-source coverage: GF `4207/4207`, Flow `1098/1098`, GravityView `3127/3127`, per merged source/content evidence and current admission manifests | Source-bound versions only; does not qualify newer vendor versions |
| G-007 bounded Gravity Perks family | Achieved exact-package `83+39+5=127` source-backed translations plus admitted uploader/RTL integration; browser evidence is maintained under the separate G-007/WU008 validation history | No automatic enrollment of Spellbook or child addons beyond locked versions |
| G-008 Jalali date presentation | Achieved only admitted typed presentation surfaces, canonical Gregorian/raw semantics protected | Excludes unadmitted Status due-date, Entry Detail Scheduled, and unqualified newer packages |
| G-009 RTL/BiDi | Achieved under its bounded exact-host validation evidence | Not a promise of future upstream-version browser compatibility |
| G-010 WU-01 | **Merged / evidenced.** Canonical qualification source `tools/compatibility/gravityflow-package.json` remains Owner-supplied Flow `3.1.0`, SHA-256 `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | Not an authority to substitute new licensed packages |
| G-010 WU-02 | **Merged / evidenced.** Small `PGR-GFLOW-*` capability diagnostics, report-only bounded latest snapshot | Never controls activation |
| G-010 WU-03 | **Merged / evidenced** for direct Inbox/Status contracts and Entry Detail digit contract; four Entry Detail workflow-info date surfaces retain an explicit permanent Owner-approved exact-target-version exception | No claim of a safe version-independent date-family seam or newer-Flow qualification |
| G-010 WU-04 | **Merged / evidenced.** Timeline uses caller/data/time/marker behavioral compatibility instead of exact-version/whole-file runtime hash veto; Print inherits only when actually traversed | Synthetic drift does not qualify a genuine newer package |
| G-010 WU-05 | **Merged, real manual nonpersistent and persistent paths executed.** Runs `40086954962`, `40130703147`; #79/#81 repairs and PR #80 informational publication merged | Official Flow source freshness is inconsistent; globally latest version is **NOT_PROVEN** |
| G-010 WU-06 | **Documentation-only PR proposed for Owner review**; current-facing guidance reconciled in this branch | Not integrated until Owner reviews/merges and post-merge source/CI are checked |
| G-011 Confirmation date Merge Tags | **Mandatory, NOT IMPLEMENTED.** October 23 Owner expansion/refinement remains distinct, requiring exact GF `3.1.1.1` source and real confirmation/browser proof | Prevents any **overall project** Done/Closed declaration after G-010 |

## G-010 closeout decision about the external-source discrepancy

**Direct evidence:** two official HTML body fingerprints led to different first release headings: `3.1.2.1` (`fac0e6b6...`, run `40086954962`) versus `3.1.2` (`f2024bb...`, runs `40130703147` and `40175007477`). The governed parser selected a correct heading for its particular received body. In bounded GitHub Actions HTTP probe `40180969275`, eight requests (four ordinary/four no-cache) returned `3.1.2` and did not resolve the disagreement. CDN vs origin vs intermediate cache causation remains unresolved. These are genuine source observations but **not** proof that the radar always finds the objectively newest published version.

**Decision under existing Owner authority:** G-010 / WU-05 requires a bounded four-parent first-party informational monitoring and provenance/review/publication process, with narrow fail-closed behavior, not an absolute freshness SLA or redundant upstream release-oracle. Both manual execution modes and actual observation PR publication are verified. Thus the remaining external-source freshness limitation is a **documented reliability risk**, *not by itself a material unmet G-010 acceptance requirement*. G-010 is **eligible for closeout assessment after this documentation PR merges and exact final/integrated checks are reviewed**, rather than blocked forever by repeated uninformative probes. It is **not yet declared closed** while WU-06 is open. This disposition neither changes Owner requirements nor asserts the remote site is now consistent.

**Bounded safeguard recommendation (not implemented or pre-authorized here):** if future source-consistency alarms or the Owner's desired notification quality justify product change, evaluate a narrowly scoped, first-party multi-representation disagreement detector against captured full responses, with monotonic reviewed evidence and false-positive/fail-closed tests. First demonstrate an independent first-party representation improves detection under the observed two-body discrepancy; only then consider a separate tooling PR. Do **not** replace heading parsing with maximum historical observation, blindly add cache-busting query parameters, auto-resolve contradictory inputs, or relax MODEL_REVIEW_REQUIRED to make the workflow green. The simplest rejected approach—adding `Cache-Control: no-cache` alone—failed the eight-probe live experiment. No perpetual investigation is required to finish this documentary reconciliation.

## Future exact-package qualification / handoff

1. For each explicitly tracked parent, collect official first-party changelog/docs as an informational version-specific research observation. Only the four current parent profiles are enrolled; no automatic addon discovery.
2. Preserve `DETECTED / DOCUMENTED != OWNER_SUPPLIED != TESTED != QUALIFIED != ADMITTED`. Existing Gravity Flow `3.1.1.1` is historical unresolved research and must not be silently recast as a currently proven stable package. `3.1.2` and `3.1.2.1` are documentation/source observations, not qualified products.
3. Owner supplies an exact licensed newer package and approves updating the appropriate package authority. For Flow, resolve only `tools/compatibility/gravityflow-package.json`, check package bytes/version, then compare that version's stored documentation hypothesis with actual source/WordPress/Gravity integration evidence.
4. Evaluate each adapter's own contract; keep native fallback when unproven and record existing bounded diagnostic reasons. For the four Entry Detail dates, execute the Owner-approved package-specific `CURRENT_METHOD_STILL_VALID` / `SAFE_REPLACEMENT_FOUND` / `NO_SAFE_PATH_PROVEN` protocol before altering the exception. Do not consume radar observations or persisted diagnostics as runtime authority.
5. Integrate a separately reviewed package-specific PR only after required authentic qualification; WU-06 does not implement package acquisition, automatic adoption, compatibility expansion, release or G-011.

## Evidence identifiers and preservation

- Read-only manual Radar: [40086954962](https://github.com/rezahh107/PersianGravity/actions/runs/40086954962) / artifact `12557197288`.
- Real persisting manual Radar: [40130703147](https://github.com/rezahh107/PersianGravity/actions/runs/40130703147) / artifact `12574354613`; resulting publication PR [#80](https://github.com/rezahh107/PersianGravity/pull/80).
- Final observation PR checks: [40175007477](https://github.com/rezahh107/PersianGravity/actions/runs/40175007477), artifact `12591642899`.
- Root-cause persistence and fixture fixes: merged PRs [#79](https://github.com/rezahh107/PersianGravity/pull/79) and [#81](https://github.com/rezahh107/PersianGravity/pull/81).
- Bounded first-party HTTP investigation: [40180969275](https://github.com/rezahh107/PersianGravity/actions/runs/40180969275), successful temporary push workflow; diagnostic file removed after evidence collection.
- Official first-party source: <https://docs.gravityflow.io/changelog/>. Fingerprints and heading variants are evidence of differing returned representations, not proof of CDN/origin fault.
- Current locked qualification reference: `tools/compatibility/gravityflow-package.json` at main, Flow `3.1.0`. Prior reviewed `MODEL_REVIEWED` observations, unresolved historical `3.1.1.1` research and all vendor/source files are preserved verbatim.

**Final evidence ceiling:** This documentation-only change does not execute a new licensed browser/product runtime, assert external freshness, qualify a new commercial package, or complete G-011. CI checks on this PR Head, if green, prove only the paths those checks actually exercise.
