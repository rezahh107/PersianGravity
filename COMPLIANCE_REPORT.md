# Persian Gravity Forms v4 — Runtime Compliance & Validation Snapshot

Status date: `2026-08-31`

This file replaces the obsolete pre-v4 compliance report. It describes the current `4.0.0` architecture and validation state only.

## Executive status

The repository has been consolidated to one canonical `PGR_*` runtime. The previous parallel `src/PersianGravityForms/*` architecture, `GFPersian_*` legacy runtime, historical National ID compatibility layers, bundled font systems, payment/RSS baggage, and external-plugin translation interception are no longer part of the active product.

Repository CI on the v4 refactor has passed its configured quality and PHPUnit jobs. This does **not** mean every browser/UI path has been integration-tested against a licensed real Gravity Forms installation.

## Current repository contract

| Area | Current status | Evidence/contract |
| --- | --- | --- |
| Entrypoint | PASS | `persian-gravityforms.php` |
| Plugin version | PASS | `4.0.0` |
| Text domain | PASS | `persian-gravityforms` |
| WordPress minimum | PASS | `6.7` |
| PHP minimum | PASS | `8.2` |
| Gravity Forms minimum | PASS | `3.0` |
| Canonical runtime | PASS | one `PGR_*` runtime |
| Parallel `src/` runtime | PASS | absent |
| Historical `GFPersian_*` runtime | PASS | absent |
| National ID implementation | PASS | one `GF_Field`: `pgr_national_id` |
| Jalali implementation | PASS at code/unit level | one `GF_Field`: `pgr_jalali_date` |
| Typography ownership | PASS | general font assets removed |
| External translation interception | PASS | not part of current runtime |
| SRWF business logic | PASS | out of scope / absent |

## Current Gravity Forms integration

The v4 runtime uses supported public integration surfaces including:

- `gform_loaded`
- `GF_Field`
- `GF_Fields::register()`
- `gform_form_settings_fields`
- `gform_save_field_value`
- `gform_value_pre_duplicate_check`
- `gform_enqueue_scripts`

Removed pre-v4 architecture must not be reintroduced merely for historical compatibility.

## Field status

### Iranian National ID

Current contract:

- custom field type `pgr_national_id`;
- server-side Persian/Arabic digit normalization;
- server-side Iranian National ID checksum validation;
- canonical ten-digit ASCII persistence;
- Gravity Forms native duplicate-check integration;
- optional typing-time normalization as UX only.

### Jalali Date

Current contract:

- custom field type `pgr_jalali_date`;
- server-side Jalali parsing/validation;
- canonical ASCII `YYYY-MM-DD` persistence with Jalali semantics;
- multiple field-owned presentation formats;
- no implicit Gregorian conversion;
- no override of the ordinary Gravity Forms Date field.

## CI status

The current workflow is designed to run:

### Quality job

- Composer dependency installation
- syntax check against shipped PHP
- WordPress Coding Standards
- PHPCompatibility baseline
- runtime-integrity guard

### Unit jobs

PHP matrix:

- `8.2`
- `8.3`
- `8.4`
- `8.5`

The refactor branch and post-merge `main` workflow were observed completing successfully on `2026-08-30`.

## Evidence boundary

The following distinctions are mandatory:

- CI/unit success proves the configured automated checks passed.
- Stubbed Gravity Forms tests do not prove full licensed Gravity Forms integration.
- Seeing a field in the Gravity Forms editor proves field registration, not all frontend behavior.
- Browser rendering, entry save/load, AJAX behavior, and real editor interactions require real integration evidence when changed.

## Remaining validation focus

At the current v4 baseline, the main non-CI validation concern is real WordPress + Gravity Forms integration, especially when modifying:

- Jalali field frontend behavior;
- entry display/edit behavior;
- format persistence and round-trips;
- National ID duplicate behavior;
- AJAX/multi-page forms;
- Gravity Forms editor-specific behavior.

These are not evidence of defects. They are validation surfaces that CI alone cannot close.

## Governance

The active architecture and contribution rules are defined by:

- `README.md`
- `AGENTS.md`
- `docs/ARCHITECTURE.md`
- `docs/VALIDATION.md`

Historical plans and pre-v4 compliance percentages must not be treated as current repository truth.
