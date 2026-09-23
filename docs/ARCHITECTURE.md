# Persian Gravity Forms Architecture — 4.6.0

## Runtime topology

```text
persian-gravityforms.php
        |
        +-- PGR_Localization (immediate, load-free resolver registration)
        |
        +-- non-disableable admin infrastructure
        |     +-- PGR_Module_Registry
        |     +-- PGR_Module_Usage
        |     +-- PGR_Product_Admin
        |     +-- PGR_Admin (existing settings/profile implementation)
        |     +-- PGR_Scanner_Profile_Registry
        |     +-- PGR_Help_Catalog
        |     +-- Settings / System Status / Help
        |
        +-- gform_loaded
              +-- module-state class-load gates
              +-- PGR_Core
                    +-- National ID [conditional]
                    +-- Jalali Date [conditional]
                    +-- Jalali system-date presentation [conditional]
                    +-- Iranian Address [conditional]
                    +-- Digit normalization [conditional]
                    +-- Iranian Currency [conditional]
                    +-- Structured Scanner [conditional]
```

This remains one canonical `PGR_*` runtime. There is no service container, generic plugin framework, remote registry, runtime third-party registration API, custom table, CPT, REST toggle endpoint, or SPA.

## Module Registry

`PGR_Module_Registry` is a bounded code-owned catalog for exactly:

- `national_id`
- `jalali_date`
- `jalali_presentation`
- `iranian_address`
- `digit_normalization`
- `iranian_currency`
- `structured_scanner`

Metadata is source-owned: IDs, defaults, bilingual labels/descriptions, help topic, and only minimal field-type metadata. The option never stores callbacks, class names, paths, labels, help text, or executable definitions.

### Persistence

Option: `pgr_modules`

```php
array(
    'schema_version' => 1,
    'states' => array(
        'national_id' => true,
        'jalali_date' => true,
        'jalali_presentation' => false,
        'iranian_address' => true,
        'digit_normalization' => true,
        'iranian_currency' => true,
        'structured_scanner' => true,
    ),
)
```

The option is small and explicitly autoloaded when first persisted. Missing/malformed state falls back safely to source defaults. Unknown IDs and non-boolean values are ignored. The six pre-G-008 modules retain their prior default-enabled behavior. `jalali_presentation` defaults disabled so installing or upgrading PersianGravity cannot silently change system-date presentation. Existing schema-v1 state is merged over source defaults, so pre-G-008 stored states remain intact without a data migration or schema bump.

## Runtime gating

At `gform_loaded`, the GF runtime bootstrap loads `PGR_Module_Registry` first, then conditionally loads only enabled Gravity Forms runtime files. `PGR_Core::init()` independently honors the same states before registering fields/hooks.

### Ownership matrix

| Module | Runtime ownership |
|---|---|
| `national_id` | `pgr_national_id`, editor setting, duplicate normalization, typing-normalization asset |
| `jalali_date` | `pgr_jalali_date` and Jalali-domain helper runtime |
| `jalali_presentation` | source-owned Gregorian→Jalali converter, typed presentation facade, bounded Gravity Forms Entries List adapter plus exact Flow 3.1.0 Inbox/Status presentation adapters |
| `iranian_address` | `gform_address_types`, `gform_predefined_choices` |
| `digit_normalization` | `pgr_normalize_digits` form setting, `gform_save_field_value` |
| `iranian_currency` | `gform_currencies` IRR/IRT definitions |
| `structured_scanner` | `pgr_structured_scanner`, editor integration, Scanner frontend assets/runtime |

`PGR_Utils` loads when National ID or generic digit normalization needs it. Scanner Profile administration is intentionally independent of `structured_scanner` runtime state.

## G-008 Jalali system-date presentation

`jalali_presentation` is deliberately separate from `jalali_date`. `PGR_Persian_Date` and `pgr_jalali_date` continue to own true Jalali-domain user data and canonical Jalali storage. G-008 never activates from field presence and never interprets a `pgr_jalali_date` value as Gregorian.

The production path is intentionally narrow:

```text
known Gregorian/system source
        ↓
PGR_Gregorian_Jalali_Converter (pure calendar arithmetic)
        ↓
PGR_Jalali_Presentation (typed timezone + formatting facade)
        ↓
bounded presentation adapters
        ├── PGR_GF_Jalali_Presentation_Adapter → Gravity Forms Entries List date_created
        ├── PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter → exact Flow 3.1.0 Inbox date_created / last_updated / due_date
        └── PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter → exact Flow 3.1.0 Status date_created / workflow_timestamp
```

`PGR_Gregorian_Jalali_Converter` is a source-owned PHP adaptation of the Borkowski-lineage arithmetic represented by `jalaali-js` 2.0.1 commit `7ff10a0a4145c84a6911e87bfacf40ddf51a2adc`. MIT attribution ships in `includes/jalali-presentation/LICENSE.jalaali-js.txt`. Production adds no calendar library, Node, or `ext-intl` requirement.

The public capability is `PGR_Jalali_Presentation::format_datetime( DateTimeInterface, ?DateTimeZone )`. The input's timezone is part of the typed source semantics. The facade applies the target/site timezone before extracting Gregorian Y/M/D, converts only that local date, and preserves local time-of-day. `format_date()` is the bounded civil-date companion and performs no timezone shift.

The upstream reference documents a Gregorian `1800..2256` `Intl` cross-check range. PersianGravity records that separately as `REFERENCE_CROSSCHECK_RANGE`; it is not promoted to the V1 product range. Exhaustive current differential evidence against Unicode ICU 77.1 and the locked reference establishes `VALIDATED_PRODUCT_RANGE = 1800-01-01..2124-03-19` (118,417 dates, zero mismatches). The first current ICU/reference divergence is `2124-03-20`, so that date and everything outside the validated product range fall back to native presentation rather than emitting a guessed Jalali value.

The V1 Gravity Forms adapter uses `gform_entries_field_value`, acts only on `date_created`, parses `entry['date_created']` under Gravity Forms' documented UTC contract, and never parses arbitrary display strings. It has no save/update/query/global-date hooks. Raw Entry values, database storage, REST/API values, sorting/filtering keys, and chronological comparisons remain Gregorian/native.

The exact Gravity Flow 3.1.0 Inbox adapter uses only `gravityflow_inbox_field_value` and recognizes the separate raw/display identities for `date_created`, `last_updated`, and `due_date`. Exact source qualification proves each row emits raw `due_date` before `due_date_human_readable` through that same filter. The adapter therefore captures the already-computed native integer epoch/0 by form+entry identity, returns raw unchanged, consumes that request-local value only for Jalali display, and never calls `get_due_date_timestamp()` from presentation processing or parses `due_date_human_readable`. Gravity Flow's deadline calculation/filter invocation count, raw `due_date` AG Grid compare value, raw-0/display-`-` no-due sentinel, `is_overdue()` comparison, highlight state, workflow/assignment state, query/sort/filter behavior and date/date-field/delay timing remain host-owned. Missing/malformed/out-of-order capture or exact-version/source drift fails closed to native display.

See `docs/G008_JALALI_PRESENTATION.md` for converter provenance and `docs/G008_SYSTEM_DATE_EXPANSION.md` for the current exact Flow surface/source/operational evidence boundary.

## Safe disable

Usage inspection runs only on an explicit disable POST and reads Gravity Forms form configuration through `GFAPI::get_forms( null, false )`. It does not scan Entries or use direct SQL.

States:

- `USED` — disable is blocked.
- `UNUSED` — disable is allowed.
- `UNKNOWN` — a second explicit confirmation is required.

Detection:

- custom field modules: field type match.
- `jalali_presentation`: always `UNUSED` because it owns no persisted form configuration or migrated data.
- digit normalization: form-level `pgr_normalize_digits`.
- Iranian address: Address field with `addressType = iran`.
- Iranian currency: intentionally `UNKNOWN`; safe site-wide non-use is not inferred from incomplete form metadata.
- Gravity Forms/API unavailable or enumeration failure: `UNKNOWN`.

Enable never performs a usage scan.

## Admin architecture

`PGR_Product_Admin` owns the 4.2 product shell and reuses `PGR_Admin` for the existing Scanner Profiles implementation and Settings registration. The menu is:

1. Overview
2. Scanner Profiles
3. Settings
4. System Status
5. Help & Documentation

Overview renders bilingual module metadata, text state, nonce-protected POST controls, Help links, and second-confirmation warnings. Persian is primary only when `get_user_locale()` starts with `fa`; otherwise English is primary. Technical IDs are LTR.

All module writes require `manage_options`, POST, nonce, a source allowlist, sanitization, and `wp_safe_redirect`.

## Help and i18n

`PGR_Help_Catalog` is static code-owned version-controlled content. It contains Persian and English topics and is not stored in options or fetched remotely. Help language is restricted to `fa` or `en`. `WP_Screen::add_help_tab()` supplies concise contextual help links for PersianGravity admin screens.

Ordinary UI remains WordPress gettext with text domain `persian-gravityforms`. Explicit FA/EN module metadata exists only for always-visible product capability presentation and the bilingual Help Center.

## Existing data contracts

- `pgr_settings` — current plugin settings, including `default_force_english`.
- `pgr_scanner_profiles` — existing Custom Scanner Profiles.
- `pgr_modules` — module state only.
- field types, `pgr_normalize_digits`, Scanner Profile selection, and mappings — Gravity Forms form metadata.

Disable does not delete Entries, field definitions, settings, profiles, mappings, or form metadata. G-008 adds no database migration, custom table, or alternate date storage.

## Scanner invariants

Structured Scanner remains `displayOnly` and transient. Its raw capture is not intentionally persisted as a Scanner value. Only ordinary `text` and `hidden` destinations are supported. Mapping is validated as an atomic fail-closed update plan.

Built-in `sayad_v01` remains a structural `segments_v1` profile with exactly:

`qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id`.

It adds no Sayad checksum authority, bank validity, cross-bank guarantee, payment behavior, or online inquiry.

## Localization provider

Localization is cross-cutting infrastructure, outside `PGR_Module_Registry` semantics and never gated by `pgr_modules`. Registration follows plugin constants and precedes all late lifecycle registration. The seven-module manager and bilingual admin/help remain independent from localization provider state.

Decision C: **Shared Core + Declarative Product Manifests + Bounded Adapters**.
`PGR_Localization` is the single shared core. `includes/localization/products.php`
contains data for `gravityforms`, `gravityflow` and `gk-gravityview` only. No standard
product classes or service container are introduced. GravityView's owner-supplied
`gravityview` prefix is data; no executable exception is claimed without exact
package evidence.

Registration is synchronous in the main plugin file, before any late lifecycle
hook and without gettext, foreign loads or optional vendor classes. Optional
products may all be absent. Requests before PersianGravity itself loads cannot be
intercepted; already-loaded state is preserved.

`lang_dir_for_domain` supplies a provider directory only if Core has no upstream
path and a managed `fa_IR` catalog exists. During actual PHP requests,
`load_translation_file` makes a guarded native `load_textdomain()` call to load
provider entries first, then retains Core's upstream file attempt. Core provides
MO/PHP parsing and lookup; upstream-only keys remain available. No vendor paths
are overwritten and no PO is read at runtime.

JS uses content composition through `load_script_translations`; provider-only
fallback is supplied through `pre_load_script_translations` only at the final
`file=false` request. Partial catalogs retain upstream keys, context/plural arrays
and compatible metadata. It applies only to approved handles/domains and `fa_IR`.
PHP-localized script data relies on PHP translations.

PO, compilation, census, review provenance and artifact drift belong to development/
CI. The runtime performs only discovery, request-driven file resolution and overlay.
PersianGravity's own UI remains on `persian-gravityforms`. Provider ownership means
a local generic overlay, not vendor file ownership or official upstream status.
Provider entries win; missing entries fall back to vendor/TranslationsPress.
No updater disabling, remote download, runtime compilation, translation DB/editor,
plugin scanning or project-specific terminology is permitted.

**Current source/content boundary:** exact owner-supplied Gravity Forms 3.1.1.1, Gravity
Flow 3.1.0 and GravityView 3.3.4 packages are inspected and pinned as source evidence.
GravityView is `PACKAGE_INSPECTED_METADATA_ONLY` from package SHA-256
`af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`, with
`project_source_authority=OWNER_SUPPLIED_EXACT_PACKAGE` and `vendor_authenticity=NOT_PROVEN`.
Its exact package contains no vendor POT, so `vendor_pot_sha256=null` and no POT hash is
invented. The primary `gk-gravityview` source census is 3127 canonical identities, 127
contextual identities, 42 plural identities and 3931 source references. `gk-query-filters`
remains a bounded separate Composer dependency/domain and is not manifested or runtime-
activated by PersianGravity. GravityView contributes six source-backed surfaces; the full
registry is 19 surfaces total: 6 Gravity Forms + 7 Gravity Flow + 6 GravityView.

GravityView production content authority is revision 2 with all six accepted Surface Registry records independently validated: frontend shortcode 2, Gutenberg View block 1, Admin Builder 340, Foundation Settings 41, Entry Approval 30, and Search Widget 53. The records contain 467 surface occurrences with identical overlaps deduplicated to a deterministic 461-identity aggregate from the separate 3127-message source census; 2666 source identities remain unclassified/non-admitted. The reviewed WU-003 translation authority is bounded by these source-backed records, while the historical two-identity WU-002 shortcode record and translations remain unchanged. `gk-query-filters` remains outside the manifested/runtime-activated provider boundary. GravityView script handles remain zero and no GravityView translation JSON is generated. Non-admitted identities keep the existing upstream/vendor/TranslationsPress fallback and then source English. RTL/BiDi evidence remains source-only and real licensed GravityView browser/UI validation is `NOT_RUN`. This is bounded `CONTENT_ADMITTED_PARTIAL`, not full-product localization.

Gravity Forms production content authority is revision 3 `CONTENT_ADMITTED_FULL`. Its six historical accepted Surface Registry records remain unchanged and deduplicate to 1759 identities; one exact reviewed `PRODUCT_REMAINDER` adds the disjoint 2448 residual source-backed identities. The complete locked Gravity Forms 3.1.1.1 authority is therefore 4207/4207 accepted Persian identities. The known vendor-POT-only stale identity remains excluded. Gravity Forms script handles remain zero and no Gravity Forms translation JSON is generated.

Gravity Flow production content authority is revision 3 `CONTENT_ADMITTED_FULL`. Its seven historical accepted Surface Registry records remain unchanged: shortcode 15, Inbox 255, Status 43, Reports 20, Form Settings / Admin Builder 391, Settings & Integrations 44, and Entry Detail Sidebar 0. Their 768 surface occurrences still deduplicate to 732 identities; one exact reviewed `PRODUCT_REMAINDER` adds the disjoint 366 residual identities. The complete locked Gravity Flow 3.1.0 authority is therefore 1098/1098 accepted Persian identities. Inbox and Status locked fingerprints remain unchanged, and the zero-identity Entry Detail Sidebar record remains explicit and auditable. Gravity Flow script handles remain zero and no Gravity Flow translation JSON is generated.

Across the exact locked-source censuses, accepted Persian coverage is 5766/8432: Gravity Forms 4207/4207, Gravity Flow 1098/1098, and GravityView 461/3127. Exactly 2666 GravityView identities remain mandatory, so G-006 remains active. Only GravityView's non-admitted identities continue through upstream/vendor/TranslationsPress fallback and then source English. Provider authority remains exact-version bounded; `action-scheduler` is outside provider authority, vendor/upstream plugins remain unmodified, and no native product JS handles/provider JSON are activated. Full accepted content authority for Gravity Forms and Gravity Flow does not establish ecosystem-wide, future-version, all-browser-exercised, or native-product-JS localization coverage. See `docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md`, `docs/VALIDATION_CONTENT_ADMISSION_V2.md`, `docs/WU004_GRAVITYVIEW_SOURCE_ADMISSION.md`, and `docs/WU006_GRAVITYVIEW_FRONTEND_CONTENT_ADMISSION.md` for the exact source/content contracts.

## Scope

PersianGravity does not own Gravity Flow workflow, GravityView business behavior, SRWF business logic, fonts, payment gateways, arbitrary external plugin translations, OCR/camera scanning, online Sayad inquiry, or custom databases. G-008 owns only explicitly admitted presentation seams. Exact Flow 3.1.0 Inbox `date_created`/`last_updated`/`due_date` and Status `date_created`/`workflow_timestamp` display are admitted without owning Flow workflow/query/export/deadline semantics; Status `due_date`, Entry Detail due/schedule/expiration, Timeline/history, Print, global WordPress dates, ordinary GF Date fields, GravityView dates, and future Flow versions remain outside the admitted boundary.

## G-007 bounded Gravity Perks family extension

G-007 keeps `PGR_Localization` as the single shared runtime core. `includes/localization/products.php` continues to hold the prior Gravity Forms / Gravity Flow / GravityView manifest data; `includes/localization/registry.php` now merges that unchanged set with the bounded G-007 data in `includes/localization/g007-products.php`. The only added domains are `gravityperks`, `gp-file-upload-pro`, and `gp-advanced-select`, bound to exact package versions 2.3.16, 1.5.13, and 1.1.21 and admitted primary-domain censuses 83, 39, and 5. Observed cross-domain/default-domain calls are evidence only and do not activate unrelated domains.

GP File Upload Pro remains data-only at runtime: its exact source passes the relevant gettext values into the uploader through `wp_localize_script()`, so the shared PHP provider supplies the Persian labels without a new JavaScript translation subsystem. GP Advanced Select is the only G-007 product with executable compatibility behavior. `PGR_Gravity_Perks_RTL` attaches inline CSS only to the source-proven `gp-advanced-select-tom-select` style handle, only under `fa_IR` + RTL, and mirrors Tom Select's actual `.ts-wrapper.rtl` state and caret spacing. Vendor package bytes, vendor CSS, and updater behavior remain untouched.
