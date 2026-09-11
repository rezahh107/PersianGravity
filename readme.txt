=== Persian Gravity Forms ===
Contributors: rezahh107
Requires at least: 6.7
Requires PHP: 8.2
Stable tag: 4.2.0
Tags: gravity forms, persian, iran, national id, jalali, scanner

Focused Persian and Iranian capabilities for Gravity Forms with runtime module control, bilingual product UI, and local help.

== Description ==

Persian Gravity Forms provides generic Persian/Iranian functionality for Gravity Forms without owning typography, workflow business rules, payment gateways, or translations for other plugins.

Version 4.2.0 exposes six bounded source-defined modules, all enabled by default for backward compatibility:

* Iranian National ID (`pgr_national_id`).
* Jalali Date (`pgr_jalali_date`).
* Iranian Address type and province choices.
* Form-level Persian/Arabic digit normalization (`pgr_normalize_digits`).
* Iranian Rial (IRR) and Toman (IRT) currency definitions.
* Structured Scanner (`pgr_structured_scanner`).

The Module Manager affects real runtime participation. Disabled modules do not register their owned Gravity Forms fields/hooks and do not enqueue their owned frontend assets. Core administration, Settings, System Status, Help, translation bootstrap, dependency notices, Module Registry, and Scanner Profiles administration remain available.

Before disable, PersianGravity performs a bounded Gravity Forms form-metadata usage check. Confirmed use blocks disable. When safe non-use cannot be established, the state is UNKNOWN and a second explicit confirmation is required. No Entries are scanned and no direct SQL is used.

Overview always presents capability names and concise descriptions in Persian and English. Ordinary application UI remains WordPress-gettext based with the `persian-gravityforms` text domain.

The complete Help & Documentation Center ships locally with Persian and English content and native WordPress contextual help links.

= Structured Scanner =

Structured Scanner is a transient, display-only controller. Raw scan input is not intentionally persisted as the Scanner field value. Parsed outputs map only to ordinary `text` and `hidden` fields. Mapping is validated as one atomic update plan and fails closed for invalid scans or mappings.

Built-in `sayad_v01` uses structural `segments_v1` parsing with exactly these ordered outputs: `qr_version`, `owner_type`, `owner_identifier`, `iban`, `bank_branch`, `cheque_serial`, and `sayad_id`. It does not establish Sayad checksum authority, bank validity, cross-bank compatibility, payment behavior, or online inquiry.

= Gravity ecosystem localization foundation =

The shared generic fa_IR provider overlay is cross-cutting infrastructure outside the six modules. Resolvers register immediately after constants without loading foreign catalogs. Provider translations win only where supplied; upstream/vendor/TranslationsPress remains fallback. No vendor files or updaters are changed.

Owner-authorized targets are Gravity Forms 3.1.1.1, Gravity Flow 3.1.0 and GravityView 3.3.4. Gravity Forms has exactly one bounded production content-admission record for `gravityforms::frontend_runtime::shortcode:gravityform`: 41 identities from the separate 4207-message source census. The WU-003 full reviewed baseline remains provenance for that subset; seven explicit PR #15 semantic corrections define the final admitted Persian content. Gravity Flow retains exactly two bounded production content-admission records: Inbox (255 identities) and Status (43 identities), with 10 identical overlaps deduplicated to an aggregate of 288 unique admitted identities from a separate 1098-message source census. GravityView 3.3.4 is source-admitted at `PACKAGE_INSPECTED_METADATA_ONLY` from the exact owner-supplied package SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`; `project_source_authority=OWNER_SUPPLIED_EXACT_PACKAGE`, `vendor_authenticity=NOT_PROVEN`, and the exact package contains no vendor POT so `vendor_pot_sha256=null`. Its primary `gk-gravityview` census is 3127 canonical identities, including 127 contextual and 42 plural identities, with 3931 source references. WU-006 adds exactly one bounded GravityView content-admission record for `gravityview::frontend_runtime::shortcode:gravityview`, containing only two canonical identities proven by `src/Shortcode/GravityViewShortcode.php`. Their Persian text is copied unchanged from the final WU-002 reviewed baseline SHA-256 `2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca`; that full baseline contains 3281 reviewed canonical identities and does not grant blanket content authority. `gk-query-filters` remains a separate bounded Composer dependency/domain that PersianGravity does not manifest or runtime-activate. GravityView contributes six source-backed surfaces, making the current registry 19 total: 6 Gravity Forms + 7 Gravity Flow + 6 GravityView. For the admitted GravityView surface, the provider MO and `.l10n.php` are generated only from the two-message aggregate, the product script map remains empty, zero native JS translation handles are activated, and no GravityView translation JSON is generated. Non-admitted identities retain the existing upstream/vendor/TranslationsPress/source-English fallback. This is bounded `CONTENT_ADMITTED_PARTIAL`, not full-product or browser-complete localization; RTL/BiDi evidence is source-only and real licensed GravityView browser/UI validation remains `NOT_RUN`. See docs/LOCALIZATION.md, docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md, and docs/WU006_GRAVITYVIEW_FRONTEND_CONTENT_ADMISSION.md for the source/load-order and content-authority boundaries.

== Requirements ==

* WordPress 6.7 or newer.
* PHP 8.2 or newer.
* Gravity Forms 3.0 or newer.

== Installation ==

1. Install and activate Gravity Forms 3.0 or newer.
2. Upload and activate Persian Gravity Forms.
3. Open Persian Gravity > Overview to review module state.
4. Open a Gravity Forms Form Editor and use enabled PersianGravity fields/features.
5. Use Persian Gravity > Help & Documentation for the complete bilingual guide.

== Persistence ==

* `pgr_modules` stores schema version 1 and boolean module state only.
* `pgr_settings` stores plugin settings such as `default_force_english`.
* `pgr_scanner_profiles` stores Custom Scanner Profiles.
* Field configuration, `pgr_normalize_digits`, Scanner Profile selection, and Scanner mappings remain Gravity Forms form metadata.

Disabling a module does not delete Entries, form definitions, settings, Profiles, or mappings.

== Development ==

Run the repository validation commands:

`composer install`
`composer test`
`composer cs`
`composer compat`
`node --test tests/js/structured-scanner.test.js`
`composer i18n:check`
`PGR_WP_CORE=/path/to/pinned/core composer i18n:test`

Generate/update the POT template with WP-CLI when available:

`composer i18n:pot`

Source/unit tests are not equivalent to a real licensed WordPress + Gravity Forms browser integration test.

== Changelog ==

= Unreleased =
* Admitted exactly the first bounded GravityView 3.3.4 frontend shortcode content surface: two source-proven `gk-gravityview` identities copied unchanged from the final WU-002 reviewed baseline, deterministic MO/`.l10n.php`, zero JS handles/translation JSON, preserved fallback for all non-admitted identities, and no licensed browser-validation claim.
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
