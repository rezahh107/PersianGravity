# AGENTS.md — Persian Gravity Forms v4

This document defines current repository rules for human contributors and coding agents.

## 1. Canonical repository identity

- Repository: `rezahh107/PersianGravity`
- Plugin name: `Persian Gravity Forms`
- Version: `4.8.0`
- Entrypoint: `persian-gravityforms.php`
- Runtime prefix: `PGR_`
- Text domain: `persian-gravityforms`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`

There is exactly one canonical runtime. Do not recreate removed `src/PersianGravityForms/*` or `GFPersian_*` architectures.

## 2. Product scope

Current bounded modules are exactly `national_id`, `jalali_date`, `jalali_presentation`, `iranian_address`, `digit_normalization`, `iranian_currency`, and `structured_scanner`. `PGR_Module_Registry` owns their source metadata and defaults; it is not a third-party extension framework. The six pre-G-008 modules retain their previous default-enabled behavior. `jalali_presentation` is opt-in and defaults disabled so upgrade alone cannot change system-date presentation.

Out of scope: fonts, arbitrary third-party translation ownership, payment gateways, workflow/SRWF rules, custom databases, bank/checksum/cross-bank authority, OCR/camera scanning, remote module registries or marketplaces.

PersianGravity may also provide generic `fa_IR` localization overlays for explicitly manifested Gravity ecosystem domains. This is cross-cutting infrastructure outside the user-module registry. Exact licensed source admission is required before activating catalog content or JS handles.

Cross-product responsibility with Gravity Presentation Profiles (GPP) is governed by [`docs/PERSIANGRAVITY_GPP_RESPONSIBILITY_BOUNDARY.md`](docs/PERSIANGRAVITY_GPP_RESPONSIBILITY_BOUNDARY.md). That contract preserves host → PersianGravity → GPP ownership, does not widen runtime authority by documentation, and requires GPP to qualify its own exact PersianGravity provider release/source seam before consumption.

## 3. Runtime architecture

```text
persian-gravityforms.php
        |
        +-- PGR_Localization (immediate, load-free resolver registration)
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
                    +-- Jalali system-date presentation [conditional]
                    +-- Iranian Address [conditional]
                    +-- Digit normalization [conditional]
                    +-- Iranian Currency [conditional]
                    +-- Structured Scanner [conditional]
```

Disabled modules must not register owned fields/hooks/assets merely to return early later. Shared utility loading may use explicit small conditions; do not introduce a service container or generic dependency resolver.

## 4. Module persistence and disable safety

`pgr_modules` is a small autoloaded option with schema version 1 and booleans only. Missing/malformed state falls back safely to source defaults. The six pre-G-008 modules default enabled; `jalali_presentation` defaults disabled. When a schema-v1 option from an older installation lacks the new key, source-default merging keeps all stored legacy states unchanged and leaves G-008 disabled. Do not persist labels, callbacks, class names, paths or Help content.

Disable checks run only on the explicit admin disable request. Use supported Gravity Forms APIs and form metadata only; never direct SQL or Entry scans. `USED` blocks. `UNKNOWN` requires explicit second confirmation. Gravity Forms unavailable is `UNKNOWN`. Enable does not require a usage scan. `jalali_presentation` owns no persisted form configuration, so its disable usage result is `UNUSED`.

Disabling must never delete Entries, form definitions, `pgr_settings`, `pgr_scanner_profiles`, mappings or form settings.

## 5. Capability contracts

- National ID: `pgr_national_id`; server Mod-11 validation, Persian/Arabic digit normalization, canonical 10 ASCII digits, native No Duplicates normalization. Typing normalization is UX only.
- Jalali: `pgr_jalali_date`; dedicated field, server Jalali validation, canonical ASCII `YYYY-MM-DD` with Jalali semantics. Never replace native GF Date fields.
- Jalali system-date presentation: `jalali_presentation`; opt-in presentation of authoritative Gregorian/system dates through the typed `PGR_Jalali_Presentation` facade. Currently admitted bounded surfaces are `gravityforms.entries-list.date-created`; Gravity Flow Inbox `gravityflow.inbox.date-created`, `gravityflow.inbox.last-updated`, and `gravityflow.inbox.due-date`; Gravity Flow Status `gravityflow.status.date-created` and `gravityflow.status.workflow-timestamp`; Gravity Flow Entry Detail workflow-info `gravityflow.entry-detail.submitted`, `gravityflow.entry-detail.last-updated`, `gravityflow.entry-detail.due-date`, and `gravityflow.entry-detail.expiration`; Gravity Flow `gravityflow.timeline-history` with inherited `gravityflow.print`; plus exact GravityView 3.3.4 `gravityview.date-created` and `gravityview.date-updated`. The direct Inbox/Status capabilities are runtime-qualified by canonical `gravityflow/gravityflow.php` host identity, a bounded syntactically valid `GRAVITY_FLOW_VERSION` observation, and their admitted callback/source/context/provenance contracts; a differing version string is not by itself a runtime veto for those direct adapters. The four Entry Detail workflow-info date capabilities are different: their shared `gravityflow_date_format_entry_detail` → owned request-local marker → exact `date_i18n` seam has no semantic row identity, so marker ownership is admitted only when runtime `GRAVITY_FLOW_VERSION` exactly matches the existing repository-owned Gravity Flow `target_version`. That exact-version check must happen before marker ownership is armed; post-render four-call reconciliation is diagnostics-only and cannot be the sole safety gate. On version drift Entry Detail dates remain native and the four date capabilities are not promoted to `AVAILABLE`. For the exact qualified host, Entry Detail preserves admitted `$gmt === true` plus integer timestamp-with-offset semantics, local-civil extraction without a second timezone conversion, marker removal on every PersianGravity-owned fallback, and aggregate four-capability diagnostics only when a complete four-call render truthfully supports attribution. Inbox due-date must preserve the raw integer epoch/0 → immediately matching display one-shot contract without re-entering `get_due_date_timestamp()`. Status must preserve the one-shot `gravityflow_entry_url_status_table` → matching value callback proof so direct/export paths remain native. Entry Detail Persian digit shaping is independently bounded to the exact post-workflow-info hook, `fa_IR`, canonical host identity, one-time asset enqueue, exact status-box root, `.gravityflow-status-box-field` descendants and human-visible text nodes only; version equality is not its runtime oracle. Do not create a shared digit-shaping diagnostics capability unless browser success can be reported truthfully without intrusive instrumentation; PHP enqueue eligibility alone is not browser-contract `AVAILABLE`. The exact Owner-supplied Flow 3.1.0 package remains the authentic qualification baseline and no real newer package is implied compatible. Timeline runtime admission is capability-local: canonical Flow plugin identity plus bounded Flow/GF version observations as provenance, the nearest complete contiguous admitted caller chains, exact argument vectors, stable note/notes/Entry/form/event identity, strict authoritative UTC `note->date_created`, independently derived WordPress-localized timestamp semantics, exact `$gmt === true` + integer timestamp behavior, request-local one-shot marker/time ownership, replay/staleness/re-entrancy isolation, and successful calendar admission before time-digit shaping. Exact Flow/GF version equality and whole-file source fingerprints are not Timeline runtime activation authorities; the exact Owner-supplied package and source fingerprints remain mandatory qualification evidence in CI. Print owns no independent date seam or calendar authority: it is observed/admitted only when `Gravity_Flow_Print_Entries::render()` actually contains the same live admitted Timeline invocation, and nested Timeline calls must not borrow an outer Print owner. A Print divergence must not disable ordinary Timeline or fabricate a Print result. These adapters are presentation-only: native raw/storage/API/query/workflow/deadline/schedule/expiration semantics remain authoritative. Exact Flow 3.1.0 Status `gravityflow.status.due-date` and Entry Detail `gravityflow.entry-detail.schedule` remain evidence-qualified final no-admission surfaces. A final no-admission disposition is exact-package evidence, not permanent impossibility: a newly discovered supported bounded seam in the same 3.1.0 package requires fresh qualification. GravityView and any other unmigrated adapters retain their exact qualified version/seam guards until separately migrated. GravityView admission is presentation-only through the exact field-specific output hooks, reads the authoritative raw UTC Entry property rather than reparsing localized display text, and must not alter DB/API/query/search/sort/filter semantics. This capability remains independent from `jalali_date` and must never reinterpret `pgr_jalali_date` storage as Gregorian.
- Address: address type id `iran` and Iranian province choices.
- Digit normalization: form-level `pgr_normalize_digits` plus authoritative server `gform_save_field_value` normalization; semantically separate from National ID typing UX.
- Currency: IRR/IRT definitions, zero decimal places in current source; no payment behavior.
- Structured Scanner: `pgr_structured_scanner`, transient/displayOnly, raw value not persisted, text/hidden destinations only, parser-driven completion, atomic fail-closed mapping. Built-in `sayad_v01` outputs exactly `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, `sayad_id` and is structural only.

Scanner Profiles administration is non-disableable and remains available when Scanner runtime is disabled.

## 6. Admin, Help and i18n

Persian Gravity admin pages are Overview, Scanner Profiles, Settings, System Status and Help & Documentation. Admin CSS/JS must remain scoped to these screens.

Capability presentation explicitly contains FA/EN metadata together. Persian is primary only when WordPress user locale is Persian; otherwise English is primary. Technical identifiers are LTR.

Ordinary UI uses WordPress gettext with text domain `persian-gravityforms`. Arbitrary external-domain interception remains prohibited; only the bounded provider contract below is permitted. The full bilingual Help Catalog is static, local, source-owned and version-controlled. Use native `WP_Screen` contextual help for concise page help and links into the full Help Center.

### Bounded localization provider

PersianGravity's own UI uses `persian-gravityforms`. For explicitly supported products
and `fa_IR`, PersianGravity may act as the generic local provider/overlay through
**Shared Core + Declarative Product Manifests + Bounded Adapters** (closed decision C).
This intentionally replaces the previous blanket foreign-translation ban.

Locked contracts:

- Provider entries win collisions; missing entries retain upstream/vendor/TranslationsPress fallback.
- Registry-level discovery supports operation without an upstream Persian catalog and preserves existing upstream paths.
- Partial JavaScript catalogs preserve upstream messages, contexts and compatible plural metadata.
- Resolver registration occurs immediately in the plugin file, independently of optional vendor classes; registration must not load foreign translations.
- Load activity before PersianGravity itself is included cannot be intercepted. Do not erase already-loaded translation state to hide this boundary.
- PO is editable source; compilation, source census, hashes and drift checks belong exclusively to development/CI.
- Domain/handle approval and catalog content require source provenance. Empty scaffolds do not prove product support or translation coverage.

Gravity Forms and Gravity Flow remain data-only for localization. Add executable GravityView handling
only for a source-proven failure which data cannot express. G-008's bounded admitted system-date adapters are separate presentation functionality
and do not change localization ownership. Do not introduce a service
container, product-class hierarchy, arbitrary callbacks/DSL, gettext replacement engine,
translation database/editor, vendor writes or updater disabling, SRWF semantic rewriting,
or runtime compilation/downloads. `load_textdomain_mofile` remains prohibited.

Commands: `composer i18n:pot` for own UI; `composer i18n:build` for deliberate
provider generation; `composer i18n:check` for non-mutating drift validation;
`PGR_WP_CORE=/path/to/pinned/core composer i18n:test` for real Core translation tests.
See `docs/LOCALIZATION.md` for source-admission and licensed integration gaps.

## 7. Gravity Forms API policy

Prefer documented public APIs/hooks, including `gform_loaded`, `GF_Field`, `GF_Fields::register()`, `GFAPI::get_forms()`, `gform_field_advanced_settings`, `gform_form_settings_fields`, `gform_save_field_value`, `gform_value_pre_duplicate_check`, `gform_enqueue_scripts`, `gform_address_types`, `gform_predefined_choices`, `gform_currencies`, `gform_entries_field_value`, and browser `gform/post_render` where already required.

Do not reintroduce deprecated form-settings paths or private/direct-SQL usage. G-008 may parse host-owned `date_created` only in bounded admitted adapters where the qualified host contract establishes the source semantics; the typed presentation facade must not accept arbitrary date-looking strings.

## 8. Testing and CI

Gravity Flow qualification package authority lives at `tools/compatibility/gravityflow-package.json`. Active qualification consumers must resolve it through the repository helper rather than discovering, substituting, or hard-coding another Gravity Flow package reference.

Gravity Flow compatibility diagnostics are informational maintenance evidence, not runtime authority. `PGR_Gravity_Flow_Compatibility_Diagnostics` reuses established G-008 capability IDs, accepts only the bounded `AVAILABLE` / `DEGRADED` / `UNAVAILABLE` / `NOT_EVALUATED` states and source-owned `PGR-GFLOW-*` reasons, and exposes only a latest bounded reporting snapshot. The optional cross-request option is non-autoloaded and must never be read by a production adapter to decide activation. Unknown/new Gravity Flow versions are not automatically incompatible. An adapter may stop using exact-version equality only through an evidence-backed capability-local migration that first preserves a stronger host/callback/data/context/provenance contract; version-string difference alone must not override that contract. The Entry Detail workflow-info date family currently does not satisfy that condition because its shared seam lacks semantic row identity, so it retains exact repository-target admission before marker ownership. Timeline does satisfy a separately proven capability-local replacement contract and therefore uses its canonical host/caller/data/note/entry/form/timestamp/marker behavior as runtime authority instead of exact Flow/GF equality or whole-file runtime hashes. Exact Owner-supplied package/source identity, source fingerprints and authentic path verification remain mandatory CI qualification evidence; synthetic version/source drift is architecture evidence only and cannot qualify a real newer package. Diagnostics observe the adapter decision after evaluation and may never supply or cache activation authority. `gravityflow.print` may be reported only when the same evaluated Timeline invocation is actually inside the live Print renderer; ordinary Timeline evaluation must never fabricate Print state. Browser-only outcomes must not be promoted to shared `AVAILABLE` merely from PHP enqueue/asset eligibility. Unmigrated adapters keep their existing guards until separately qualified.

Required validation categories:

```bash
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
node --test tests/js/g008-jalali-oracles.test.js
composer i18n:check
PGR_WP_CORE=/path/to/pinned/core composer i18n:test
```

CI preserves GNU-gettext validation of the own-plugin PO/POT/MO, provider artifact/provenance drift checks, pinned WordPress 6.7.2 and 7.1 localization contracts, PHP syntax, WPCS, PHPCompatibility, Scanner JS, runtime-integrity guards and PHPUnit PHP 8.2–8.5. Repository consistency tests must keep active version declarations, bilingual module metadata, Help coverage and translation assets aligned. G-008 additionally owns exhaustive ICU/reference differential verification, an exact Gravity Forms 3.1.1.1 Entries List runtime gate, and authentic exact Gravity Flow 3.1.0 runtime/browser qualification for its admitted Inbox/Status/Entry Detail/Timeline surfaces and inherited Print presentation. That exact 3.1.0 lane remains the qualification baseline even where a capability-local production runtime contract is version-equality-independent.

Unit/stub/source tests are not equivalent to a real WordPress + licensed Gravity Forms browser/integration test. Record executed vs unexecuted evidence in `docs/VALIDATION.md`.

## 9. Runtime-integrity and release safety

Do not reintroduce `src/`, `GFPersian_*`, `mellicart`, `ir_national_id`, external `load_textdomain_mofile` interception, bundled fonts, old payment/RSS/transaction subsystems or SRWF identifiers.

Work on focused branches. Do not force-push shared history, merge to `main`, publish tags/releases, or claim browser validation without actual execution. Material runtime changes update README, readme.txt, ARCHITECTURE, VALIDATION and—when governance changes—AGENTS in the same PR.