# WU-006 — GravityView frontend shortcode bounded content admission

Work Unit: `WU-006 — PGR-GRAVITYVIEW-FIRST-BOUNDED-CONTENT-ADMISSION-01`

This change admits exactly one GravityView 3.3.4 surface through Content Admission v2:

`gravityview::frontend_runtime::shortcode:gravityview`

The managed domain remains `gk-gravityview`. Surface membership is proven only by the explicit source-path rule `src/Shortcode/GravityViewShortcode.php`; semantic matching is not used.

## Authority bindings

- Exact inspected GravityView package SHA-256: `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`.
- Source-admitted `gk-gravityview` census: `3127` canonical identities.
- Exact final-QA WU-002 Persian baseline SHA-256: `2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca` (`3281` canonical identities in the full reviewed baseline).
- The sparse admission copies translations only for the two current `gk-gravityview` identities proven to originate from the authorized shortcode source path.
- The exact source package contains no vendor POT. Source admission therefore remains `vendor_pot_sha256 = null` with `comparison = NOT_APPLICABLE_VENDOR_POT_ABSENT`; no surrogate POT or hash is invented.
- `gk-query-filters` remains a bounded Composer dependency domain and is not merged into, or runtime-activated by, the GravityView provider.

## Admitted subset

Exactly two canonical identities are admitted:

- `3fe848f1b629acdcb7bfd703d8a9787cf579eabb1fc3db0c1ba7bae368ed86de`
- `8d69b8871a337eb30ca4da453d62b8fbae894f7859cbdae3129485f8ded2588c`

Derived fingerprints for this implementation are:

- admitted keyset SHA-256: `061ddb2324a950ef0e64f9c252cec438403031c7a07a5c235dee5e71594729db`;
- admitted surface-path index SHA-256: `881e5750f77fbd1fedb4677d2e0775488ee9ac19ee42679e46ab6c045fe2668c`;
- admitted translation-content SHA-256: `293073a2b4d1a49b8c5becb59fe1f00f5c83e5b578b8b27de57749792368d13d`;
- sparse/aggregate provider PO SHA-256: `3232e2363aeebab7b83af786b2dfd17a2748b4918561e5123bd9cde900387fb4`;
- generated MO SHA-256: `a77b6f4cfb0ad994595f2fb16e09fab5ad11a127a4f7c686636bda5c9ee370d5`;
- generated `.l10n.php` SHA-256: `f6896f38496a97074f079c173683f63f1160dbf5bfb381a319d8f2c283e7de37`.

## Runtime boundary

The GravityView product script map remains empty. This surface activates zero native JavaScript translation handles and generates zero translation JSON files. The generated MO and `.l10n.php` are derived only from the two-message validated aggregate PO.

Provider translations may win only for the two admitted identities. Other current `gk-gravityview` identities remain absent from the provider and therefore continue through the existing upstream/vendor Persian fallback path and then source English. The existing localization runtime tests exercise provider collision precedence, upstream-only fallback and source-string fallback for every manifested product, including `gk-gravityview`.

## State and validation claim

This is bounded `CONTENT_ADMITTED_PARTIAL` / `CONTENT_ADMITTED` evidence for one GravityView surface only. It is not a claim that GravityView is fully Persian, product-complete, ecosystem-complete, or real-browser validated. Real WordPress + licensed GravityView browser/UI validation remains `NOT_RUN` in this Work Unit and is a separate evidence class.
