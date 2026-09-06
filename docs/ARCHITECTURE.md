# Persian Gravity Forms v4 Architecture

## Purpose

Persian Gravity Forms is a focused Gravity Forms extension for generic Persian/Iranian capabilities. Version 4 deliberately uses one canonical runtime and keeps responsibility boundaries narrow.

Structured Scanner v0.1 is an unreleased source capability in the current development PR. It does not change the plugin version or create a new release authority.

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
                    ├── PGR_Scanner_Profile_Registry
                    ├── PGR_GF_Field_National_ID
                    ├── PGR_GF_Field_Jalali_Date
                    └── PGR_GF_Field_Structured_Scanner
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

It registers all three current custom field implementations and conditionally loads National ID and Structured Scanner assets only for forms that need them.

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

### `PGR_Scanner_Profile_Registry`

Owns Structured Scanner profile metadata only. It does not own browser capture state and does not provide a second PHP parser.

Current profile ID:

`sayad_v01`

The ordered structural outputs are exactly:

1. `qr_version`
2. `owner_type`
3. `owner_identifier`
4. `iban`
5. `bank_branch`
6. `cheque_serial`
7. `sayad_id`

Supported mapped destination field types are exactly:

- `text`
- `hidden`

The profile is structural only. It does not assert bank validity, cheque validity, checksum authority, cross-bank compatibility, payment behavior, or financial business rules.

### `PGR_GF_Field_Structured_Scanner`

Field type:

`pgr_structured_scanner`

The field is a non-persistent controller with `displayOnly = true`.

Field-owned persisted configuration properties:

- `scanner_profile`
- `scanner_mappings`

`scanner_mappings` is stored as the ordered Gravity Forms field configuration aligned with the selected profile outputs. At runtime it is converted to a keyed mapping object for the browser parser/update planner.

Raw Scanner payload is not a persisted field value:

- capture markup has no Gravity Forms `name="input_<id>"`;
- the capture control is a multiline `textarea` so LF/CRLF survives the browser value boundary;
- `get_value_save_entry()` returns an empty string;
- `get_value_entry_detail()` returns an empty string;
- `get_value_entry_list()` returns an empty string;
- `get_value_merge_tag()` returns an empty string;
- `get_value_export()` returns an empty string.

The PHP field exports only configuration metadata needed by the browser: selected profile, normalized mapping configuration, allowed target descriptors, safe status strings, and Scanner field ID.

## Structured Scanner browser contract

### Authority split

`assets/js/pgr-structured-scanner-core.js` is the pure authority for:

- `sayad_v01` structural parsing;
- LF/CRLF normalization;
- Persian/Arabic digit normalization;
- profile lookup;
- mapping validation;
- atomic update-plan construction;
- parser-driven capture-completion decisions.

`assets/js/pgr-structured-scanner.js` is a thin DOM adapter. It does not define a second seven-segment grammar.

### Capture boundary

The browser capture control is one transient multiline `textarea`.

There is no parallel hidden raw buffer, persistent queue, global keyboard interceptor, browser storage, network request, or worker acting as a second raw-payload authority.

The adapter copies the selected raw payload before clearing the transient control. Completed attempts clear the raw capture and restore focus to the Scanner capture where appropriate.

### Keyboard and idle behavior

The pure core exposes parser-driven completion evaluation. The adapter uses it as follows:

- Enter while the current payload is not parser-valid remains an in-progress separator event and is not forced through the invalid path;
- Enter when the current payload is parser-valid finalizes and prevents an extra newline;
- non-empty Tab is an explicit finalization action and can surface an incomplete/invalid payload;
- empty Tab remains ordinary focus traversal;
- idle processing runs only when the current payload is parser-valid; an incomplete pause leaves the partial multiline payload intact;
- paste is an explicit finalization path using the raw clipboard text so LF/CRLF reaches the parser without passing through a single-line control.

The parser still rejects delimiter-less collapsed payloads.

### Atomic mapped-field update contract

The pure core validates the full mapping and builds the full update plan before DOM mutation.

Fail-closed conditions include:

- malformed mapping metadata;
- unknown profile;
- missing target;
- unsupported target type;
- duplicate target;
- self-target;
- invalid scan structure.

Only after the complete plan is valid does the DOM adapter resolve every target and apply the mapped values. A failed later scan must not expose or apply a partial plan over the prior successful state. Repeated successful scans may replace all mapped values.

### Per-instance lifecycle

Each rendered Scanner root owns state through a `WeakMap` entry in the DOM adapter. Target lookup is scoped to the owning `<form>`.

Initialization runs on normal document readiness and the supported Gravity Forms browser lifecycle event:

`gform/post_render`

The `WeakMap` prevents duplicate initialization for the same rendered Scanner node.

Autofocus remains bounded to the owning form and does not use document-wide keyboard interception.

## `PGR_Persian_Date`

Owns the bounded Jalali parsing, canonicalization, validation, and display-format logic required by the dedicated Jalali field. It is not intended to become a general calendar framework.

## `PGR_Address`

Provides generic Iranian address/province behavior through Gravity Forms integration. No project-specific school, region, or workflow logic belongs here.

## `PGR_Currency`

Provides Iranian Rial (`IRR`) and Toman (`IRT`) definitions through Gravity Forms currency integration.

## `PGR_Admin`

Uses the WordPress Settings API.

Current plugin option:

`pgr_settings`

Current option key:

`default_force_english`

## Form-level persistence

Current form property:

`pgr_normalize_digits`

When enabled, string values pass through server-side Persian/Arabic digit normalization before Gravity Forms persists them.

Structured Scanner field configuration adds `scanner_profile` and `scanner_mappings` to its field object. Scanner raw payload is explicitly outside persistence authority.

## Asset policy

National ID frontend JavaScript:

`assets/js/pgr-frontend.js`

It is conditionally enqueued only when a National ID field opts into typing-time digit normalization.

Structured Scanner assets:

- `assets/js/pgr-structured-scanner-core.js`
- `assets/js/pgr-structured-scanner.js`
- `assets/css/pgr-structured-scanner.css`

They are conditionally enqueued only when a form contains `pgr_structured_scanner`.

JavaScript is not used as authority for National ID/Jalali server validation. Structured Scanner is different by design: it is a transient browser controller whose own raw payload is not submitted as a field value. Its structural parser and mapping planner are pure client-side code, while persisted destination fields remain ordinary Gravity Forms fields.

General site typography is not part of PersianGravity.

## Gravity Forms API boundary

Important current integration points include:

- `gform_loaded`
- `GF_Field`
- `GF_Fields::register()`
- `gform_field_advanced_settings`
- `gform_form_settings_fields`
- `gform_save_field_value`
- `gform_value_pre_duplicate_check`
- `gform_enqueue_scripts`
- browser event `gform/post_render`

Structured Scanner Form Editor configuration uses Gravity Forms field properties and the existing `SetFieldProperty()` editor mechanism. It does not depend on Gravity Flow, GravityView, Nested Forms internals, direct SQL, or private Gravity Forms DOM identifiers for mapped target discovery.

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
- bank/checksum/business validation for `sayad_v01`;
- a general barcode/scanner framework or global keyboard interception layer;
- historical field-ID compatibility (`mellicart`, `ir_national_id`);
- broad migration machinery for removed legacy installations;
- parallel plugin frameworks or service-container architecture.

## Testing boundary

Automated tests cover pure Scanner parsing/mapping/completion behavior, bootstrap contracts, non-persistence value surfaces, capture markup, conditional asset loading, runtime integrity, WPCS, PHPCompatibility, and repository PHPUnit behavior that can be exercised without a licensed real Gravity Forms environment.

A real WordPress + Gravity Forms integration environment is still required to prove browser/editor/frontend behavior for Structured Scanner. Unit/stub coverage and green CI are not equivalent to that proof.

See `docs/VALIDATION.md`.
