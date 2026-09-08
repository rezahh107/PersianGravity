# Translation assets

The root is for PersianGravity's own `persian-gravityforms` UI assets (`composer i18n:pot`).
`providers/` is the explicitly managed generic `fa_IR` provider overlay.
Provider `source/` directories contain editable PO and build provenance; generated
runtime catalogs sit beside `source/`. Empty scaffolds intentionally generate only
metadata, leaving WordPress/vendor translation behavior intact.

No PO parsing, compilation, downloads, database, or vendor writes occur at runtime.
See `docs/LOCALIZATION.md` for the unverified product surfaces and completion gates.
