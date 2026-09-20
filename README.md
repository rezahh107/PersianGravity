# Persian Gravity Forms

Focused Persian/Iranian capabilities for Gravity Forms with one canonical `PGR_*` runtime.

- Plugin version: `4.5.0`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`
- Text domain: `persian-gravityforms`

## Current capabilities

PersianGravity 4.5.0 exposes six bounded default-enabled modules plus one bounded opt-in module. Upgrade preserves the six existing defaults, while `jalali_presentation` remains disabled until explicitly enabled:

- `national_id` — `pgr_national_id`, server-authoritative checksum validation, canonical ten-ASCII-digit storage, native No Duplicates normalization, optional typing normalization.
- `jalali_date` — `pgr_jalali_date`, server-side Jalali validation and canonical ASCII `YYYY-MM-DD` storage with Jalali semantics.
- `jalali_presentation` — opt-in Jalali presentation of authoritative Gregorian/system dates; V1 is bounded to Gravity Forms Entries List `date_created` and never changes the stored/native value.
- `iranian_address` — Iranian Gravity Forms address type (`iran`) plus the 31-province predefined choice list.
- `digit_normalization` — form-level `pgr_normalize_digits` and server-side `gform_save_field_value` digit normalization.
- `iranian_currency` — IRR and IRT currency definitions with zero decimal places.
- `structured_scanner` — transient `pgr_structured_scanner` controller with versioned Scanner Profiles and ordinary-field output mapping.

The Module Manager is not decorative. Disabled modules do not register their owned Gravity Forms fields/hooks and do not enqueue their owned frontend assets. Administrative infrastructure remains available independently of module state and Gravity Forms availability.

`jalali_date` and `jalali_presentation` are intentionally separate. The first owns true Jalali-domain user data; the second only presents known Gregorian/system dates and cannot be activated by the presence of a `pgr_jalali_date` field.

## Admin product surface

`Persian Gravity` contains:

1. Overview / Module Manager
2. Scanner Profiles
3. Settings
4. System Status
5. Help & Documentation

Overview always shows Persian and English product labels/descriptions. Persian is primary only for a Persian WordPress user locale; otherwise English is primary. Ordinary interface strings continue to use WordPress gettext.

Disable requests use bounded form-metadata inspection. Detected use blocks disable. When safe non-use cannot be proven, the action requires an explicit second confirmation. The current currency module deliberately uses `UNKNOWN` because site-wide IRR/IRT usage is not reliably enumerable from the documented public Form Object alone. `jalali_presentation` owns no persisted form configuration, so it is always safe to disable.

Scanner Profiles remain available while Structured Scanner runtime is disabled.

## Persistence

- `pgr_modules` — schema version plus boolean state for seven bounded modules; source-default merging keeps pre-G-008 states intact and leaves the new presentation module disabled until opt-in.
- `pgr_settings` — current plugin settings, including `default_force_english`.
- `pgr_scanner_profiles` — existing versioned Custom Scanner Profiles.
- `pgr_normalize_digits`, field types, Scanner mappings and other field configuration — Gravity Forms form metadata.

Disabling a module does not delete Entries, field definitions, settings, Scanner Profiles, mappings, or form metadata.

## G-008 Jalali system-date presentation

G-008 adds a source-owned Borkowski-lineage Gregorian→Jalali engine, typed `PGR_Jalali_Presentation` facade, and one bounded Gravity Forms adapter. The V1 `VALIDATED_PRODUCT_RANGE` is Gregorian `1800-01-01..2124-03-19`; dates outside it retain native presentation. Instant conversion applies the target/site timezone before calendar conversion and preserves local time-of-day. Date-only conversion never timezone-shifts.

The V1 Gravity Forms seam is `gform_entries_field_value` for `date_created` only. Gravity Forms' raw UTC Entry value, database value, REST/API value, sorting and filtering semantics remain native. Full provenance, license attribution, range evidence, fallback rules and verification details are in [`docs/G008_JALALI_PRESENTATION.md`](docs/G008_JALALI_PRESENTATION.md).

## Structured Scanner contract

The Scanner field is `displayOnly` and transient. Its raw multiline capture has no Gravity Forms submission `name` and is not intentionally persisted as a Scanner field value. Parsed outputs map only to ordinary `text` and `hidden` fields. Mapping changes are planned atomically and fail closed.

Built-in `sayad_v01` uses `segments_v1` and outputs, in order: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id`. It is structural only: no Sayad checksum authority, bank validity, cross-bank guarantee, online inquiry, workflow, or payment behavior is implied.

## Help and i18n

The complete bilingual Help Center ships locally with the plugin and is version-controlled. Native `WP_Screen` contextual help on PersianGravity admin pages links into the corresponding full topic. No runtime Internet documentation fetch or third-party frontend framework is used.

Translation assets for `fa_IR` ship in `languages/` as POT, PO and MO. These root assets serve the `persian-gravityforms` UI domain. The separate provider directory holds the bounded ecosystem localization foundation described below.

## Gravity ecosystem localization foundation

Localization is cross-cutting infrastructure outside the module registry. Its resolvers register immediately after plugin constants, independently of module state and optional vendor classes.

برای دامنه‌های `gravityforms`، `gravityflow` و `gk-gravityview` زیرساخت مشترک ترجمهٔ فارسی اضافه شده است. اگر ترجمهٔ ارائه‌دهنده موجود باشد اولویت دارد؛ در غیر این صورت ترجمهٔ upstream/TranslationsPress باقی می‌ماند و بعد از آن متن اصلی نمایش داده می‌شود. هیچ فایل فروشنده یا updater تغییر نمی‌کند.

**وضعیت provider به‌صورت source-bounded و fail-closed است:** targetهای مالک GF `3.1.1.1`، Flow `3.1.0` و GravityView `3.3.4` هستند. Gravity Forms اکنون revision-3 `CONTENT_ADMITTED_FULL` است: شش surface تاریخی بدون تغییر روی union دقیق `1759` identity باقی مانده‌اند و یک `PRODUCT_REMAINDER` مستقل دقیقاً `2448` identity باقیمانده را پس از دو دور semantic review می‌پذیرد؛ union نهایی `4207/4207` با canonical keyset قفل‌شده است و کلید stale فقط-POT وارد authority نشده است. Gravity Flow نیز revision-3 و کامل `1098/1098` باقی می‌ماند (`732` تاریخی + `366` remainder). GravityView همچنان revision-2 و bounded است: شش surface آن `461` identity از census `3127` را می‌پذیرند و `2666` identity بعدی هنوز خارج از authority هستند. برای هر سه محصول script map خالی است؛ Gravity Forms و Gravity Flow `0` native JS handle و `0` provider translation JSON دارند. `gk-query-filters` مرز dependency/domain جداگانه باقی می‌ماند. package bytes/version قفل و بررسی شده‌اند، اما `vendor_authenticity=NOT_PROVEN` است و تکمیل GravityView یا کل G-006 هنوز ادعا نمی‌شود.

`composer i18n:check` سازگاری source و metadata، content-admission fingerprints و deterministic artifact drift را کنترل می‌کند. برای تولید آگاهانه `composer i18n:build` و برای آزمون Core، `PGR_WP_CORE=/path/to/wordpress composer i18n:test` را اجرا کنید. PO هنگام ساخت دست‌نخورده می‌ماند. فایل‌های `.mo` و `.l10n.php` فقط برای محتوای معتبر و ثبت‌شده ساخته می‌شوند و JSON تنها برای handleهای صریحاً مجاز ممکن است.

ثبت resolverها فوری و بدون بارگذاری ترجمه است. درخواست‌هایی که پیش از بارگذاری خود PersianGravity رخ داده‌اند خارج از این مرز هستند. جزئیات source/load-order و مدل revision-3 `PRODUCT_REMAINDER` در [`docs/LOCALIZATION.md`](docs/LOCALIZATION.md) و شواهد validation در [`docs/VALIDATION.md`](docs/VALIDATION.md) ثبت شده‌اند؛ سند v2 تاریخی همچنان معماری surface-admission اولیه را توضیح می‌دهد.

## Release

قبل از release باید حداقل این موارد هم‌راستا باشند:

- plugin header / `PGR_VERSION`
- `readme.txt` Stable tag
- Composer PHP baseline
- CI matrix
- changelog
- production package contents

ادغام تغییرات عادی یا تغییر خود سامانهٔ انتشار، به‌تنهایی نسخه یا انتشار تولید نمی‌کند. تغییر نسخه فقط از مسیر **Prepare Release** و انتشار فقط پس از تأیید صریح مالک از مسیر **Publish Release** انجام می‌شود.

مستندات contributor/agent: [`AGENTS.md`](AGENTS.md)

## Development and validation

```sh
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
node --test tests/js/g008-jalali-oracles.test.js
composer i18n:check
PGR_WP_CORE=/path/to/pinned/core composer i18n:test
```

Own UI POT extraction uses `composer i18n:pot` when deliberately updating source assets. CI preserves GNU-gettext PO/POT/MO validation, provider drift checks, pinned Core localization contracts, syntax, WPCS, PHPCompatibility, Scanner JavaScript tests, runtime-integrity guards, repository consistency, and PHPUnit on the supported PHP matrix. G-008 additionally runs exhaustive ICU/reference differential verification and an exact Gravity Forms 3.1.1.1 Entries List runtime gate. Source/unit tests are not browser proof; real WordPress + Gravity Forms validation is recorded separately in `docs/VALIDATION.md`.

## Scope

PersianGravity does not own Gravity Flow workflows, GravityView business behavior, SRWF-specific business logic, fonts, payment gateways, online Sayad inquiry, OCR/camera scanning, custom databases, or arbitrary third-party translations. Only explicitly manifested generic `fa_IR` overlays are permitted; vendor files and updaters remain vendor-owned.

## G-007 — Gravity Perks family localization

G-007 adds exact-version, source-bounded `fa_IR` provider authority for Gravity Perks 2.3.16 (`gravityperks`, 83/83), GP File Upload Pro 1.5.13 (`gp-file-upload-pro`, 39/39), and GP Advanced Select 1.1.21 (`gp-advanced-select`, 5/5). Cross-domain calls remain outside these three catalogs. File Upload Pro’s uploader labels are covered through its PHP-gettext → `wp_localize_script()` path, and a bounded RTL adapter targets only GP Advanced Select’s exact `gp-advanced-select-tom-select` style handle. No vendor package bytes or vendor files are committed or modified. Exact package/source/build evidence and the browser-evidence ceiling are in `docs/G007_GRAVITY_PERKS_LOCALIZATION.md` and `docs/VALIDATION_G007.md`.
