# Content Admission v2 — deterministic multi-record authority

Work Unit: `WU-001 — PGR-I18N-MULTI-CONTENT-ADMISSION-GENERALIZATION-01`.

This contract generalizes the previously proven single Gravity Flow Inbox admission without changing the closed localization architecture: **Shared Core + Declarative Product Manifests + Bounded Adapters**. It changes development-time content authority only; runtime fallback semantics remain provider entry first, then upstream/vendor/TranslationsPress content, then source English.

## Authority layers

Source admission and translation-content admission remain separate. A valid product identity, package/source admission, manifest entry, or Surface Registry entry does not by itself authorize runtime translated content.

`tools/i18n/admission/content.json` revision 2 contains zero or more independently validated records. A record identity is the exact tuple:

`product + domain + locale + target_version + surface_id`

Duplicate tuples fail closed. Production currently contains exactly one record: Gravity Flow 3.1.0 Inbox (`gravityflow::workflow_runtime::admin_page:gravityflow-inbox`). No second production content admission is introduced by this Work Unit.

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

## Deterministic per-product union

Validated records are grouped by product and sorted by record identity tuple. The product aggregate is the set union of canonical gettext identities.

When an identity occurs in more than one record, identical translation content is counted once. Different translation content for the same canonical identity is a hard validation failure; input order never selects a winner.

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

The production Gravity Flow Inbox PO and derived artifacts are intentionally unchanged:

- provider PO: `ac77a1812d8edcf0264b4f4ffa3bfb918a3915929f3dd35847df4d062d14b570`
- MO: `cefef16940557a5b4586972d5abeb3be3f0b2f17eba8a53db2f14f30c543d04b`
- `.l10n.php`: `731d54d4a612533fe614293328c9912cd59b267cd49585416a4c6aaaeb48f983`

All product script maps remain empty. No native JavaScript translation handle or product translation JSON is activated by this Work Unit.
