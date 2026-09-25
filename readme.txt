=== Persian Gravity Forms ===
Contributors: rezahh107
Requires at least: 6.7
Requires PHP: 8.2
Stable tag: 4.7.0
Tags: gravity forms, persian, iran, national id, jalali, scanner

Focused Persian and Iranian capabilities for Gravity Forms with runtime module control, bilingual product UI, and local help.

== Description ==

Persian Gravity Forms provides generic Persian/Iranian functionality for Gravity Forms. It also provides explicitly manifested `fa_IR` localization overlays for bounded Gravity ecosystem products; it does not own typography, workflow business rules, payment gateways, or arbitrary third-party translations.

Version 4.7.0 exposes six bounded source-defined modules enabled by default for backward compatibility plus one bounded opt-in module:

* Iranian National ID (`pgr_national_id`).
* Jalali Date (`pgr_jalali_date`).
* Jalali System-Date Presentation (`jalali_presentation`) — opt-in; verified presentation covers Gravity Forms Entries List `date_created` plus exact Gravity Flow 3.1.0 Inbox `date_created`/`last_updated`/`due_date`, Status `date_created`/`workflow_timestamp`, Entry Detail Submitted / Last Updated / Due / Expiration, and Timeline/history. Print receives Jalali calendar presentation only when it reuses that verified Timeline renderer; it has no independent Print date adapter. Stored/native and workflow-owned operational values remain unchanged.
* Iranian Address type and province choices.
* Form-level Persian/Arabic digit normalization (`pgr_normalize_digits`).
* Iranian Rial (IRR) and Toman (IRT) currency definitions.
* Structured Scanner (`pgr_structured_scanner`).

The Module Manager affects real runtime participation. Disabled modules do not register their owned Gravity Forms fields/hooks and do not enqueue their owned frontend assets. Core administration, Settings, System Status, Help, translation bootstrap, dependency notices, Module Registry, and Scanner Profiles administration remain available.

The existing six modules retain their previous default-enabled behavior. `jalali_presentation` defaults disabled, including on upgrades whose schema-v1 module option predates G-008, so installing/upgrading PersianGravity cannot silently change system-date presentation.

Before disable, PersianGravity performs a bounded Gravity Forms form-metadata usage check. Confirmed use blocks disable. When safe non-use cannot be established, the state is UNKNOWN and a second explicit confirmation is required. No Entries are scanned and no direct SQL is used. `jalali_presentation` owns no persisted form configuration and is therefore safe to disable.

Overview always presents capability names and concise descriptions in Persian and English. Ordinary application UI remains WordPress-gettext based with the `persian-gravityforms` text domain.

The complete Help & Documentation Center ships locally with Persian and English content and native WordPress contextual help links.

= Jalali System-Date Presentation =

`jalali_presentation` is separate from the dedicated `pgr_jalali_date` field. The field keeps true Jalali-domain storage semantics; the presentation module converts only explicitly known Gregorian/system sources for display.

The module uses a source-owned Borkowski-lineage Gregorian→Jalali engine and typed `DateTimeInterface` presentation facade. Gravity Forms Entries List `date_created` remains admitted through `gform_entries_field_value`. Exact Gravity Flow 3.1.0 additionally admits Inbox `date_created`/`last_updated`/`due_date`, Status-table `date_created`/`workflow_timestamp`, Entry Detail workflow-info Submitted / Last Updated / Due / Expiration, and Timeline/history through bounded host presentation seams. Print owns no independent calendar adapter: only its Timeline path inherits the already verified Timeline renderer. Timeline conversion changes only the localized Gregorian date component; native storage, raw `date_created`, note/event identity, bodies, ordering, surrounding native time semantics, API/state behavior and workflow semantics remain host-owned and unchanged. Unsupported formats or exact version/source/caller-chain drift fail closed to native output. Exact Flow 3.1.0 Status `due_date` and Entry Detail Scheduled calendar conversion remain evidence-qualified final no-admission surfaces. Separately, under `fa_IR`, exact Flow 3.1.0 Entry Detail status-box field text may receive presentation-only ASCII→Persian digit shaping; attributes, Entry ID URLs/query values, form/control values and all raw/system values remain native, while non-Persian and module-disabled requests do not load the digit adapter. Scheduled text may receive glyph shaping when present in that same status box, but its calendar conversion remains unadmitted. Timeline/history and Print remain outside this digit-shaping adapter.

The V1 `VALIDATED_PRODUCT_RANGE` is Gregorian `1800-01-01..2124-03-19`. Outside that evidence-backed range, native presentation is retained. Full provenance, MIT attribution, official University of Tehran golden cases, ICU/reference differential evidence, timezone behavior and known limitations are documented in `docs/G008_JALALI_PRESENTATION.md`.

= Structured Scanner =

Structured Scanner is a transient, display-only controller. Raw scan input is not intentionally persisted as the Scanner field value. Parsed outputs map only to ordinary `text` and `hidden` fields. Mapping is validated as one atomic update plan and fails closed for invalid scans or mappings.

Built-in `sayad_v01` uses structural `segments_v1` parsing with exactly these ordered outputs: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, and `sayad_id`. It does not establish Sayad checksum authority, bank validity, cross-bank compatibility, payment behavior, or online inquiry.

= Gravity ecosystem localization foundation =

The shared generic `fa_IR` provider overlay is cross-cutting infrastructure outside the module registry. Resolvers register immediately after constants without loading foreign catalogs. Provider translations win only where supplied; upstream/vendor/TranslationsPress remains fallback. No vendor files or updaters are changed.

The existing locked Gravity products remain Gravity Forms 3.1.1.1 at 4207/4207 accepted source-backed identities, Gravity Flow 3.1.0 at 1098/1098, and GravityView 3.3.4 at 461/3127. Exactly 2666 GravityView identities remain mandatory under G-006; its partial authority is not broadened by this release checkpoint.

G-007 adds separately bounded, exact-version provider authority for Gravity Perks 2.3.16 (`gravityperks`, 83/83), GP File Upload Pro 1.5.13 (`gp-file-upload-pro`, 39/39), and GP Advanced Select 1.1.21 (`gp-advanced-select`, 5/5): 127/127 reviewed source-backed primary-domain identities in total. Broader/current/future Gravity Perks products are not implicitly supported, observed cross-domain calls remain explicit exclusions, and all three native script-translation maps remain empty. File Upload Pro's uploader labels are supplied through its real PHP gettext → `wp_localize_script()` path, including `select files` → `انتخاب فایل‌ها`, `Drop files here` → `فایل‌ها را اینجا رها کنید`, and `or` → `یا`. GP Advanced Select uses a bounded `fa_IR` RTL compatibility adapter attached only to the exact `gp-advanced-select-tom-select` style handle; vendor package bytes, vendor CSS and updater behavior remain unmodified.

Current WU008 exact-package browser evidence now verifies the authentic File Upload Pro 1.5.13 labels/RTL layout and the GP Advanced Select 1.1.21 Tom Select caret/padding behavior at desktop and narrow RTL/LTR profiles. Advanced Select remains a bounded adapter case: exact version + `fa_IR`/RTL + exact style handle are required, the correction is scoped to the authentic wrapper, and LTR/drift states fail closed to native behavior. Source/unit evidence remains distinct from browser evidence. See `docs/LOCALIZATION.md`, `docs/G007_GRAVITY_PERKS_LOCALIZATION.md`, `docs/VALIDATION_G007.md`, and `docs/G009_RTL_BIDI_COMPATIBILITY.md`.

== Requirements ==

* WordPress 6.7 or newer.
* PHP 8.2 or newer.
* Gravity Forms 3.0 or newer.

== Installation ==

1. Install and activate Gravity Forms 3.0 or newer.
2. Upload and activate Persian Gravity Forms.
3. Open Persian Gravity > Overview to review module state.
4. Open a Gravity Forms Form Editor and use enabled PersianGravity fields/features.
5. Explicitly enable Jalali System-Date Presentation only if you want bounded system-date Jalali display.
6. Use Persian Gravity > Help & Documentation for the complete bilingual guide.

== Persistence ==

* `pgr_modules` stores schema version 1 and boolean module state only; legacy module states are preserved and `jalali_presentation` defaults false when absent.
* `pgr_settings` stores plugin settings such as `default_force_english`.
* `pgr_scanner_profiles` stores Custom Scanner Profiles.
* Field configuration, `pgr_normalize_digits`, Scanner Profile selection, and Scanner mappings remain Gravity Forms form metadata.

Disabling a module does not delete Entries, form definitions, settings, Profiles, or mappings. G-008 adds no data migration and does not rewrite existing Entry dates.

== Development ==

Run the repository validation commands:

`composer install`
`composer test`
`composer cs`
`composer compat`
`node --test tests/js/structured-scanner.test.js`
`node --test tests/js/g008-jalali-oracles.test.js`
`composer i18n:check`
`PGR_WP_CORE=/path/to/pinned/core composer i18n:test`

Generate/update the POT template with WP-CLI when available:

`composer i18n:pot`

Source/unit tests are not equivalent to a real licensed WordPress + Gravity Forms browser integration test.

== Changelog ==

= Unreleased =
* Added presentation-only Persian digit shaping for exact Gravity Flow 3.1.0 Entry Detail status-box field text in `fa_IR`, including visible Entry ID and native time text, while preserving ASCII/native storage, API, URLs/query values, attributes, controls, workflow state and non-Persian/module-disabled output. Timeline and Print remain untouched by this digit-shaping adapter, and Scheduled remains calendar-unadmitted.
* Added bounded Jalali calendar presentation for exact Gravity Flow 3.1.0 Timeline/history. Print inherits that verified Timeline rendering rather than owning a separate date adapter. Raw/storage/workflow values, note/event identity, order and bodies, and surrounding native time semantics remain unchanged; unsupported formats or exact version/source/caller-chain drift fail closed to native output. Status `due_date` and Entry Detail Scheduled remain calendar-unadmitted.

= 4.7.0 =
* Expanded exact Gravity Flow 3.1.0 Jalali system-date presentation to Inbox `date_created`/`last_updated`/`due_date` and Status `date_created`/`workflow_timestamp`, with exact-source and authentic runtime/browser evidence while preserving native raw/storage/API/query/sort/filter, workflow/assignment, due/overdue/deadline, compare-value, and Status CSV/export semantics.
* Added bounded Entry Detail workflow-info Jalali presentation for Submitted / Last Updated / Due / Expiration. Exact Flow 3.1.0 Status `due_date`, Entry Detail Scheduled, Timeline/history timestamps, and independent Print date seams remain native under evidence-qualified no-admission dispositions where no safe supported presentation seam was proven.
* Closed the remaining non-GravityView G-009 Gravity Perks browser scope: exact public-read package provisioning, authentic File Upload Pro Persian labels/RTL, exact Advanced Select Tom Select qualification with a scoped logical-padding repair, and a final no-repair/no-admission disposition for `gform_admin` on the exercised Flow frontend surface.

= 4.6.0 =
* Added opt-in `jalali_presentation` as a module independent from the existing `jalali_date` field.
* Added a source-owned Borkowski-lineage Gregorian→Jalali presentation engine, typed timezone-aware facade, bounded Gravity Forms Entries List `date_created` adapter, exhaustive ICU/reference verification, official-calendar golden fixtures, and native fallback outside the validated range.
* Preserved Gregorian/UTC Entry storage, API, sorting and filtering semantics; no version/tag/release change is part of G-008 implementation.

= 4.5.0 =
* Added bounded Persian localization support for exact Gravity Perks 2.3.16, GP File Upload Pro 1.5.13 and GP Advanced Select 1.1.21, covering 127 reviewed source-backed primary-domain identities.
* Added Persian File Upload Pro uploader labels through the product's real gettext/localized-script path and a bounded `fa_IR` RTL compatibility adapter for GP Advanced Select's Tom Select caret/padding behavior, without modifying vendor files.
* Existing Gravity Forms and Gravity Flow full localization states remain intact; GravityView remains partial at 461/3127 with 2666 identities still mandatory.

= 4.4.0 =
* Gravity Flow 3.1.0 now has complete accepted Persian coverage for all 1098 canonical identities, including the former 366 residual identities.
* Gravity Forms 3.1.1.1 now has complete accepted Persian coverage for all 4207 canonical source-backed identities, including the former 2448 residual identities; the known vendor-POT-only stale identity remains excluded.
* GravityView 3.3.4 remains partial at 461/3127 accepted Persian identities; 2666 identities remain mandatory and are not complete in this release.

= 4.3.0 =
* Completed Gravity Flow 3.1.0 Content Admission v2 across all seven accepted surfaces: 732 unique admitted identities, 366 non-admitted source identities, an auditable zero-identity Entry Detail Sidebar record, zero JS handles/translation JSON, and unchanged fallback semantics.
* Completed GravityView 3.3.4 Content Admission v2 across all six accepted surfaces: 467 surface occurrences, 461 unique admitted identities, 2666 unclassified/non-admitted source identities, deterministic MO/`.l10n.php`, zero JS handles/translation JSON, preserved two-message shortcode baseline, and no licensed browser-validation claim.
* Synchronized active documentation to WU-004 GravityView 3.3.4 source admission: exact package/source evidence is admitted, while Persian translation content, runtime JS handles, provider MO/`.l10n.php`/translation JSON, and licensed browser validation remain absent/not run.
* Repaired the bounded Gravity Forms frontend-shortcode admission: seven locked Persian semantic corrections, product-scoped Gravity Flow regression fixtures, deterministic regenerated provider artifacts, and active documentation truth synchronization; 41/4207 scope, zero JS handles, no translation JSON, and fallback boundaries are unchanged.
* Admitted the reviewed Gravity Flow 3.1.0 Status surface alongside the existing Inbox surface through revision-2 deterministic multi-record content authority: 43 Status identities, 10 identical Inbox/Status overlaps, and 288 unique aggregate identities from a separate 1098-message source census; zero JS handles and no translation JSON remain unchanged.
* Historical source-admission checkpoint before WU-004: admitted exact Gravity Forms 3.1.1.1 and Gravity Flow 3.1.0 package/source evidence while GravityView remained package-unavailable at that checkpoint.
* Reconciled owner-authorized localization targets to Gravity Forms 3.1.1.1, Gravity Flow 3.1.0 and GravityView 3.3.4.
* Integrated the shared localization provider foundation with the existing 4.2.0 module manager and bilingual admin/help. Localization remains outside the six modules.
* Preserved provider fallback and deterministic build/provenance checks. No version bump or release.

= 4.2.0 =
* Added bounded `PGR_Module_Registry` with the small autoloaded `pgr_modules` option and default-enabled upgrade behavior.
* Added real runtime gating for National ID, Jalali Date, Iranian Address, digit normalization, IRR/IRT currencies, and Structured Scanner.
* Added bounded safe-disable form-metadata checks with USED/UNUSED/UNKNOWN behavior and explicit second confirmation for UNKNOWN.
* Added bilingual Persian/English capability cards and module state controls on Overview.
* Added local bilingual Help & Documentation plus native WordPress contextual help.
* Added module states to System Status while keeping Scanner Profiles available when Scanner runtime is disabled.
* Added Persian `fa_IR` translation assets and repository consistency/module/help regression tests.
* Synchronized active release metadata to 4.2.0.

= 4.1.0 =
* Established the current Structured Scanner and Scanner Profiles administration foundation used by 4.2.0.

= 4.0.0 =
* Consolidated the repository to one `PGR_*` runtime.
* Moved Gravity Forms initialization to `gform_loaded`.
* Reduced National ID and Jalali behavior to canonical field implementations and removed obsolete legacy architecture.
