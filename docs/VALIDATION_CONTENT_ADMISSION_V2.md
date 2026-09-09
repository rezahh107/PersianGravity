# Content Admission v2 validation record

Originating Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.
Current production extension: `WU-005 — PGR-GRAVITYFLOW-STATUS-BOUNDED-CONTENT-ADMISSION-02`.

WU-005 required base: `main@8c8a7642fa8362775bc0730c6db3e00dede3eb3a`.

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

Production-focused tests bind revision 2 to exactly the Gravity Flow Inbox and Status records. They verify:

- Status source evidence is exactly within `includes/pages/class-status.php`;
- Status contains exactly 43 canonical identities from reviewed baseline SHA-256 `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9`;
- Inbox locked count/keyset/path/content fingerprints remain unchanged;
- the two records share exactly 10 identities with identical translation rows;
- the aggregate is the deterministic 288-identity set union and remains distinct from the 1098-message source census;
- tampered Status keyset/path/content/evidence/provider fingerprints fail closed;
- out-of-surface Status evidence fails even if its raw file hash is recomputed;
- removing Status authority restores Inbox-only 255-message content authority rather than relying on product identity;
- every Gravity Flow product script map remains empty and no Gravity Flow translation JSON exists; and
- generated MO/`.l10n.php` hashes match the deterministic aggregate.

Locked Status values:

- keyset `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d`;
- path fingerprint `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed`;
- translation fingerprint `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7`;
- evidence-index file `c3583bfb2695095dcd0b65baa78dfb3233e976124dd0a18ba3bd9af1d322dd9e`;
- per-record sparse PO `9d82982c5117ed5e2988e2958dc21ae8b4bd6003453a63b2f8ce38d033271958`.

Locked aggregate values:

- count `288`;
- keyset `58167ac415f0367a5a64dc098273b81bdca07a38641deaf75fd29ff4be42f363`;
- translation fingerprint `00c79c563019f01f68e0c03c9671e3587779cd6757fb830c53d1c2da4ecb4139`;
- provider PO `bc52c11763b536e44e977a1417d9096a1e3086f016b0a31206291340f411b757`;
- MO `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640`;
- `.l10n.php` `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

`composer i18n:check` remains the deterministic build/artifact drift gate. Repository CI additionally runs Composer dependency installation, PHPUnit on PHP 8.2–8.5, WPCS, PHPCompatibility, Structured Scanner Node tests, runtime-integrity checks, and pinned WordPress Core localization contracts on 6.7.2 and 7.1.

## Evidence boundary

Real WordPress + licensed Gravity Forms / Gravity Flow / GravityView browser/UI validation is `NOT_EXECUTED` unless separately recorded with concrete evidence. A green source/unit/Core CI run does not prove visible browser lifecycle behavior, visual RTL correctness, or full Gravity Flow localization.

WU-005 activates no JavaScript translation handle and generates no Gravity Flow translation JSON. Runtime fallback behavior is inherited from the unchanged shared localization core: provider entries win only for admitted identities; missing identities retain upstream/vendor/TranslationsPress fallback and then source English.
