# Content Admission v2 — deterministic multi-record authority

Originating Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.
Current production extensions:
- `WU-005 — PGR-GRAVITYFLOW-STATUS-BOUNDED-CONTENT-ADMISSION-02`;
- `WU-007 — PGR-GRAVITYFORMS-FRONTEND-FIRST-BOUNDED-CONTENT-ADMISSION-01`.

This contract generalizes bounded content admission without changing the closed localization architecture: **Shared Core + Declarative Product Manifests + Bounded Adapters**. It changes development-time content authority only; runtime fallback semantics remain provider entry first, then upstream/vendor/TranslationsPress content, then source English.

## Authority layers

Source admission and translation-content admission remain separate. A valid product identity, package/source admission, manifest entry, reviewed full baseline, or Surface Registry entry does not by itself authorize runtime translated content.

`tools/i18n/admission/content.json` revision 2 contains zero or more independently validated records. A record identity is the exact tuple:

`product + domain + locale + target_version + surface_id`

Duplicate tuples fail closed. Production currently contains exactly three bounded records:

1. Gravity Forms frontend shortcode — `gravityforms::frontend_runtime::shortcode:gravityform`;
2. Gravity Flow Inbox — `gravityflow::workflow_runtime::admin_page:gravityflow-inbox`;
3. Gravity Flow Status — `gravityflow::workflow_runtime::admin_page:gravityflow-status`.

No other Gravity Forms or Gravity Flow surface is authorized by those Work Units.

## Independent record validation

Each record is validated without product-specific or surface-specific branches. Validation binds the record to:

- the runtime product manifest's product/domain/version and empty script map;
- validated source admission package/POT/version/message-count evidence;
- a registered Surface Registry identity owned by the same product;
- the exact registered source-path rules;
- a repository-relative evidence index whose raw SHA-256, identity set, path membership, message count, keyset fingerprint, and path fingerprint match the record;
- a repository-relative sparse record PO whose raw SHA-256 and canonical identity set exactly match that record;
- non-fuzzy, non-empty, plural-complete translations with placeholder/markup/URL/entity/literal token preservation;
- the admitted translation-content fingerprint; and
- explicit zero native JS handles and zero generated translation JSON.

Evidence entry ordering is not authority: semantically equivalent ordering is normalized before set fingerprints are computed.

The Gravity Forms frontend record is bounded by the registered source-path rule exactly `form_display.php`. Deterministic tokenizer extraction over the exact 3.1.1.1 source produced 48 gettext calls and 41 unique canonical identities; the set equals the current vendor-POT identities referencing `form_display.php`. The WU-003 full reviewed PO SHA-256 `2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1` remains provenance for the reviewed input. PR #15 applies seven explicit semantic corrections within the same 41-identity set, so the final sparse catalog is not represented as an unmodified exact subset of that baseline. Final locked derived values are:

- admitted count: `41`;
- keyset: `827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb`;
- path fingerprint: `5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177`;
- translation fingerprint: `1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6`;
- evidence-index raw SHA-256: `0e9fb3c7a44f3bc2ae14d25f694a2301be59d7e75fc1d1f0740fb3be94283d22`;
- sparse record PO raw SHA-256: `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`.

The Gravity Flow Status record remains bounded by `includes/pages/class-status.php` with 43 canonical identities. Sparse PO headers remain data-source authority: `StrictPoLoader` must parse `Language: fa_IR`, the exact domain, and `Plural-Forms: nplurals=2; plural=(n > 1);`.

## Deterministic per-product union

Validated records are grouped by product and sorted by record identity tuple. The product aggregate is the set union of canonical gettext identities. When an identity occurs in more than one record, identical translation content is counted once. Different translation content for the same canonical identity is a hard validation failure; input order never selects a winner.

Current aggregates:

- Gravity Forms frontend shortcode: 41 admitted identities from an independent 4207-message source census; because this is the first Gravity Forms content record, the product aggregate is exactly those 41 identities.
- Gravity Flow Inbox + Status: 255 + 43 with 10 identical overlaps = 288 unique admitted identities from an independent 1098-message source census.

The committed product source PO at `languages/providers/<product>/source/fa_IR.po` must equal the validated union exactly. Extra, missing, duplicate, fuzzy, empty, incomplete-plural, token-unsafe, or invalid-header catalogs fail closed.

Three Gravity Forms frontend identities (`Medium`, `Submit`, `Strong`) also have source references matching another registered Gravity Forms surface. They remain single canonical identities; any later independently authorized record must supply identical translation content for them or validation fails closed.

## Provenance and generated metadata

Per-product `source/provenance.json` separates source evidence from content authority using:

- `content_admission_revision`;
- `content_admissions[]` for sorted individual admissions; and
- `content_aggregate` for the validated product union.

Deprecated singular authority fields such as `admitted_surface_id` are rejected when a product has validated content admissions.

Generated `metadata.json` mirrors the validated multi-record state under `content_admission` with `revision`, `state`, `admissions`, and `aggregate`. Metadata is evidence only and is not consumed by runtime. For Gravity Forms, `translation_review` records the WU-003 reviewed baseline as provenance and separately states that the final admitted source includes seven PR #15 semantic corrections; it is not a second authority schema.

## Build and runtime boundary

`tools/i18n/build.php` grants partial runtime catalog generation only when a validated product aggregate exists and the committed product PO matches the aggregate message count and SHA-256. A product with valid source admission but zero validated content records has no content authority; metadata-only products remain runtime dormant.

Gravity Forms WU-007 generates only the aggregate justified by the repaired 41-identity frontend record:

- aggregate provider PO SHA-256: `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`;
- translation-content SHA-256: `1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6`;
- MO SHA-256: `c6c5bbd4fb1decaf1539bd8d4ef58148159e04653d059144572d5481e80ed9bb`;
- `.l10n.php` SHA-256: `a8d1bee39d503e1a99c296f02f64708e57c7ef731db059778b575e20e0b11ca3`.

Its `scripts` map remains empty and no Gravity Forms translation JSON is generated. Non-admitted Gravity Forms identities retain upstream/vendor/TranslationsPress/source-English fallback; this is partial surface coverage, not a full-product Persian claim.

Gravity Flow Inbox fingerprints remain:

- admitted count: `255`;
- keyset: `446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a`;
- path fingerprint: `6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09`;
- translation fingerprint: `6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f`.

Gravity Flow Status fingerprints remain:

- admitted count: `43`;
- keyset: `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d`;
- path fingerprint: `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed`;
- translation fingerprint: `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7`.

The current Gravity Flow aggregate remains 288 identities with MO `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640` and `.l10n.php` `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

All product script maps remain empty. No native JavaScript translation handle is activated by these content-admission records, and no Gravity Forms or Gravity Flow translation JSON is generated.
