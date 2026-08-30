# Persian Gravity Forms v4 Architecture

## Purpose

Persian Gravity Forms is a focused Gravity Forms extension for generic Persian/Iranian capabilities. Version 4 deliberately uses one canonical runtime and keeps responsibility boundaries narrow.

## Runtime topology

```text
persian-gravityforms.php
        │
        ├── own text-domain loading
        ├── dependency notice
        │
        └── gform_loaded
              │
              └── PGR_Core::init()
                    ├── PGR_Admin
                    ├── PGR_Utils
                    ├── PGR_Address
                    ├── PGR_Currency
                    ├── PGR_Persian_Date
                    ├── PGR_GF_Field_National_ID
                    └── PGR_GF_Field_Jalali_Date
```

There is no parallel runtime under `src/` and no historical `GFPersian_*` runtime.

## Bootstrap contract

`persian-gravityforms.php` is the only plugin entrypoint.

Important constants:

- `PGR_VERSION`
- `PGR_FILE`
- `PGR_PATH`
- `PGR_URL`
- `PGR_MIN_GF_VERSION`

Gravity Forms-dependent runtime initialization is attached to `gform_loaded`. Initialization is idempotent and exits when required Gravity Forms classes or the minimum Gravity Forms version are unavailable.

## Product modules

### `PGR_Core`

Coordinates field registration, admin/settings, address/currency integrations, form-level digit normalization, duplicate normalization, and conditional field assets.

### `PGR_Utils`

Owns canonical digit normalization and Iranian National ID normalization/checksum logic. Server-side behavior is authoritative.

### `PGR_GF_Field_National_ID`

Field type:

`pgr_national_id`

Contract:

- scalar National ID input;
- 10-digit Iranian checksum validation;
- Persian/Arabic to ASCII normalization;
- canonical ten-digit ASCII storage;
- conditional logic support;
- Gravity Forms native No Duplicates integration;
- optional client-side typing normalization.

### `PGR_GF_Field_Jalali_Date`

Field type:

`pgr_jalali_date`

Contract:

- dedicated Jalali field, not a modified native Gravity Forms Date field;
- field-owned presentation format;
- server-side Jalali validation;
- canonical ASCII `YYYY-MM-DD` storage;
- stored date remains Jalali and is never implicitly converted to Gregorian;
- conditional logic and Gravity Forms display/merge-tag integration.

### `PGR_Persian_Date`

Owns the bounded Jalali parsing, canonicalization, validation, and display-format logic required by the dedicated Jalali field. It is not intended to become a general calendar framework.

### `PGR_Address`

Provides generic Iranian address/province behavior through Gravity Forms integration. No project-specific school, region, or workflow logic belongs here.

### `PGR_Currency`

Provides Iranian Rial (`IRR`) and Toman (`IRT`) definitions through Gravity Forms currency integration.

### `PGR_Admin`

Uses the WordPress Settings API.

Current plugin option:

`pgr_settings`

Current option key:

`default_force_english`

## Form-level persistence

Current form property:

`pgr_normalize_digits`

When enabled, string values pass through server-side Persian/Arabic digit normalization before Gravity Forms persists them.

## Asset policy

Current frontend JavaScript:

`assets/js/pgr-frontend.js`

It is conditionally enqueued only when a National ID field opts into typing-time digit normalization.

JavaScript is never authoritative for validation or storage.

General site typography is not part of PersianGravity.

## Localization boundary

Canonical text domain:

`persian-gravityforms`

The plugin may translate only its own strings.

It must not intercept or own translations for:

- Gravity Forms
- Gravity Flow
- GravityView
- third-party add-ons

## Explicit non-goals

Do not add these without a new product decision:

- general font delivery;
- payment gateways;
- workflow/business rules;
- SRWF-specific behavior;
- custom databases;
- historical field-ID compatibility (`mellicart`, `ir_national_id`);
- broad migration machinery for removed legacy installations;
- parallel plugin frameworks or service-container architecture.

## Testing boundary

Automated tests should cover pure logic, bootstrap contracts, runtime integrity, and public integration contracts that can be exercised without a licensed real Gravity Forms environment.

A real WordPress + Gravity Forms integration environment is still required to prove browser/editor/frontend behavior when those surfaces change.

See `docs/VALIDATION.md`.
