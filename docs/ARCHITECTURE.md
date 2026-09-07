# Persian Gravity Forms Architecture — 4.2.0

## Runtime topology

```text
persian-gravityforms.php
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
        'iranian_address' => true,
        'digit_normalization' => true,
        'iranian_currency' => true,
        'structured_scanner' => true,
    ),
)
```

The option is small and explicitly autoloaded when first persisted. Missing/malformed state falls back to source defaults. Unknown IDs and non-boolean values are ignored. All six current modules default enabled so upgrade from 4.1.0 does not remove capabilities.

## Runtime gating

The bootstrap loads `PGR_Module_Registry` first, then conditionally loads only enabled Gravity Forms runtime files. `PGR_Core::init()` independently honors the same states before registering fields/hooks.

### Ownership matrix

| Module | Runtime ownership |
|---|---|
| `national_id` | `pgr_national_id`, editor setting, duplicate normalization, typing-normalization asset |
| `jalali_date` | `pgr_jalali_date` and Jalali helper runtime |
| `iranian_address` | `gform_address_types`, `gform_predefined_choices` |
| `digit_normalization` | `pgr_normalize_digits` form setting, `gform_save_field_value` |
| `iranian_currency` | `gform_currencies` IRR/IRT definitions |
| `structured_scanner` | `pgr_structured_scanner`, editor integration, Scanner frontend assets/runtime |

`PGR_Utils` loads when National ID or generic digit normalization needs it. Scanner Profile administration is intentionally independent of `structured_scanner` runtime state.

## Safe disable

Usage inspection runs only on an explicit disable POST and reads Gravity Forms form configuration through `GFAPI::get_forms( null, false )`. It does not scan Entries or use direct SQL.

States:

- `USED` — disable is blocked.
- `UNUSED` — disable is allowed.
- `UNKNOWN` — a second explicit confirmation is required.

Detection:

- custom field modules: field type match.
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

Disable does not delete Entries, field definitions, settings, profiles, mappings, or form metadata.

## Scanner invariants

Structured Scanner remains `displayOnly` and transient. Its raw capture is not intentionally persisted as a Scanner value. Only ordinary `text` and `hidden` destinations are supported. Mapping is validated as an atomic fail-closed update plan.

Built-in `sayad_v01` remains a structural `segments_v1` profile with exactly:

`qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id`.

It adds no Sayad checksum authority, bank validity, cross-bank guarantee, payment behavior, or online inquiry.

## Scope

PersianGravity does not own Gravity Flow workflow, GravityView, SRWF business logic, fonts, payment gateways, external plugin translations, OCR/camera scanning, online Sayad inquiry, or custom databases.
