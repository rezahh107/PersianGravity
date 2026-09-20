# Persian Gravity Forms translations

Root catalog files serve the `persian-gravityforms` own-plugin text domain in 4.6.0. The separate `providers/` tree contains bounded generic `fa_IR` overlay sources and build metadata.

Version 4.6.0 ships:

- `persian-gravityforms.pot`
- `persian-gravityforms-fa_IR.po`
- `persian-gravityforms-fa_IR.mo`

Generate/update the POT from repository source with:

```sh
composer i18n:pot
```

The always-visible Persian/English capability labels and descriptions are source-owned Module Registry metadata, not a replacement for gettext. Ordinary UI strings continue to use WordPress i18n.

Provider `source/` directories contain editable PO and provenance. Derived MO, `.l10n.php` and approved-handle JSON belong beside `source/`. The existing exact owner-supplied targets remain Gravity Forms 3.1.1.1, Gravity Flow 3.1.0 and GravityView 3.3.4. Gravity Forms is revision-3 `CONTENT_ADMITTED_FULL` at 4207/4207 accepted source-backed identities, Gravity Flow is revision-3 `CONTENT_ADMITTED_FULL` at 1098/1098, and GravityView remains revision-2 `CONTENT_ADMITTED_PARTIAL` at 461/3127 with exactly 2666 identities still mandatory under G-006. GravityView's exact package contains no vendor POT; its source census is bound to deterministic source key/reference fingerprints instead of an invented POT hash. `gk-query-filters` remains a separate bounded dependency/domain and `action-scheduler` remains outside provider authority.

G-007 adds three separately bounded provider directories and exact primary-domain authorities: Gravity Perks 2.3.16 (`gravityperks`) at 83/83, GP File Upload Pro 1.5.13 (`gp-file-upload-pro`) at 39/39, and GP Advanced Select 1.1.21 (`gp-advanced-select`) at 5/5, for 127/127 reviewed accepted identities. These exact package/version/domain boundaries do not implicitly admit other Gravity Perks products or future versions. Cross-domain calls observed in the supplied source remain explicit exclusions rather than catalog imports.

All three G-007 native WordPress script-translation maps are empty and no G-007 provider translation JSON is generated. File Upload Pro's uploader strings are PHP-gettext values passed through `wp_localize_script()`, so its Persian labels use the existing PHP provider path rather than a parallel JavaScript catalog. GP Advanced Select has one bounded compatibility adapter that attaches inline RTL CSS only for `fa_IR` + RTL to the exact `gp-advanced-select-tom-select` style handle; it does not edit vendor files or updater behavior.

`composer i18n:check` compares derived output without changing source and validates source/content-admission boundaries. Provider entries win where present; upstream/vendor/TranslationsPress remains fallback for every missing identity. Vendor files and updaters are never overwritten, deleted or disabled. Runtime performs no PO parsing, compilation, downloads or database writes. Arbitrary-domain interception remains forbidden.

Exact package/source/build evidence is available for G-007, but exact-package browser rendering of GP File Upload Pro 1.5.13's visible uploader labels and screenshot-level GP Advanced Select 1.1.21 caret placement remains `NOT_VERIFIED`. Source/unit/CI evidence is not browser proof. See `docs/LOCALIZATION.md`, `docs/G007_GRAVITY_PERKS_LOCALIZATION.md`, `docs/VALIDATION_G007.md`, `docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md`, and the G-006 GravityView records for the controlling evidence boundaries.

CI validates own-plugin PO/POT/MO using GNU `msgfmt --check`, `msgcmp`, and byte comparison with the committed MO. Provider build/admission checks are additional and do not replace that gate.
