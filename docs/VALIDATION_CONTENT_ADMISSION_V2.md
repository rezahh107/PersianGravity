# Content Admission v2 validation record

Originating Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.
Current production extensions:
- `WU-005 — PGR-GRAVITYFLOW-STATUS-BOUNDED-CONTENT-ADMISSION-02`;
- `WU-007 — PGR-GRAVITYFORMS-FRONTEND-FIRST-BOUNDED-CONTENT-ADMISSION-01`.
Current recovery: `WU-PR15-COORD-01` recovery continuation.

WU-005 required base: `main@8c8a7642fa8362775bc0730c6db3e00dede3eb3a`.
The Gravity Forms WU-007 identity scope remains exactly 41 messages from `form_display.php`; PR #15 changes only seven locked Persian translations within that same set.

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

Production-focused Gravity Flow tests are product-scoped against the global manifest. They verify that production may also contain Gravity Forms records while the Gravity Flow subset remains exactly Inbox plus Status. A third Gravity Flow record makes the exact-subset assertion fail. Dedicated Status fixtures filter to Gravity Flow before copying/validating product-specific evidence, so removing Status restores Inbox-only authority without requiring unrelated Gravity Forms fixture files. Existing validator-level fail-closed coverage for duplicate tuples, unknown/unowned surfaces, out-of-surface evidence and fingerprint drift remains unchanged.

The Gravity Flow tests additionally verify:

- Status source evidence is exactly within `includes/pages/class-status.php`;
- Status contains exactly 43 canonical identities from reviewed baseline SHA-256 `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9`;
- Inbox locked count/keyset/path/content fingerprints remain unchanged;
- the two records share exactly 10 identities with identical translation rows;
- the aggregate is the deterministic 288-identity set union and remains distinct from the 1098-message source census;
- tampered Status keyset/path/content/evidence/provider fingerprints fail closed;
- a repaired Status PO parses the required `Language`, `X-Domain`, and `Plural-Forms` headers;
- a temporary Status fixture whose valid header `\n` escapes are changed back to literal double-escaped `\\n` is rejected with `Invalid sparse provider PO headers`;
- out-of-surface Status evidence fails even if its raw file hash is recomputed;
- removing Status authority restores Inbox-only 255-message content authority rather than relying on product identity;
- every Gravity Flow product script map remains empty and no Gravity Flow translation JSON exists; and
- generated MO/`.l10n.php` hashes match the deterministic aggregate.

Current Gravity Flow values remain unchanged:

- Status keyset `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d`;
- Status path fingerprint `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed`;
- Status translation fingerprint `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7`;
- Status evidence-index file `c3583bfb2695095dcd0b65baa78dfb3233e976124dd0a18ba3bd9af1d322dd9e`;
- aggregate count `288`;
- aggregate keyset `58167ac415f0367a5a64dc098273b81bdca07a38641deaf75fd29ff4be42f363`;
- aggregate translation fingerprint `00c79c563019f01f68e0c03c9671e3587779cd6757fb830c53d1c2da4ecb4139`;
- aggregate provider PO `838c2409841c099d17d475e6290ec8fff49ad237331cd527f3e25769d2162267`;
- MO `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640`;
- `.l10n.php` `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

## Gravity Forms WU-007 / PR #15 semantic recovery

Gravity Forms production authority is exactly one record:

`gravityforms::frontend_runtime::shortcode:gravityform`

Its source-path rule is exactly `form_display.php`; admitted count remains `41` from the separate `4207`-message source census. The keyset remains `827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb` and the path fingerprint remains `5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177`.

The WU-003 reviewed full baseline SHA-256 `2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1` is historical reviewed-input provenance. PR #15 applies exactly seven semantic corrections to the admitted sparse provider source, so the corrected final catalog is not described as a byte-for-byte untouched subset of WU-003. The corrected content fingerprint is `1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6` and the sparse/aggregate PO SHA-256 is `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`.

`GravityFormsFrontendContentAdmissionTest` mechanically asserts all seven exact English=>Persian mappings, retains the 41-message count and locked identity/path scope, verifies a known non-admitted current identity remains absent, and verifies Gravity Forms scripts/translation JSON remain empty. Generated artifact bytes are development outputs of `composer i18n:build`; `composer i18n:check` must reproduce them without drift. No validator or runtime fallback logic is changed by this recovery.

## Deterministic generation and repository gates

`composer i18n:build` is the deliberate source-to-artifact generation path. After source/provenance reconciliation, committed `.mo`, `.l10n.php` and generated `metadata.json` must be the bytes produced by that path. `composer i18n:check` remains the non-mutating deterministic drift gate.

Repository CI additionally runs Composer validation/dependency installation, PHPUnit on PHP 8.2–8.5, WPCS, PHPCompatibility, Structured Scanner Node tests, runtime-integrity checks, pinned WordPress Core localization contracts on 6.7.2 and 7.1, and provider integrity/drift validation. Exact PASS/FAIL claims are bound to the resulting PR state only.

## Evidence boundary

Real WordPress + licensed Gravity Forms / Gravity Flow / GravityView browser/UI validation is `NOT_EXECUTED` unless separately recorded with concrete evidence. A green source/unit/Core CI run does not prove visible browser lifecycle behavior, visual RTL correctness, full-product localization, or licensed vendor integration.

WU-005, WU-007 and the PR #15 recovery activate no JavaScript translation handle and generate no Gravity Forms or Gravity Flow translation JSON. Runtime fallback behavior is inherited from the unchanged shared localization core: provider entries win only for admitted identities; missing identities retain upstream/vendor/TranslationsPress fallback and then source English.
