# WU-004 — GravityView 3.3.4 source admission

This document records source admission only. It does **not** admit Persian translation content, activate JavaScript translation handles, prove vendor-original authenticity, or claim browser validation.

## Exact source authority

- Product/version: GravityView `3.3.4`.
- Owner-authorized package SHA-256: `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`.
- Project source authority: `OWNER_SUPPLIED_EXACT_PACKAGE`.
- Vendor authenticity: `NOT_PROVEN`.
- Vendor POT: absent from the exact package. `vendor_pot_sha256` is therefore `null`; no POT hash is inferred or fabricated.

Deterministic literal-gettext extraction records `3127` canonical `gk-gravityview` identities, including `127` contextual and `42` plural identities, with `3931` source references. The canonical keyset SHA-256 is `3b533294de818bd7e772533512424571c85e5aa7bccb78cfe06b8b6820645c95`; the source-reference index SHA-256 is `4332466a4f585fd66110b7158d0bbfbf1947ef9e4a97bb924e9c0800fbf64c34`. One non-literal gettext call is recorded separately rather than guessed into the canonical keyset. Reproducible domain/source evidence is in `tools/i18n/admission/gravityview-source-evidence.json`.

## Domain ownership

The package carries `gravitykit/query-filters` `v2.16.0` as a Composer dependency and GravityView source calls into it. Its `gk-query-filters` gettext domain is therefore represented as a bounded dependency/domain boundary, not folded into `gk-gravityview`. The dependency census is `97` canonical identities, `16` contexts, `0` plurals and `104` references. PersianGravity does not manifest this domain at runtime and WU-004 activates no translation handle for it.

`action-scheduler` is likewise identified as a third-party Composer dependency and excluded from the GravityView provider boundary. These independent dependency boundaries mean the current single managed GravityView domain remains safely expressible without architectural invention; `BLOCKED_BY_DOMAIN_MODEL` is not triggered.

## Surface Registry and first recommendation

Six GravityView surfaces are source-path classified with no semantic catch-all: `461` of `3127` identities are classified, `2666` remain unclassified, and `6` identities are multi-surface. Control types and RTL flags are evidence metadata, not browser-success claims.

The reproducible first recommendation is `gravityview::frontend_runtime::shortcode:gravityview`. Its expected domain is `gk-gravityview`; its sole source-path rule is `src/Shortcode/GravityViewShortcode.php`; it contains exactly two canonical identities, both unique to that surface, with zero shared/multi-surface identities. The path requires no source-proven native JavaScript translation attachment. It remains subject to the normal RTL/BiDi checklist; real licensed browser validation is `NOT_RUN`.

## JavaScript and RTL/BiDi boundary

The JavaScript census distinguishes native translation attachments, PHP-localized data, a static `wp-i18n` path without a native attachment, and Script Module API absence. Two source `wp_set_script_translations()` attachment sites are recorded: a fixed Divi handle and dynamically generated Gutenberg block editor handles. These are evidence only: `includes/localization/products.php` keeps the GravityView `scripts` map empty and WU-004 activates zero runtime handles.

RTL/BiDi evidence keys the existing checklist to all admitted GravityView surfaces. No production RTL patch is added and real browser execution remains `NOT_RUN`/environment-unavailable for this source-admission work unit.

## Content/runtime dormancy

`languages/providers/gravityview/source/fa_IR.po` remains an empty catalog scaffold. Committed production translated count is zero. No GravityView provider MO, `.l10n.php`, or translation JSON is authorized or generated. Source admission therefore remains distinct from translation-content admission and generated runtime artifacts.
