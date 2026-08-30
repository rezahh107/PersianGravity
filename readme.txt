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
* Server-side Persian/Arabic digit normalization.
* Optional typing-time digit normalization for National ID UX.
* Bounded Jalali date support for Gravity Forms date fields; Jalali values are stored as entered with ASCII digits and are not silently converted to Gregorian dates.
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
* Removed historical field IDs, migrations, legacy `GFPersian_*` architecture, typography/font delivery, payment/RSS code, and external-plugin translation interception.
* Retained and bounded generic Iranian address, currency, digit-normalization, and Jalali functionality.
* Rebuilt tests and CI around the shipped runtime.
