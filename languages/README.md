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

Provider `source/` directories contain editable PO and provenance. Derived MO, `.l10n.php` and approved-handle JSON belong beside `source/`. Current GF/Flow/View scaffolds have no admitted licensed source or translations and no approved JS handles: build emits metadata only, and product coverage remains unknown/null.

`composer i18n:check` compares derived output without changing source. Provider entries win where present; upstream/vendor/TranslationsPress remains fallback. Vendor files and updaters are never overwritten, deleted or disabled. Runtime performs no PO parsing, compilation, downloads or database writes. Arbitrary-domain interception remains forbidden. See `docs/LOCALIZATION.md` for the source admission boundary.

CI validates own-plugin PO/POT/MO using GNU `msgfmt --check`, `msgcmp`, and byte comparison with the committed MO. Provider build checks are additional and do not replace that gate.
