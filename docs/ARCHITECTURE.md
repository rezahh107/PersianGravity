# Persian Gravity Forms Architecture — 4.2.0

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

At `gform_loaded`, the GF runtime bootstrap loads `PGR_Module_Registry` first, then conditionally loads only enabled Gravity Forms runtime files. `PGR_Core::init()` independently honors the same states before registering fields/hooks.

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

## Localization provider

Localization is cross-cutting infrastructure, never a seventh `PGR_Module_Registry` module and never gated by `pgr_modules`. Registration follows plugin constants and precedes all late lifecycle registration. The six-module manager and bilingual admin/help remain the 4.2.0 authority.

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

**Current source/content boundary:** exact owner-supplied Gravity Forms 3.1.1.1 and Gravity
Flow 3.1.0 packages/POTs are inspected and pinned as source evidence; GravityView 3.3.4
remains `PACKAGE_UNAVAILABLE`. Gravity Forms has no production content-admission record
in this boundary. Gravity Flow production content authority is revision 2 with exactly
two independently validated records: Inbox
(`gravityflow::workflow_runtime::admin_page:gravityflow-inbox`, 255 identities) and
Status (`gravityflow::workflow_runtime::admin_page:gravityflow-status`, 43 identities,
source path exactly `includes/pages/class-status.php`). The two records share 10 canonical
identities with identical reviewed translations and therefore deterministically union to
288 unique admitted identities. The Gravity Flow source census remains separately 1098.
Inbox locked keyset/path/content fingerprints remain unchanged. The aggregate sparse PO is
the exact set union; MO and `.l10n.php` are deterministic generated outputs. Gravity Flow
script handles remain zero and no Gravity Flow translation JSON is generated. Non-admitted
keys still fall through to upstream/vendor/TranslationsPress and then source English.
This is partial surface authority only, not full Gravity Flow localization or licensed
browser proof. See `docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md` and
`docs/VALIDATION_CONTENT_ADMISSION_V2.md` for the exact content contract.

## Scope

PersianGravity does not own Gravity Flow workflow, GravityView business behavior, SRWF business logic, fonts, payment gateways, arbitrary external plugin translations, OCR/camera scanning, online Sayad inquiry, or custom databases.
