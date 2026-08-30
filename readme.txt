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

* Iranian National ID field using the Gravity Forms `GF_Field` architecture.
* Dedicated Jalali Date field using its own `GF_Field` type (`pgr_jalali_date`).
* Server-authoritative Jalali parsing and validation across seven field-owned presentation formats.
* Canonical Jalali persistence as ASCII `YYYY-MM-DD`; the stored value retains Jalali calendar semantics and is never implicitly converted to Gregorian.
* Ordinary Gravity Forms Date fields remain untouched, and PersianGravity does not replace Gravity Forms or WordPress datepicker handles.
* Server-side Persian/Arabic digit normalization.
* Optional typing-time digit normalization for National ID UX.
* Iranian address type and province choices.
* Iranian Rial (IRR) and Toman (IRT) currency definitions.
* Standard WordPress localization for Persian Gravity Forms' own strings.

== Requirements ==

* WordPress 6.7 or newer.
* PHP 8.2 or newer.
* Gravity Forms 3.0 or newer.

PHP 8.3 is the recommended production target because it is the current Gravity Forms recommendation. Newer PHP versions are exercised by this repository's unit/runtime-characterization CI matrix, but licensed Gravity Forms integration testing is a separate validation layer.

== Development ==

Install development dependencies with Composer and run:

`composer test`
`composer cs`
`composer compat`

Generate the plugin-owned translation template with WP-CLI:

`composer i18n:pot`

The production plugin does not require Composer at runtime.

== Changelog ==

= 4.0.0 =
* Consolidated the repository to one `PGR_*` runtime.
* Moved Gravity Forms initialization to `gform_loaded`.
* Replaced removed form-settings integration with `gform_form_settings_fields`.
* Reduced National ID support to one `GF_Field` implementation with one server-side checksum/normalization implementation.
* Replaced native-Date Jalali modification with one dedicated Jalali `GF_Field` using canonical ASCII `YYYY-MM-DD` Jalali storage.
* Removed the shared `jquery-ui-datepicker` replacement path and obsolete bundled Jalali datepicker assets.
* Removed historical field IDs, migrations, legacy `GFPersian_*` architecture, typography/font delivery, payment/RSS code, and external-plugin translation interception.
* Retained and bounded generic Iranian address, currency, digit-normalization, and Jalali functionality.
* Rebuilt tests and CI around the shipped runtime.
