# Persian Gravity Forms

Focused Persian/Iranian capabilities for Gravity Forms with one canonical `PGR_*` runtime.

- Plugin version: `4.8.0`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`
- Text domain: `persian-gravityforms`

## Current capabilities

Current repository source exposes six bounded default-enabled modules plus one bounded opt-in module. Upgrade preserves the six existing defaults, while `jalali_presentation` remains disabled until explicitly enabled:

- `national_id` — `pgr_national_id`, server-authoritative checksum validation, canonical ten-ASCII-digit storage, native No Duplicates normalization, optional typing normalization.
- `jalali_date` — `pgr_jalali_date`, server-side Jalali validation and canonical ASCII `YYYY-MM-DD` storage with Jalali semantics.
- `jalali_presentation` — opt-in Jalali presentation of explicitly admitted authoritative Gregorian/system dates; current verified surfaces are Gravity Forms Entries List `date_created`, exact Gravity Flow 3.1.0 Inbox `date_created`/`last_updated`/`due_date`, Status `date_created`/`workflow_timestamp`, Entry Detail Submitted / Last Updated / Due / Expiration, Timeline/history with inherited Print presentation, and exact GravityView 3.3.4 `date_created`/`date_updated`. Stored/native timestamps, DB/API/query/search/sort/filter semantics and workflow-owned operational values remain host-owned; unsupported locale/context/version/source states fail closed to native output.
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

G-008 adds a source-owned Borkowski-lineage Gregorian→Jalali engine, typed `PGR_Jalali_Presentation` facade, and bounded presentation adapters. The validated product range is Gregorian `1800-01-01..2124-03-19`; dates outside it retain native presentation. Instant conversion applies the target/site timezone before calendar conversion and preserves local time-of-day. Date-only conversion never timezone-shifts.

Exact GravityView 3.3.4 additionally admits only `date_created` and `date_updated` through `PGR_GravityView_Jalali_Presentation_Adapter` on the two field-specific output hooks. The adapter reads the authoritative raw UTC Entry property from `Template_Context`, delegates visible formatting to `PGR_Jalali_Presentation`, and never reparses GravityView's localized display string. Module-disabled, non-Persian, malformed context, and exact-version drift states return native GravityView output; DB/GFAPI/REST values and GravityView/GFAPI sorting remain unchanged.

The original Gravity Forms seam remains `gform_entries_field_value` for `date_created`. Exact Gravity Flow 3.1.0 additionally admits Inbox `date_created`/`last_updated`/`due_date`, Status-table `date_created`/`workflow_timestamp`, and the Entry Detail workflow-info family Submitted / Last Updated / Due / Expiration through a bounded composed `gravityflow_date_format_entry_detail` + exact-marker `date_i18n` mechanism. Timeline/history initial and stored event dates are admitted through the exact-version/source/caller-chain-bounded `PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter`. The same Timeline adapter keeps digit shaping in a separate row-local context bound to the host's own time-format/date_i18n path; under `fa_IR` it changes only visible ASCII time glyphs such as `12:01` → `۱۲:۰۱` without parsing or recalculating the time. Print owns no independent calendar or digit-shaping engine; the Print path that reuses the verified Timeline renderer inherits both presentations. Raw `date_created`, DB/GFAPI/REST values, note/event identity, bodies and ordering, workflow/assignment state, timestamp/timezone semantics, deadline/overdue/expiration state, query/sort/filter behavior, raw compare values, operational getter counts and CSV/export remain native. Unsupported locale, format, version, source or caller/context drift fails closed to native output. Exact Flow 3.1.0 Status `due_date` and Entry Detail Scheduled remain evidence-qualified final no-admission calendar surfaces. Separately, under `fa_IR`, the Entry Detail status-box digit adapter may shape ASCII digits to Persian glyphs only in rendered `.gravityflow-status-box-field` text nodes. Entry ID link/query values, element attributes, `data-*`, IDs, form/control values, APIs and stored workflow values remain native ASCII; non-Persian locale and module-disabled requests do not load that Entry Detail digit adapter. Scheduled text may receive glyph shaping when visibly rendered in that status box even though its calendar conversion remains unadmitted. Timeline/history and Print remain outside the Entry Detail digit adapter and use their own narrower Timeline time seam. Print does not render the workflow sidebar. Full expansion evidence is in [`docs/G008_SYSTEM_DATE_EXPANSION.md`](docs/G008_SYSTEM_DATE_EXPANSION.md); converter provenance/range details remain in [`docs/G008_JALALI_PRESENTATION.md`](docs/G008_JALALI_PRESENTATION.md).

## Structured Scanner contract

The Scanner field is `displayOnly` and transient. Its raw multiline capture has no Gravity Forms submission `name` and is not intentionally persisted as a Scanner field value. Parsed outputs map only to ordinary `text` and `hidden` fields. Mapping changes are planned atomically and fail closed.

Built-in `sayad_v01` uses `segments_v1` and outputs, in order: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id`. It is structural only: no Sayad checksum authority, bank validity, cross-bank guarantee, online inquiry, workflow, or payment behavior is implied.

## Help and i18n

The complete bilingual Help Center ships locally with the plugin and is version-controlled. Native `WP_Screen` contextual help on PersianGravity admin pages links into the corresponding full topic. No runtime Internet documentation fetch or third-party frontend framework is used.

Translation assets for `fa_IR` ship in `languages/` as POT, PO and MO. These root assets serve the `persian-gravityforms` UI domain. The separate provider directory holds the bounded ecosystem localization foundation described below.

## Gravity ecosystem localization foundation

Localization is cross-cutting infrastructure outside the module registry. Its resolvers register immediately after plugin constants, independently of module state and optional vendor classes.

برای دامنه‌های `gravityforms`، `gravityflow` و `gk-gravityview` زیرساخت مشترک ترجمهٔ فارسی اضافه شده است. اگر ترجمهٔ ارائه‌دهنده موجود باشد اولویت دارد؛ در غیر این صورت ترجمهٔ upstream/TranslationsPress باقی می‌ماند و بعد از آن متن اصلی نمایش داده می‌شود. هیچ فایل فروشنده یا updater تغییر نمی‌کند.

**وضعیت provider به‌صورت source-bounded و fail-closed است:** targetهای مالک GF `3.1.1.1`، Flow `3.1.0` و GravityView `3.3.4` هستند. هر سه محصول اکنون revision-3 `CONTENT_ADMITTED_FULL` هستند. Gravity Forms شش surface تاریخی `1759`-identity را بدون تغییر نگه می‌دارد و `2448` identity remainder را می‌پذیرد (`4207/4207`)؛ کلید stale فقط-POT همچنان خارج از authority است. Gravity Flow نیز `732` identity تاریخی + `366` remainder را به `1098/1098` می‌رساند. GravityView شش surface تاریخیِ همان `461` identity را حفظ می‌کند و یک `PRODUCT_REMAINDER` مستقل دقیقاً `2666` identity باقیمانده را پس از دو دور review می‌پذیرد؛ union source-authority نهایی `3127/3127` است. یک collision دقیق singular/plural در gettext باعث می‌شود projection اجرایی GravityView `3126` runtime entry داشته باشد، اما هر دو source identity جداگانه review/admit شده‌اند و این alias در evidence قفل شده است. در مجموع سه census قفل‌شده `8432/8432` source identity پذیرفته دارند. برای هر سه محصول script map خالی و native JS/provider translation JSON برابر صفر است. `gk-query-filters` مرز dependency/domain جداگانه باقی می‌ماند. package bytes/version قفل و بررسی شده‌اند و `vendor_authenticity=NOT_PROVEN` همچنان فقط یک سقف ادعای اصالت vendor است؛ full content authority برای این نسخه‌های دقیق به معنی پوشش نسخه‌های آینده، همهٔ UIها یا کل اکوسیستم نیست.

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

G-007 adds exact-version, source-bounded `fa_IR` provider authority for Gravity Perks 2.3.16 (`gravityperks`, 83/83), GP File Upload Pro 1.5.13 (`gp-file-upload-pro`, 39/39), and GP Advanced Select 1.1.21 (`gp-advanced-select`, 5/5). Cross-domain calls remain outside these three catalogs. File Upload Pro’s uploader labels use its real PHP-gettext → `wp_localize_script()` path. GP Advanced Select’s bounded RTL adapter is gated to exact 1.1.21 + `fa_IR`/RTL + `gp-advanced-select-tom-select` and scoped to its authentic Tom Select wrapper. Current WU008 exact-package browser evidence verifies the visible File Upload Pro labels/RTL behavior and the Advanced Select caret/padding plus LTR fail-closed control. No vendor package bytes or vendor files are committed or modified. See `docs/G007_GRAVITY_PERKS_LOCALIZATION.md`, `docs/VALIDATION_G007.md`, and `docs/G009_RTL_BIDI_COMPATIBILITY.md`.
