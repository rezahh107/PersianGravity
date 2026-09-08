=== Persian Gravity Forms ===
Contributors: rezahh107
Requires at least: 6.7
Requires PHP: 8.2
Stable tag: 4.1.0
Tags: gravity forms, persian, iran, national id, jalali, scanner

Small, focused Persian and Iranian enhancements for Gravity Forms.

== Description ==

Persian Gravity Forms provides generic Persian/Iranian functionality for Gravity Forms without owning typography, workflow business rules, or payment gateways.

Current source capabilities:

* Iranian National ID field using the Gravity Forms `GF_Field` architecture (`pgr_national_id`).
* Dedicated Jalali Date field using its own `GF_Field` type (`pgr_jalali_date`).
* Generic non-persistent Structured Scanner controller field (`pgr_structured_scanner`) with the structural `sayad_v01` profile.
* `sayad_v01` exposes exactly seven ordered outputs: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, and `sayad_id`.
* Structured Scanner captures multiline LF/CRLF input in a transient `textarea`, has no Gravity Forms submission `name`, and does not persist its raw scan payload.
* Structured Scanner maps only to Single Line Text (`text`) and Hidden (`hidden`) fields. Its `scanner_profile` and `scanner_mappings` configuration is field-owned and persisted with the form field configuration.
* Scanner keyboard completion is parser-driven: separator Enter events remain in-progress until the existing parser accepts the full payload; complete Enter and complete idle may finalize; non-empty Tab and paste are explicit finalization paths.
* Scanner mapped-field changes are planned atomically and fail closed for malformed mappings, missing/unsupported/self/duplicate targets, or invalid scan structure.
* Server-authoritative Iranian National ID checksum validation and Persian/Arabic digit normalization.
* Server-authoritative Jalali parsing and validation across field-owned presentation formats.
* Canonical Jalali persistence as ASCII `YYYY-MM-DD`; the stored value retains Jalali calendar semantics and is never implicitly converted to Gregorian.
* Ordinary Gravity Forms Date fields remain untouched.
* Optional typing-time digit normalization for National ID UX.
* Optional form-level Persian/Arabic digit normalization before entry values are saved.
* Iranian address type and province choices.
* Iranian Rial (IRR) and Toman (IRT) currency definitions.
* Standard WordPress localization for Persian Gravity Forms' own strings.
* Partial fa_IR provider foundation for explicitly managed Gravity ecosystem domains; current provider PO scaffolds have no production translations or approved JS handles pending exact source/POT inspection.

The Structured Scanner profile is structural only. It does not establish bank/checksum authority, cross-bank validation, financial business rules, or payment behavior.

This plugin intentionally does not provide fonts, arbitrary third-party localization, payment gateways, workflow rules, SRWF-specific behavior, custom databases, or legacy PersianGravity compatibility layers.

== Requirements ==

* WordPress 6.7 or newer.
* PHP 8.2 or newer.
* Gravity Forms 3.0 or newer.

PHP 8.3 is the recommended production target for the current Gravity Forms stack. The repository CI runs its unit/runtime-characterization test suite on PHP 8.2, 8.3, 8.4, and 8.5. A licensed WordPress + Gravity Forms integration environment remains a separate validation layer.

== Installation ==

1. Install and activate Gravity Forms 3.0 or newer.
2. Upload and activate Persian Gravity Forms.
3. Open a Gravity Forms Form Editor.
4. Add `Iranian National ID`, `Jalali Date`, or, in the current development source, `Structured Scanner` from Advanced Fields.
5. Configure plugin defaults under Settings > Persian Gravity Forms if needed.

== Field behavior ==

= Iranian National ID =

* Accepts Iranian National ID input as a scalar field.
* Persian and Arabic digits are normalized to ASCII server-side.
* Validates the 10-digit Iranian National ID checksum server-side.
* Stores the canonical value as ten ASCII digits.
* Supports Gravity Forms conditional logic and native No Duplicates behavior.

= Jalali Date =

* Uses a dedicated custom field rather than modifying the native Gravity Forms Date field.
* Validates Jalali dates server-side.
* Supports multiple field-owned presentation formats.
* Stores canonical ASCII `YYYY-MM-DD` while preserving Jalali calendar semantics.

= Structured Scanner =

* Field type: `pgr_structured_scanner`.
* Current profile: `sayad_v01`.
* Uses a transient multiline `textarea` capture without a Gravity Forms submission name.
* Raw payload is excluded from save-entry, entry-detail, entry-list, merge-tag, and export value surfaces.
* Only `text` and `hidden` destination fields are supported.
* LF/CRLF segmentation and digit normalization remain owned by the pure Scanner parser.
* Enter/Tab/idle/paste completion decisions are parser-driven and do not introduce a second segment-count authority.
* Successful scans may replace mapped values; failed later scans do not expose a partial update plan.
* Scanner assets load only when a form contains this field and reinitialization uses the supported `gform/post_render` lifecycle.

== Development ==

Install development dependencies with Composer and run:

`composer test`
`composer cs`
`composer compat`
`node --test tests/js/structured-scanner.test.js`

Generate the plugin-owned translation template with WP-CLI:

`composer i18n:pot`

The production plugin does not require Composer at runtime.

== Validation note ==

Repository CI verifies shipped PHP syntax, WordPress Coding Standards, PHPCompatibility, Structured Scanner pure-JavaScript tests, runtime-integrity guards, and PHPUnit across the configured PHP matrix. CI does not by itself prove browser/UI behavior in a real licensed Gravity Forms installation.

For the current Structured Scanner work, the automated source-repair checkpoint passed. Real WordPress + Gravity Forms browser validation remains separate and must not be claimed unless actually executed. See `docs/VALIDATION.md`.

== Changelog ==

= Unreleased =
* Added a shared request-driven localization foundation with declarative manifests, upstream fallback and deterministic build tooling. Exact licensed product/POT/JS surface verification remains blocked; no production translation coverage is claimed.
* Reconciled stale documentation with the existing 4.1.0 runtime identity; no new version or release.
* Added the generic non-persistent Structured Scanner source capability (`pgr_structured_scanner`) with the structural `sayad_v01` profile.
* Added transient multiline capture, parser-driven Enter/Tab/idle/paste completion, atomic mapped-field updates, conditional Scanner assets, and focused regression coverage.
* No plugin version bump or release is performed by this development PR.

= 4.0.0 =
* Consolidated the repository to one `PGR_*` runtime.
* Moved Gravity Forms initialization to `gform_loaded`.
* Replaced removed form-settings integration with `gform_form_settings_fields`.
* Reduced National ID support to one `GF_Field` implementation with one server-side checksum/normalization implementation.
* Added one dedicated Jalali `GF_Field` using canonical ASCII `YYYY-MM-DD` Jalali storage.
* Removed native-Date Jalali modification and obsolete bundled Jalali datepicker replacement paths.
* Removed historical field IDs, migrations, legacy `GFPersian_*` architecture, typography/font delivery, payment/RSS code, and external-plugin translation interception.
* Retained and bounded generic Iranian address, currency, digit-normalization, and Jalali functionality.
* Rebuilt tests and CI around the shipped runtime.
