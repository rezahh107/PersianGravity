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

Provider `source/` directories contain editable PO and provenance. Derived MO, `.l10n.php` and approved-handle JSON belong beside `source/`. Gravity Forms 3.1.1.1 and Gravity Flow 3.1.0 are now `PACKAGE_INSPECTED_METADATA_ONLY` from exact owner-supplied, SHA-256-pinned packages; GravityView 3.3.4 remains `PACKAGE_UNAVAILABLE`. The GF/Flow provider PO files remain header-only, all runtime `scripts` maps remain empty, and source admission generates no product MO, `.l10n.php`, or translation JSON. Full vendor packages/source/POT corpora are not committed; non-expressive admission evidence lives under `tools/i18n/admission/` and is excluded from distribution.

`composer i18n:check` compares derived output without changing source and now also validates the metadata-only admission boundary. Provider entries win where present; upstream/vendor/TranslationsPress remains fallback. Vendor files and updaters are never overwritten, deleted or disabled. Runtime performs no PO parsing, compilation, downloads or database writes. Arbitrary-domain interception remains forbidden. See `docs/LOCALIZATION.md` for the source admission boundary.

CI validates own-plugin PO/POT/MO using GNU `msgfmt --check`, `msgcmp`, and byte comparison with the committed MO. Provider build/admission checks are additional and do not replace that gate.
