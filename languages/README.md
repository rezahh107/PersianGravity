# Persian Gravity Forms translations

Root catalog files serve the `persian-gravityforms` own-plugin text domain in 4.2.0. The separate `providers/` tree contains bounded generic `fa_IR` overlay sources and build metadata.

Version 4.2.0 ships:

- `persian-gravityforms.pot`
- `persian-gravityforms-fa_IR.po`
- `persian-gravityforms-fa_IR.mo`

Generate/update the POT from repository source with:

```sh
composer i18n:pot
```

The always-visible Persian/English capability labels and descriptions are source-owned Module Registry metadata, not a replacement for gettext. Ordinary UI strings continue to use WordPress i18n.

Provider `source/` directories contain editable PO and provenance. Derived MO, `.l10n.php` and approved-handle JSON belong beside `source/`. Gravity Forms 3.1.1.1, Gravity Flow 3.1.0 and GravityView 3.3.4 are `PACKAGE_INSPECTED_METADATA_ONLY` at the source-admission layer from exact owner-supplied, SHA-256-pinned packages. Vendor authenticity is not claimed. GravityView's exact package contains no vendor POT; its source census is therefore bound to deterministic source key/reference fingerprints instead of an invented POT hash.

Content authority is separately bounded. Gravity Forms retains the merged PR #19 production authority: six independently admitted records with a deterministic union of 1759 identities from the independent 4207-message source census. Gravity Flow now admits all seven accepted registry surfaces with counts 15 / 255 / 43 / 20 / 391 / 44 / 0; 768 surface occurrences deterministically union to 732 unique identities from the independent 1098-message source census, leaving 366 non-admitted identities. Its native JS handle count remains zero and no Gravity Flow translation JSON is generated. GravityView retains its separately bounded existing content authority and dependency/domain boundary. Full vendor packages/source/POT corpora and the full reviewed Persian baselines are not committed; non-expressive admission evidence lives under `tools/i18n/admission/` and is excluded from distribution.

`composer i18n:check` compares derived output without changing source and validates source/content-admission boundaries. Provider entries win where present; upstream/vendor/TranslationsPress remains fallback for every missing identity. Vendor files and updaters are never overwritten, deleted or disabled. Runtime performs no PO parsing, compilation, downloads or database writes. Arbitrary-domain interception remains forbidden. See `docs/LOCALIZATION.md`, `docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md`, `docs/WU004_GRAVITYVIEW_SOURCE_ADMISSION.md`, and `docs/WU007_GRAVITYFORMS_FRONTEND_CONTENT_ADMISSION.md`.

CI validates own-plugin PO/POT/MO using GNU `msgfmt --check`, `msgcmp`, and byte comparison with the committed MO. Provider build/admission checks are additional and do not replace that gate.
