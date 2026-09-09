# Persian Gravity Forms

Focused Persian/Iranian capabilities for Gravity Forms with one canonical `PGR_*` runtime.

- Plugin version: `4.2.0`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`
- Text domain: `persian-gravityforms`

## Current capabilities

PersianGravity 4.2.0 exposes six bounded, source-defined modules. All default to enabled, including on upgrade when `pgr_modules` does not yet exist:

- `national_id` — `pgr_national_id`, server-authoritative checksum validation, canonical ten-ASCII-digit storage, native No Duplicates normalization, optional typing normalization.
- `jalali_date` — `pgr_jalali_date`, server-side Jalali validation and canonical ASCII `YYYY-MM-DD` storage with Jalali semantics.
- `iranian_address` — Iranian Gravity Forms address type (`iran`) plus the 31-province predefined choice list.
- `digit_normalization` — form-level `pgr_normalize_digits` and server-side `gform_save_field_value` digit normalization.
- `iranian_currency` — IRR and IRT currency definitions with zero decimal places.
- `structured_scanner` — transient `pgr_structured_scanner` controller with versioned Scanner Profiles and ordinary-field output mapping.

The Module Manager is not decorative. Disabled modules do not register their owned Gravity Forms fields/hooks and do not enqueue their owned frontend assets. Administrative infrastructure remains available independently of module state and Gravity Forms availability.

## Admin product surface

`Persian Gravity` contains:

1. Overview / Module Manager
2. Scanner Profiles
3. Settings
4. System Status
5. Help & Documentation

Overview always shows Persian and English product labels/descriptions. Persian is primary only for a Persian WordPress user locale; otherwise English is primary. Ordinary interface strings continue to use WordPress gettext.

Disable requests use bounded form-metadata inspection. Detected use blocks disable. When safe non-use cannot be proven, the action requires an explicit second confirmation. The current currency module deliberately uses `UNKNOWN` because site-wide IRR/IRT usage is not reliably enumerable from the documented public Form Object alone.

Scanner Profiles remain available while Structured Scanner runtime is disabled.

## Persistence

- `pgr_modules` — schema version plus boolean state for the six bounded modules; metadata is never stored.
- `pgr_settings` — current plugin settings, including `default_force_english`.
- `pgr_scanner_profiles` — existing versioned Custom Scanner Profiles.
- `pgr_normalize_digits`, field types, Scanner mappings and other field configuration — Gravity Forms form metadata.

Disabling a module does not delete Entries, field definitions, settings, Scanner Profiles, mappings, or form metadata.

## Structured Scanner contract

The Scanner field is `displayOnly` and transient. Its raw multiline capture has no Gravity Forms submission `name` and is not intentionally persisted as a Scanner field value. Parsed outputs map only to ordinary `text` and `hidden` fields. Mapping changes are planned atomically and fail closed.

Built-in `sayad_v01` uses `segments_v1` and outputs, in order: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id`. It is structural only: no Sayad checksum authority, bank validity, cross-bank guarantee, online inquiry, workflow, or payment behavior is implied.

## Help and i18n

The complete bilingual Help Center ships locally with the plugin and is version-controlled. Native `WP_Screen` contextual help on PersianGravity admin pages links into the corresponding full topic. No runtime Internet documentation fetch or third-party frontend framework is used.

Translation assets for `fa_IR` ship in `languages/` as POT, PO and MO. These root assets serve the `persian-gravityforms` UI domain. The separate provider directory holds the bounded ecosystem localization foundation described below.

## Gravity ecosystem localization foundation

Localization is cross-cutting infrastructure outside the six-module registry. Its resolvers register immediately after plugin constants, independently of module state and optional vendor classes.

برای دامنه‌های `gravityforms`، `gravityflow` و `gk-gravityview` زیرساخت مشترک ترجمهٔ فارسی اضافه شده است. اگر ترجمهٔ ارائه‌دهنده موجود باشد اولویت دارد؛ در غیر این صورت ترجمهٔ upstream/TranslationsPress باقی می‌ماند و بعد از آن متن اصلی نمایش داده می‌شود. هیچ فایل فروشنده یا updater تغییر نمی‌کند.

**وضعیت provider همچنان جزئی و source-bounded است:** targetهای مالک GF `3.1.1.1`، Flow `3.1.0` و GravityView `3.3.4` هستند. Gravity Forms در این مرز فقط source-admitted است و GravityView همچنان package-unavailable باقی می‌ماند. برای Gravity Flow، runtime content authority اکنون دقیقاً دو surface مرورشده دارد: Inbox (`255` identity) و Status (`43` identity) با `10` identity مشترک یکسان؛ union نهایی `288` identity یکتا از source census مستقل `1098` است. Inbox fingerprintهای قفل‌شده بدون تغییر مانده‌اند. همهٔ Gravity Flow script handleها همچنان خالی‌اند و هیچ translation JSON تولید نمی‌شود. این وضعیت نه ترجمهٔ کامل Gravity Flow است و نه browser/licensed integration proof.

`composer i18n:check` سازگاری source و metadata، content-admission fingerprints و deterministic artifact drift را کنترل می‌کند. برای تولید آگاهانه `composer i18n:build` و برای آزمون Core، `PGR_WP_CORE=/path/to/wordpress composer i18n:test` را اجرا کنید. PO هنگام ساخت دست‌نخورده می‌ماند. فایل‌های `.mo` و `.l10n.php` فقط برای محتوای معتبر و ثبت‌شده ساخته می‌شوند و JSON تنها برای handleهای صریحاً مجاز ممکن است.

ثبت resolverها فوری و بدون بارگذاری ترجمه است. درخواست‌هایی که پیش از بارگذاری خود PersianGravity رخ داده‌اند خارج از این مرز هستند. جزئیات source/load-order در [`docs/LOCALIZATION.md`](docs/LOCALIZATION.md) و قرارداد multi-record content authority در [`docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md`](docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md) ثبت شده‌اند.

## Release

قبل از release باید حداقل این موارد هم‌راستا باشند:

- plugin header / `PGR_VERSION`
- `readme.txt` Stable tag
- Composer PHP baseline
- CI matrix
- changelog
- production package contents

این تطبیق شاخه، شناسهٔ فعلی `4.2.0` را حفظ می‌کند و نسخه یا انتشار جدید ایجاد نمی‌کند.

مستندات contributor/agent: [`AGENTS.md`](AGENTS.md)

## Development and validation

```sh
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
composer i18n:check
PGR_WP_CORE=/path/to/pinned/core composer i18n:test
```

Own UI POT extraction uses `composer i18n:pot` when deliberately updating source assets. CI preserves GNU-gettext PO/POT/MO validation, provider drift checks, pinned Core localization contracts, syntax, WPCS, PHPCompatibility, Scanner JavaScript tests, runtime-integrity guards, repository consistency, and PHPUnit on the supported PHP matrix. Source/unit tests are not browser proof; real WordPress + Gravity Forms validation is recorded separately in `docs/VALIDATION.md`.

## Scope

PersianGravity does not own Gravity Flow workflows, GravityView business behavior, SRWF-specific business logic, fonts, payment gateways, online Sayad inquiry, OCR/camera scanning, custom databases, or arbitrary third-party translations. Only explicitly manifested generic `fa_IR` overlays are permitted; vendor files and updaters remain vendor-owned.
