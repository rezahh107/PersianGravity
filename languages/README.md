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

Provider `source/` directories contain editable PO and provenance. Derived MO, `.l10n.php` and approved-handle JSON belong beside `source/`. Gravity Forms 3.1.1.1 and Gravity Flow 3.1.0 remain `PACKAGE_INSPECTED_METADATA_ONLY` at the source-admission layer from exact owner-supplied, SHA-256-pinned packages; GravityView 3.3.4 remains `PACKAGE_UNAVAILABLE`.

Content authority is separately bounded. Gravity Forms now admits only the registered frontend shortcode surface `gravityforms::frontend_runtime::shortcode:gravityform`, derived from the exact `form_display.php` rule: 41 identities out of the independent 4207-message source census. Gravity Flow retains its Inbox + Status 288-identity union. Product runtime `scripts` maps remain empty, so no Gravity Forms or Gravity Flow translation JSON is generated. Full vendor packages/source/POT corpora and the full reviewed Persian baselines are not committed; non-expressive admission evidence lives under `tools/i18n/admission/` and is excluded from distribution.

`composer i18n:check` compares derived output without changing source and validates source/content-admission boundaries. Provider entries win where present; upstream/vendor/TranslationsPress remains fallback for every missing identity. Vendor files and updaters are never overwritten, deleted or disabled. Runtime performs no PO parsing, compilation, downloads or database writes. Arbitrary-domain interception remains forbidden. See `docs/LOCALIZATION.md`, `docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md`, and `docs/WU007_GRAVITYFORMS_FRONTEND_CONTENT_ADMISSION.md`.

CI validates own-plugin PO/POT/MO using GNU `msgfmt --check`, `msgcmp`, and byte comparison with the committed MO. Provider build/admission checks are additional and do not replace that gate.
