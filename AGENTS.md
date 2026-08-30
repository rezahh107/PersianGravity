# AGENTS.md — Persian Gravity Forms v4

This document defines current repository rules for human contributors and coding agents.

## 1. Canonical repository identity

- Repository: `rezahh107/PersianGravity`
- Plugin name: `Persian Gravity Forms`
- Version: `4.0.0`
- Entrypoint: `persian-gravityforms.php`
- Runtime prefix: `PGR_`
- Text domain: `persian-gravityforms`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`

There is exactly **one canonical runtime**. Do not recreate the removed `src/PersianGravityForms/*` or `GFPersian_*` architectures.

## 2. Product scope

PersianGravity owns only generic Persian/Iranian Gravity Forms capabilities.

Current product scope:

- Iranian National ID field (`pgr_national_id`)
- Jalali Date field (`pgr_jalali_date`)
- Persian/Arabic digit normalization
- Iranian address/province support
- IRR/IRT currency definitions
- localization of PersianGravity's own strings

Out of scope:

- Vazir/Vazirmatn or other font delivery
- Gravity Flow or GravityView translation ownership
- payment gateways
- workflow/business rules
- SRWF-specific behavior
- custom databases
- historical field IDs/migrations kept only for legacy compatibility

Do not reintroduce removed capabilities without an explicit product decision and evidence that they belong to this plugin.

## 3. Runtime architecture

```text
persian-gravityforms.php
        │
        └── gform_loaded
              │
              └── PGR_Core
                    ├── PGR_Admin
                    ├── PGR_Utils
                    ├── PGR_Address
                    ├── PGR_Currency
                    ├── PGR_Persian_Date
                    ├── PGR_GF_Field_National_ID
                    └── PGR_GF_Field_Jalali_Date
```

Detailed contract: `docs/ARCHITECTURE.md`.

### Required invariants

1. Gravity Forms-dependent code initializes through the supported Gravity Forms lifecycle.
2. Plugin activation must not fatal when Gravity Forms is absent.
3. Each custom field has one canonical implementation.
4. Server-side validation/normalization is authoritative.
5. Ordinary Gravity Forms Date fields are not converted into Jalali fields.
6. External plugin text domains are never globally intercepted.
7. Tests target the same runtime WordPress executes.

## 4. Gravity Forms API policy

Use current documented Gravity Forms APIs.

Current important integration points include:

- `gform_loaded`
- `GF_Field`
- `GF_Fields::register()`
- `gform_form_settings_fields`
- `gform_save_field_value`
- `gform_value_pre_duplicate_check`
- `gform_enqueue_scripts`

Do not reintroduce removed/deprecated hooks such as the old `gform_form_settings` path.

Before adding a new Gravity Forms hook, document:

- why it is required;
- the official API contract;
- what test covers it.

## 5. Field contracts

### Iranian National ID

- Field type: `pgr_national_id`
- Validate on the server.
- Normalize Persian/Arabic digits to ASCII.
- Persist exactly ten ASCII digits when valid.
- Use Gravity Forms' native No Duplicates mechanism rather than direct database duplicate queries.
- Client-side digit normalization is UX only and must never be authoritative.

### Jalali Date

- Field type: `pgr_jalali_date`
- Validate Jalali calendar rules server-side.
- Persist canonical ASCII `YYYY-MM-DD` with Jalali semantics.
- Do not silently convert stored values to Gregorian.
- Do not override or deregister Gravity Forms/WordPress native datepicker handles to implement this field.

## 6. Settings and persistence

Current plugin option:

`pgr_settings`

Current form-level setting:

`pgr_normalize_digits`

Use WordPress Settings API for plugin settings. Do not add a custom database table.

Any new persisted identifier must be documented in `docs/ARCHITECTURE.md` before release.

## 7. Coding standards

- Follow WordPress Coding Standards.
- PHP baseline is 8.2; do not use syntax newer than the minimum supported PHP unless the baseline is intentionally changed.
- Guard directly accessible PHP files with `defined( 'ABSPATH' ) || exit;` where applicable.
- Sanitize input and escape output at the correct boundary.
- Prefer WordPress/Gravity Forms public APIs over direct SQL or internal implementation details.
- Do not add abstractions that solve no concrete failure.

## 8. Asset rules

- Load frontend assets only when the relevant field/feature needs them.
- Server-side behavior must not depend on JavaScript.
- Do not bundle general typography assets.
- Do not add large third-party libraries without a concrete, reviewed requirement.

## 9. Internationalization

Only this text domain belongs to PersianGravity:

`persian-gravityforms`

Generate/update the POT with:

```bash
composer i18n:pot
```

Translations for Gravity Forms, Gravity Flow, GravityView, or third-party add-ons must use their own official localization mechanisms.

## 10. Testing and CI

Required local commands:

```bash
composer install
composer test
composer cs
composer compat
```

CI must continue to cover:

- shipped PHP syntax;
- WPCS/PHPCS;
- PHPCompatibility baseline;
- runtime-integrity guards;
- PHPUnit on PHP 8.2, 8.3, 8.4, and 8.5 unless the support policy changes.

Unit/stub tests are not equivalent to a licensed WordPress + Gravity Forms integration test.

When changing field rendering, editor behavior, or Gravity Forms lifecycle integration, add a real integration/manual verification step and record it in `docs/VALIDATION.md`.

## 11. Runtime-integrity guard

The repository must not regress to historical architecture.

Do not reintroduce without explicit owner approval:

- `src/` parallel runtime
- `GFPersian_*`
- `mellicart`
- `ir_national_id`
- external `load_textdomain_mofile` interception
- bundled Vazir/Shabnam/Yekan font systems
- old payment/RSS/transaction-ID subsystems
- SRWF identifiers or business logic

## 12. Documentation rules

Material runtime changes must update the relevant documentation in the same PR:

- `README.md` — GitHub/user/developer overview
- `readme.txt` — WordPress distribution metadata and changelog
- `docs/ARCHITECTURE.md` — runtime/data/API contracts
- `docs/VALIDATION.md` — what has actually been tested versus what remains unproven
- `AGENTS.md` — only when repository governance or boundaries change

Do not leave historical plans or compliance reports in the active root if they describe an architecture that no longer exists.

## 13. Release checklist

Before release:

1. Align plugin header, `PGR_VERSION`, `readme.txt` Stable tag, Composer PHP requirement, and changelog.
2. Run CI successfully.
3. Confirm production package excludes development-only files according to `.distignore`.
4. Generate/update plugin-owned translation assets if needed.
5. Re-run real Gravity Forms integration checks for any UI/field-lifecycle changes.
6. Do not publish or merge release changes without owner authorization.

## 14. Git and PR safety

- Work on focused branches.
- Keep commits reviewable.
- Do not force-push shared history.
- Do not merge to `main` without explicit owner approval.
- Do not publish releases unless explicitly requested.

The repository source and executed validation are authoritative. Historical documents, old reports, and generated summaries are not runtime truth.
