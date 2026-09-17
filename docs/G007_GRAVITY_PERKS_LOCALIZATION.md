# G-007 — Gravity Perks Family Persian Localization

G-007 is an exact-package, exact-domain extension of PersianGravity's existing bounded `fa_IR` localization provider. It does not create a Gravity Perks-wide translation subsystem and it does not modify licensed vendor packages.

## Exact admitted products

| Product | Version | Domain | Exact owner-supplied package SHA-256 | Canonical source-backed identities |
| --- | --- | --- | --- | ---: |
| Gravity Perks | 2.3.16 | `gravityperks` | `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c` | 83 |
| GP File Upload Pro | 1.5.13 | `gp-file-upload-pro` | `fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce` | 39 |
| GP Advanced Select | 1.1.21 | `gp-advanced-select` | `d83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2` | 5 |

All 127 canonical identities are `CONTENT_ADMITTED_FULL`, with two review passes, three second-pass wording corrections, no fuzzy entries, no empty translations, and no unreviewed or rejected identities. Runtime product manifests remain declarative and all three native script-translation maps are empty.

## Source authority and POT reconciliation

Source extraction is based on literal gettext calls in the exact supplied package source, not POT membership alone. The vendor POTs are retained as consistency evidence:

- Gravity Perks POT SHA-256 `d56ae3bab39365193e17f9c4113ed30e65f33565d2697749df2931890b6f453d`: 86 POT keys, 83 source-backed primary-domain keys, 3 plugin-header metadata-only POT keys, 0 source-backed keys missing from POT.
- GP File Upload Pro POT SHA-256 `f2ccb660acd7b6952950eef9a617386da6b4bc19a22ede8f765765578ece1b7b`: 44 POT keys, 39 source-backed primary-domain keys, 5 plugin-header metadata-only POT keys, 0 source-backed keys missing from POT.
- GP Advanced Select POT SHA-256 `1b595f32532235e931a5572b880fa03840c3420dbcfd40384cac52d566301923`: 10 POT keys, 5 source-backed primary-domain keys, 5 plugin-header metadata-only POT keys, 0 source-backed keys missing from POT.

Cross-domain calls remain outside their primary providers. Gravity Perks has one `gravity-perks` reference plus four calls without an explicit managed domain; these are recorded but not intercepted. File Upload Pro has one `gravityperks` bootstrap reference. Advanced Select has four `gp-advanced-phone-field`, one `gp-populate-anything`, and one `gravityperks` reference. None is silently merged into the three G-007 provider catalogs.

## File Upload Pro runtime localization

GP File Upload Pro 1.5.13 resolves uploader labels through ordinary PHP gettext and passes the resulting values through `wp_localize_script()` to its frontend Vue runtime (`class-gp-file-upload-pro.php:395-414`, with form data localized near line 382). Therefore the existing PHP provider path is the correct runtime boundary; no WordPress script-translation JSON handle is necessary.

The explicit screenshot-backed targets are admitted as:

- `select files` → `انتخاب فایل‌ها`
- `Drop files here` → `فایل‌ها را اینجا رها کنید`
- `or` → `یا`

The deterministic MO and `.l10n.php` contain these exact values. A real licensed browser rendering remains a separate evidence level and is not inferred from catalog/source proof.

## GP Advanced Select RTL root cause and fix

The exact Advanced Select package uses the style handle `gp-advanced-select-tom-select`. Its Tom Select runtime derives direction from the original control and places `rtl` on the `.ts-wrapper`. The bundled CSS contains direction selectors for `.ts-control.rtl` and fixes the single-select caret background to the right (`styles/tom-select.bootstrap5.css:299-307` and `:583-587`). That selector/state mismatch leaves the caret on the wrong side in RTL.

PersianGravity adds one bounded compatibility adapter, `PGR_Gravity_Perks_RTL`, which:

- runs only when the effective locale is `fa_IR` and WordPress is RTL;
- requires the exact `gp-advanced-select-tom-select` handle to be registered;
- attaches inline CSS to that vendor-owned handle instead of editing vendor files;
- targets only `.ts-wrapper.rtl`, moves the single-select caret to the left, and mirrors the vendor caret-reservation padding so the text no longer keeps the LTR right-side caret gap;
- remains dormant when Advanced Select is absent, the locale is not `fa_IR`, RTL is false, or the exact style handle is unavailable.

This is a source-proven compatibility repair rather than a general Select/Tom Select override.

## Deterministic artifacts and authority

Editable provider source remains under `languages/providers/<product>/source/`. Runtime output is deterministic MO and `.l10n.php`; no G-007 native script translation JSON is generated. `tools/i18n/admission/g007-gravity-perks.json` locks package identity, POT reconciliation, source-backed census, cross-domain boundaries, content fingerprints, review indexes, and the screenshot-backed acceptance paths. `tools/i18n/g007-admission.php` and `tools/i18n/g007-build.php` extend the existing build without changing the historical GF/Flow/GravityView admission contracts.

Exact fingerprints are intentionally fail-closed. A package, source census, provider PO, review index, accepted translation, or generated artifact drift requires deliberate authority updates rather than silent runtime acceptance.
