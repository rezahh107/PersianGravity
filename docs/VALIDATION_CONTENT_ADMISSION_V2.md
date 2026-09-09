# Content Admission v2 validation record

Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.

Required base: `main@a4ede14bddd9dad180e4e47d44ea541668900c4c`.

This record distinguishes repository/source evidence from licensed browser integration. Exact final-Head command and CI outcomes belong to the pull request and execution handoff; an unexecuted check is never promoted to PASS.

## Automated contract coverage

`ContentAdmissionGeneralizationTest` uses synthetic, non-production fixtures to cover:

- N >= 2 admissions and multiple products without Gravity Flow/Inbox/version/count branches;
- identical shared-identity deduplication and conflicting-translation rejection;
- duplicate record tuple rejection;
- unknown surface and out-of-surface evidence rejection;
- product/domain/version source/manifest mismatch;
- package, POT, reviewed-baseline hash/count, evidence-index, keyset, path and translation-content drift;
- aggregate sparse provider extra/missing identity rejection;
- record-order and permitted evidence-order fingerprint stability; and
- valid source admission with zero content records yielding zero content authority.

Production-focused tests additionally bind the sole Gravity Flow Inbox record to revision 2, verify the aggregate count/keyset/content fingerprint, verify all product script maps remain empty, prove no product translation JSON appears, and assert the locked production hashes:

- provider PO `ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570`;
- MO `cefef16940557a5b4586972d5abeb3be3f0b2f17eba8a53db2f14f30c543d04b`;
- `.l10n.php` `731d54d4a612533fe614293328c9912cd59b267cd49585416a4c6aaaeb48f983`.

`composer i18n:check` remains the deterministic build/artifact drift gate. The repository CI additionally runs Composer dependency installation, PHPUnit, WPCS, PHPCompatibility, Structured Scanner Node tests, and pinned WordPress Core localization contracts.

## Evidence boundary

No licensed Gravity Forms / Gravity Flow / GravityView browser environment is required for this infrastructure-only Work Unit. Real WordPress + licensed Gravity product browser/UI validation is therefore `NOT_EXECUTED` unless separately recorded with concrete evidence.

This Work Unit does not claim visual RTL validation, product browser lifecycle validation, or any new JavaScript translation activation. Runtime fallback behavior is inherited from the unchanged shared localization core and remains covered by the existing Core localization contract suite.
