# Persian Gravity Forms Validation — 4.2.0

This file distinguishes source evidence from real WordPress/Gravity Forms browser evidence.

Status vocabulary:

- `SOURCE_AUTOMATED_PASS` — an automated source/unit/static check was actually executed and passed.
- `MANUAL_ADMIN_PASS` — a real WordPress admin scenario was executed and passed.
- `MANUAL_GF_PASS` — a real WordPress + Gravity Forms scenario was executed and passed.
- `NOT_EXECUTED` — the check was not run.
- `NOT_PROVEN` — available evidence is insufficient for the claimed behavior.

## Baseline

Initial `main` for the 4.2 work:

`b1a52c975988bf60f473d4b843de057ed5bd0c06`

Runtime plugin header / `PGR_VERSION` at that checkpoint: `4.1.0`.

Observed active-documentation drift at baseline:

- README current version: `4.0.0`.
- AGENTS current version: `4.0.0`.
- readme Stable tag: `4.0.0`.
- `languages/` contained no POT/PO/MO assets.

These observations do not rewrite historical 4.0/4.1 validation claims; they only establish the 4.2 starting state. Earlier detailed validation remains historical evidence in repository history.

## 4.2 source changes

The 4.2 branch adds:

- bounded `PGR_Module_Registry` and `pgr_modules` state persistence;
- class-load and hook/field runtime gates;
- bounded safe-disable usage inspection;
- bilingual Overview module controls;
- module state in System Status;
- Scanner Profiles availability while Scanner runtime is disabled;
- local bilingual Help Catalog and native contextual help;
- `fa_IR` POT/PO/MO translation assets;
- module/usage/runtime/help/repository-consistency tests;
- active version metadata synchronized to 4.2.0.

## Executed before commit

### PHP syntax

Command category:

```sh
php -l <new-or-replaced-4.2-php-file>
```

Result: `SOURCE_AUTOMATED_PASS` for the new/replaced 4.2 PHP files prepared for the branch.

### MO structure

The generated `languages/persian-gravityforms-fa_IR.mo` was identified by the local `file` utility as a GNU message catalog.

Result: `SOURCE_AUTOMATED_PASS` for binary catalog structure only.

This does not prove WordPress locale loading.

## Repository CI

The existing workflow continues to run:

- shipped PHP syntax;
- WPCS/PHPCS;
- PHPCompatibility baseline;
- Structured Scanner pure-JavaScript tests;
- runtime-integrity guard;
- PHPUnit on PHP 8.2, 8.3, 8.4, and 8.5.

The new PHPUnit files are discovered through the existing test suite; no second CI system is introduced.

CI result for the 4.2 commit: `NOT_EXECUTED` until GitHub Actions runs on the pushed branch/PR. Exact run IDs must be recorded after execution.

## Translation generation

`composer i18n:pot`: `NOT_EXECUTED` in the preparation runtime because WP-CLI i18n tooling was unavailable there.

The shipped POT/PO/MO are therefore source assets that still require CI/reviewer verification against extracted source strings. Do not treat their presence as proof of complete translation coverage.

## Manual WordPress / Gravity Forms validation

No disposable real WordPress + licensed Gravity Forms environment was available during source preparation.

Therefore all of the following remain `NOT_EXECUTED` / `NOT_PROVEN` until performed:

1. Overview renders six bilingual capability cards.
2. Upgrade from 4.1.0 leaves all six modules enabled.
3. Disable an unused custom-field module and confirm its GF field disappears after reload.
4. Re-enable it and confirm field return.
5. Used custom-field module disable is blocked with affected-form count.
6. Form-level digit-normalization use blocks disable.
7. Currency `UNKNOWN` flow requires explicit second confirmation.
8. Scanner disable removes Scanner field/runtime assets while Scanner Profiles stays available.
9. Scanner re-enable restores field availability and keeps prior profile/mapping configuration.
10. System Status matches persisted module state.
11. `default_force_english` remains preserved across National ID disable/enable.
12. Help opens in Persian and English and language tabs work server-side.
13. Contextual Help appears on all PersianGravity screens.
14. `fa_IR` ordinary UI loads from the plugin text domain.
15. English locale renders English ordinary UI.
16. Technical IDs remain LTR.
17. PersianGravity admin assets do not load on unrelated wp-admin screens.
18. National ID enabled behavior remains functionally equivalent to pre-4.2.
19. Jalali enabled behavior remains functionally equivalent to pre-4.2.
20. Structured Scanner enabled behavior and parser/mapping/browser lifecycle remain equivalent to 4.1.

## Evidence ceiling

Until repository CI is green, `SOURCE_IMPLEMENTATION_PASS` is not established.

Until real WordPress/GF checks are executed, browser/admin/GF behavior remains `NOT_PROVEN` even if source/unit tests pass.

## Historical validation record

The complete pre-4.2 validation record from baseline `b1a52c975988bf60f473d4b843de057ed5bd0c06` is preserved byte-for-byte as `docs/VALIDATION_HISTORY_4.1.md`. It remains historical evidence and is not promoted to proof of 4.2 behavior.
