# Persian Gravity Forms v4 Architecture

## Purpose

Persian Gravity Forms is a focused Gravity Forms extension for generic Persian/Iranian capabilities. Version 4 deliberately uses one canonical runtime and keeps responsibility boundaries narrow.

Structured Scanner v0.1 is an unreleased source capability in the current development PR. It does not create release authority.

## Runtime topology

```text
persian-gravityforms.php
        │
        ├── PGR_Localization (immediate filter registration; no foreign loading)
        ├── own text-domain loading
        ├── dependency notice
        │
        ├── plugins_loaded
        │     └── PGR_Admin
        │
        └── gform_loaded
              │
              └── PGR_Core::init()
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

The WordPress admin product surface initializes independently so Settings and System Status can remain available when Gravity Forms is absent. Gravity Forms-dependent runtime initialization is attached to `gform_loaded`. Initialization is idempotent and exits when required Gravity Forms classes or the minimum Gravity Forms version are unavailable.

## Product modules

### `PGR_Core`

Coordinates field registration, address/currency integrations, form-level digit normalization, duplicate normalization, and conditional field assets.

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

Owns Structured Scanner profile definitions and custom-profile persistence. It does not own browser capture state and does not provide a second PHP parser.

Built-in profile ID:

`sayad_v01`

The ordered built-in outputs remain exactly:

1. `qr_version`
2. `owner_type`
3. `owner_identifier`
4. `iban`
5. `bank_branch`
6. `cheque_serial`
7. `sayad_id`

Custom profiles are stored in the single versioned option:

`pgr_scanner_profiles`

Scanner Profiles v1 is intentionally bounded to:

- parser type `segments_v1`;
- separator `newline`;
- boolean `trim`;
- boolean `normalize_digits`;
- ordered output definitions containing safe ASCII `key`, human `label`, and boolean `required`.

There is no stored executable callback, `eval`, arbitrary regular-expression language, or scripting DSL. Malformed stored profiles are skipped and never become executable runtime definitions. Built-in IDs win over stored custom collisions.

For compatibility with existing form metadata, a custom profile's executable contract is immutable after creation. The immutable portion is parser type/separator/flags plus ordered output keys and their `required` semantics. Display labels and enabled state may change. Attempts to reorder, add, remove, rename, or otherwise change executable outputs on an existing custom profile fail with an explicit `immutable_contract` result rather than silently reinterpreting old form mappings. A structurally different contract must use a new profile ID.

`runtime_profiles()` exports only enabled, validated definitions. PHP serializes these definitions before the pure Scanner core script as `window.PGRScannerProfiles`; the JavaScript core validates the bounded model again before accepting it.

Supported mapped destination field types are exactly:

- `text`
- `hidden`

Profiles are structural only. They do not assert bank validity, cheque validity, checksum authority, cross-bank compatibility, payment behavior, or financial business rules.

### `PGR_GF_Field_Structured_Scanner`

Field type:

`pgr_structured_scanner`

The field is a non-persistent controller with `displayOnly = true`.

Field-owned persisted configuration properties:

- `scanner_profile`
- `scanner_mappings`

The canonical mapping representation for new/edited Scanner fields is keyed by stable output key:

```text
scanner_mappings = {
  output_key: gravity_forms_field_id
}
```

Legacy forms that already contain positional `scanner_mappings` arrays remain readable. A legacy array is deterministically converted from position to the selected profile's ordered output keys at runtime and in the Form Editor. That compatibility is safe because the executable output contract for an existing custom profile is immutable and the built-in `sayad_v01` contract remains fixed. No migration rewrites forms silently.

A keyed mapping that contains a key not present in the selected profile is invalid. A positional mapping longer than the selected profile output list is invalid. Missing/disabled profiles and malformed mappings produce an explicit unavailable/configuration state rather than a partial or remapped update.

Raw Scanner payload is not a persisted field value:

- capture markup has no Gravity Forms `name="input_<id>"`;
- the capture control is a multiline `textarea` so LF/CRLF survives the browser value boundary;
- `get_value_save_entry()` returns an empty string;
- `get_value_entry_detail()` returns an empty string;
- `get_value_entry_list()` returns an empty string;
- `get_value_merge_tag()` returns an empty string;
- `get_value_export()` returns an empty string.

The PHP field exports only configuration metadata needed by the browser: selected profile, normalized keyed mapping configuration, allowed target descriptors, safe status strings, and Scanner field ID.

## Structured Scanner browser contract

### Authority split

`PGR_Scanner_Profile_Registry` is the PHP authority for which built-in/custom profiles are valid and enabled. Its validated runtime definitions are serialized into `window.PGRScannerProfiles` before the pure core loads.

`assets/js/pgr-structured-scanner-core.js` is the pure browser authority for:

- validating the serialized bounded runtime profile model;
- `segments_v1` newline structural parsing for built-in and custom profiles;
- LF/CRLF normalization;
- optional Persian/Arabic digit normalization according to the profile flag;
- required/optional output semantics;
- profile lookup;
- mapping validation;
- atomic update-plan construction;
- parser-driven capture-completion decisions.

The core does not contain a second hard-coded executable Sayad-only registry. Tests may inject profile fixtures through the same bounded configuration function, but production runtime definitions come from PHP.

`assets/js/pgr-structured-scanner.js` is a thin DOM adapter. It does not define a second segment grammar or display raw parser diagnostics to officers.

### Message-state contract

The PHP-rendered component and the DOM adapter share these states:

- `ready` — capture can begin;
- `processing` — an explicit/complete capture is being evaluated;
- `success` — the complete mapped update was applied;
- `invalid` — the scanned payload could not be parsed safely;
- `configuration` — profile/mapping/target configuration is unavailable or invalid.

The adapter uses only PHP-provided ready/processing/success/invalid/configuration messages. Parser failures such as invalid segment count resolve to the non-empty generic invalid message; configuration failures resolve to the configuration message. There is no dependency on an undeclared `data-pgr-message-segment-count` attribute.

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
- unknown/unavailable profile;
- malformed runtime profile definition;
- missing target;
- unsupported target type;
- duplicate target;
- self-target;
- invalid scan structure;
- missing required segment.

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

Uses normal WordPress admin pages. Scanner Profiles writes require `manage_options`, a matching nonce, request sanitization, and registry validation.

Current settings option remains:

`pgr_settings`

Current settings key remains:

`default_force_english`

Scanner Profiles admin assets are scoped to PersianGravity admin pages:

- `assets/css/pgr-admin.css`
- `assets/js/pgr-admin-profiles.js` on the Scanner Profiles page
- `assets/css/pgr-scanner-editor.css` only in the Gravity Forms Form Editor

The profile-admin JavaScript only manages output-row presentation/order and deletion confirmation. It does not create or execute parser rules.

## Form-level persistence

Current form property:

`pgr_normalize_digits`

When enabled, string values pass through server-side Persian/Arabic digit normalization before Gravity Forms persists them.

Structured Scanner field configuration adds `scanner_profile` and `scanner_mappings` to its field object. New mappings are stable-key based; legacy positional mappings are accepted only under the immutable profile-contract compatibility rule described above. Scanner raw payload is explicitly outside persistence authority.

## Asset policy

National ID frontend JavaScript:

`assets/js/pgr-frontend.js`

It is conditionally enqueued only when a National ID field opts into typing-time digit normalization.

Structured Scanner frontend assets:

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

## Localization provider

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

**Current source limitation:** exact target packages/POTs were unavailable. Manifests
have no approved JS handles and production PO scaffolds have no translations;
no runtime catalog is generated from empty source. All products remain dormant
pass-through until verified source admission. See `docs/LOCALIZATION.md` for exact
contracts, Core references, source versions/provenance and remaining validation.

## Explicit non-goals

Do not add these without a new product decision:

- general font delivery;
- payment gateways;
- workflow/business rules;
- SRWF-specific behavior;
- custom databases;
- bank/checksum/business validation for `sayad_v01`;
- executable custom parser callbacks, eval, or arbitrary scripting;
- a general barcode/scanner framework or global keyboard interception layer;
- historical field-ID compatibility (`mellicart`, `ir_national_id`);
- broad migration machinery for removed legacy installations;
- parallel plugin frameworks or service-container architecture.

## Testing boundary

Automated tests cover built-in/custom Scanner parsing, malformed profile rejection, required/optional semantics, mapping compatibility/atomicity, custom-profile persistence and immutable edit behavior, bootstrap contracts, non-persistence value surfaces, capture markup, conditional asset loading, runtime integrity, WPCS, PHPCompatibility, and repository PHPUnit behavior that can be exercised without a licensed real Gravity Forms environment.

A real WordPress + Gravity Forms integration environment is still required to prove browser/editor/frontend behavior for Structured Scanner. Unit/stub coverage and green CI are not equivalent to that proof.

See `docs/VALIDATION.md`.
