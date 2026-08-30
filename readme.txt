=== Persian Gravity Forms ===
Contributors: rezahh107
Requires at least: 6.7
Requires PHP: 8.2
Stable tag: 4.0.0
Tags: gravity forms, persian, iran, national id, jalali

Small, focused Persian and Iranian enhancements for Gravity Forms.

== Description ==

Persian Gravity Forms provides generic Persian/Iranian functionality for Gravity Forms without owning typography, workflow business rules, payment gateways, or translations for other plugins.

Current capabilities:

* Iranian National ID field using the Gravity Forms `GF_Field` architecture (`pgr_national_id`).
* Dedicated Jalali Date field using its own `GF_Field` type (`pgr_jalali_date`).
* Server-authoritative Iranian National ID checksum validation and Persian/Arabic digit normalization.
* Server-authoritative Jalali parsing and validation across field-owned presentation formats.
* Canonical Jalali persistence as ASCII `YYYY-MM-DD`; the stored value retains Jalali calendar semantics and is never implicitly converted to Gregorian.
* Ordinary Gravity Forms Date fields remain untouched.
* Optional typing-time digit normalization for National ID UX.
* Optional form-level Persian/Arabic digit normalization before entry values are saved.
* Iranian address type and province choices.
* Iranian Rial (IRR) and Toman (IRT) currency definitions.
* Standard WordPress localization for Persian Gravity Forms' own strings only.

This plugin intentionally does not provide fonts, Gravity Flow/GravityView localization, payment gateways, workflow rules, SRWF-specific behavior, or legacy PersianGravity compatibility layers.

== Requirements ==

* WordPress 6.7 or newer.
* PHP 8.2 or newer.
* Gravity Forms 3.0 or newer.

PHP 8.3 is the recommended production target for the current Gravity Forms stack. The repository CI runs its unit/runtime-characterization test suite on PHP 8.2, 8.3, 8.4, and 8.5. A licensed WordPress + Gravity Forms integration environment remains a separate validation layer.

== Installation ==

1. Install and activate Gravity Forms 3.0 or newer.
2. Upload and activate Persian Gravity Forms.
3. Open a Gravity Forms Form Editor.
4. Add `Iranian National ID` or `Jalali Date` from Advanced Fields.
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

== Development ==

Install development dependencies with Composer and run:

`composer test`
`composer cs`
`composer compat`

Generate the plugin-owned translation template with WP-CLI:

`composer i18n:pot`

The production plugin does not require Composer at runtime.

== Validation note ==

Repository CI verifies shipped PHP syntax, WordPress Coding Standards, PHPCompatibility, runtime-integrity guards, and PHPUnit across the configured PHP matrix. CI does not by itself prove browser/UI behavior in a real licensed Gravity Forms installation, so frontend/editor integration should be manually or automatically exercised before release when field rendering behavior changes.

== Changelog ==

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
