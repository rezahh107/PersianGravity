# AGENTS.md — Persian Gravity Forms v4

This document defines current repository rules for human contributors and coding agents.

## 1. Canonical repository identity

- Repository: `rezahh107/PersianGravity`
- Plugin name: `Persian Gravity Forms`
- Version: `4.2.0`
- Entrypoint: `persian-gravityforms.php`
- Runtime prefix: `PGR_`
- Text domain: `persian-gravityforms`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`

There is exactly one canonical runtime. Do not recreate removed `src/PersianGravityForms/*` or `GFPersian_*` architectures.

## 2. Product scope

Current bounded modules are exactly `national_id`, `jalali_date`, `iranian_address`, `digit_normalization`, `iranian_currency`, and `structured_scanner`. `PGR_Module_Registry` owns their source metadata and defaults; it is not a third-party extension framework.

Out of scope: fonts, Gravity Flow/GravityView translation ownership, payment gateways, workflow/SRWF rules, custom databases, bank/checksum/cross-bank authority, OCR/camera scanning, remote module registries or marketplaces.

## 3. Runtime architecture

```text
persian-gravityforms.php
        |
        +-- non-disableable admin infrastructure
        |     +-- PGR_Module_Registry
        |     +-- PGR_Product_Admin / PGR_Admin
        |     +-- Scanner Profiles
        |     +-- Help Catalog
        |     +-- Settings / System Status
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

Disabled modules must not register owned fields/hooks/assets merely to return early later. Shared utility loading may use explicit small conditions; do not introduce a service container or generic dependency resolver.

## 4. Module persistence and disable safety

`pgr_modules` is a small autoloaded option with schema version 1 and booleans only. Missing/malformed state falls back safely to source defaults; all current modules default enabled. Do not persist labels, callbacks, class names, paths or Help content.

Disable checks run only on the explicit admin disable request. Use supported Gravity Forms APIs and form metadata only; never direct SQL or Entry scans. `USED` blocks. `UNKNOWN` requires explicit second confirmation. Gravity Forms unavailable is `UNKNOWN`. Enable does not require a usage scan.

Disabling must never delete Entries, form definitions, `pgr_settings`, `pgr_scanner_profiles`, mappings or form settings.

## 5. Capability contracts

- National ID: `pgr_national_id`; server Mod-11 validation, Persian/Arabic digit normalization, canonical 10 ASCII digits, native No Duplicates normalization. Typing normalization is UX only.
- Jalali: `pgr_jalali_date`; dedicated field, server Jalali validation, canonical ASCII `YYYY-MM-DD` with Jalali semantics. Never replace native GF Date fields.
- Address: address type id `iran` and Iranian province choices.
- Digit normalization: form-level `pgr_normalize_digits` plus authoritative server `gform_save_field_value` normalization; semantically separate from National ID typing UX.
- Currency: IRR/IRT definitions, zero decimal places in current source; no payment behavior.
- Structured Scanner: `pgr_structured_scanner`, transient/displayOnly, raw value not persisted, text/hidden destinations only, parser-driven completion, atomic fail-closed mapping. Built-in `sayad_v01` outputs exactly `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id` and is structural only.

Scanner Profiles administration is non-disableable and remains available when Scanner runtime is disabled.

## 6. Admin, Help and i18n

Persian Gravity admin pages are Overview, Scanner Profiles, Settings, System Status and Help & Documentation. Admin CSS/JS must remain scoped to these screens.

Capability presentation explicitly contains FA/EN metadata together. Persian is primary only when WordPress user locale is Persian; otherwise English is primary. Technical identifiers are LTR.

Ordinary UI uses WordPress gettext with text domain `persian-gravityforms`. Do not intercept external plugin domains. The full bilingual Help Catalog is static, local, source-owned and version-controlled. Use native `WP_Screen` contextual help for concise page help and links into the full Help Center.

## 7. Gravity Forms API policy

Prefer documented public APIs/hooks, including `gform_loaded`, `GF_Field`, `GF_Fields::register()`, `GFAPI::get_forms()`, `gform_field_advanced_settings`, `gform_form_settings_fields`, `gform_save_field_value`, `gform_value_pre_duplicate_check`, `gform_enqueue_scripts`, `gform_address_types`, `gform_predefined_choices`, `gform_currencies`, and browser `gform/post_render` where already required.

Do not reintroduce deprecated form-settings paths or private/direct-SQL usage.

## 8. Testing and CI

Required validation categories:

```bash
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
```

CI continues PHP syntax, WPCS, PHPCompatibility, Scanner JS, runtime-integrity guards and PHPUnit PHP 8.2–8.5. Repository consistency tests must keep active version declarations, bilingual module metadata, Help coverage and translation assets aligned.

Unit/stub/source tests are not equivalent to a real WordPress + licensed Gravity Forms browser/integration test. Record executed vs unexecuted evidence in `docs/VALIDATION.md`.

## 9. Runtime-integrity and release safety

Do not reintroduce `src/`, `GFPersian_*`, `mellicart`, `ir_national_id`, external `load_textdomain_mofile` interception, bundled fonts, old payment/RSS/transaction subsystems or SRWF identifiers.

Work on focused branches. Do not force-push shared history, merge to `main`, publish tags/releases, or claim browser validation without actual execution. Material runtime changes update README, readme.txt, ARCHITECTURE, VALIDATION and—when governance changes—AGENTS in the same PR.
