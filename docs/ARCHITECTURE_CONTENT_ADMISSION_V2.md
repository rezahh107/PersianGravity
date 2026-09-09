# Content Admission v2 — deterministic multi-record authority

Originating Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.
Current production extension: `WU-005 — PGR-GRAVITYFLOW-STATUS-BOUNDED-CONTENT-ADMISSION-02`.

This contract generalizes bounded content admission without changing the closed localization architecture: **Shared Core + Declarative Product Manifests + Bounded Adapters**. It changes development-time content authority only; runtime fallback semantics remain provider entry first, then upstream/vendor/TranslationsPress content, then source English.

## Authority layers

Source admission and translation-content admission remain separate. A valid product identity, package/source admission, manifest entry, or Surface Registry entry does not by itself authorize runtime translated content.

`tools/i18n/admission/content.json` revision 2 contains zero or more independently validated records. A record identity is the exact tuple:

`product + domain + locale + target_version + surface_id`

Duplicate tuples fail closed. Production currently contains exactly two Gravity Flow 3.1.0 records:

1. Inbox — `gravityflow::workflow_runtime::admin_page:gravityflow-inbox`;
2. Status — `gravityflow::workflow_runtime::admin_page:gravityflow-status`.

No third production content admission is authorized by WU-005.

## Independent record validation

Each record is validated without product-specific or surface-specific branches. Validation binds the record to:

- the runtime product manifest's product/domain/version and empty script map;
- validated source admission package/POT/version/message-count evidence;
- a registered Surface Registry identity owned by the same product;
- the exact registered source-path rules;
- a repository-relative evidence index whose raw SHA-256, identity set, path membership, message count, keyset fingerprint, and path fingerprint match the record;
- a repository-relative sparse record PO whose raw SHA-256 and canonical identity set exactly match that record;
- non-fuzzy, non-empty, plural-complete translations with existing placeholder/markup/URL/entity/literal token preservation;
- the admitted translation-content fingerprint; and
- explicit zero native JS handles and zero generated translation JSON.

Evidence entry ordering is not authority: semantically equivalent ordering is normalized before set fingerprints are computed.

The Status record is additionally bounded by its registered source-path rule exactly `includes/pages/class-status.php`; the evidence-derived set contains 43 canonical identities from the reviewed Gravity Flow 3.1.0 Persian baseline.

## Deterministic per-product union

Validated records are grouped by product and sorted by record identity tuple. The product aggregate is the set union of canonical gettext identities.

When an identity occurs in more than one record, identical translation content is counted once. Different translation content for the same canonical identity is a hard validation failure; input order never selects a winner.

For the production Gravity Flow Inbox + Status pair:

- Inbox count: 255;
- Status count: 43;
- identical shared identities: 10;
- aggregate unique admitted count: 288;
- full Gravity Flow source census, reported separately: 1098.

The aggregate records at least:

- sorted validated admissions;
- unique admitted message count;
- aggregate keyset SHA-256;
- aggregate translation-content SHA-256;
- exact aggregate sparse provider PO path and SHA-256; and
- zero JS activation/generation state.

The committed product source PO at `languages/providers/<product>/source/fa_IR.po` must equal this union exactly. Extra, missing, duplicate, fuzzy, empty, incomplete-plural, or token-unsafe identities fail closed.

## Provenance and generated metadata

Per-product `source/provenance.json` separates source evidence from content authority using:

- `content_admission_revision`;
- `content_admissions[]` for sorted individual admissions; and
- `content_aggregate` for the validated product union.

Deprecated singular authority fields such as `admitted_surface_id` are rejected when a product has validated content admissions.

Generated `metadata.json` mirrors the validated multi-record state under `content_admission` with `revision`, `state`, `admissions`, and `aggregate`. Metadata is evidence only and is not consumed by runtime.

## Build and runtime boundary

`tools/i18n/build.php` grants partial runtime catalog generation only when a validated product aggregate exists and the committed product PO matches the aggregate message count and SHA-256. A product with valid source admission but zero validated content records has no content authority; metadata-only products remain runtime dormant.

WU-005 preserves the existing Inbox authority independently while extending the Gravity Flow aggregate with Status. Locked Inbox fingerprints remain:

- admitted count: `255`;
- keyset: `446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a`;
- path fingerprint: `6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09`;
- translation fingerprint: `6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f`.

The resulting aggregate artifacts are:

- provider PO: `bc52c11763b536e44e977a1417d9096a1e3086f016b0a31206291340f411b757`;
- MO: `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640`;
- `.l10n.php`: `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

All Gravity Flow product script maps remain empty. No native JavaScript translation handle or Gravity Flow translation JSON is activated by WU-005. Non-admitted keys retain upstream/vendor/TranslationsPress fallback.
