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

## Historical 4.2 preparation evidence

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

The original preparation record did not bind a CI run. Reconciliation evidence below supersedes that pending statement for the integrated source tree; exact resulting-head CI is recorded in PR #8 after execution.

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

Source validation claims must be bound to the integrated tree and its exact resulting PR Head; an older branch run does not validate reconciliation.

Until real WordPress/GF checks are executed, browser/admin/GF behavior remains `NOT_PROVEN` even if source/unit tests pass.

## Historical validation record

The complete pre-4.2 validation record from baseline `b1a52c975988bf60f473d4b843de057ed5bd0c06` is preserved byte-for-byte as `docs/VALIDATION_HISTORY_4.1.md`. It remains historical evidence and is not promoted to proof of 4.2 behavior.

## PR #8 semantic reconciliation

Work unit: `WU-PR8-SEMANTIC-RECONCILIATION-01`. Normal merge of main `62ee8b2808640578bfdba0583342d29b7ef8a164` into PR #8 starting at `7aa31708c38deffd98cd99752a75fdf2297ee0cf`; both histories are preserved. Active identity remains **4.2.0**, with exactly six modules, the current module-state gates, bilingual admin/help and own-plugin GNU-gettext assets. Localization is immediate, load-free, cross-cutting infrastructure outside the module registry. No PR merge into main, tag or release is performed.

| Independent dimension | Status | Evidence / boundary |
| --- | --- | --- |
| RUNTIME_PROVIDER_STATUS | PARTIAL | Shared core implemented; empty production source scaffolds and no approved JS handles |
| PHP_PRECEDENCE_STATUS | AUTOMATED_PASS | Actual Core gettext/JIT/controller with synthetic provider and upstream catalogs, all three manifest domains |
| JS_PRECEDENCE_STATUS | CONTENT_COMPOSITION_PASS; PRODUCT_LIFECYCLE_NOT_PROVEN | Pure Jed composition/pass-through tests; no invented vendor handle is tested |
| DISCOVERY_STATUS | AUTOMATED_PASS | Provider-only JIT, upstream custom path retained, unmanaged/non-fa_IR pass-through |
| CATALOG_BUILD_STATUS | AUTOMATED_PASS_FOR_SCAFFOLDS_AND_SYNTHETIC_FIXTURES | Pinned PO/MO dependency, reproducible PHP/JSON, PO unchanged, no fake production output |
| TRANSLATION_CONTENT_COVERAGE | SOURCE_UNAVAILABLE_EMPTY_SCAFFOLD | Each product PO: 0 translated / 0 untranslated / 0 fuzzy; actual product totals and coverage unknown |
| VENDOR_SURFACE_DRIFT_STATUS | NOT_EXECUTED_PACKAGE_UNAVAILABLE | Internal metadata/artifact/manifest checks are separate from exact source verification |
| REAL_INTEGRATION_STATUS | NOT_PROVEN_REAL_INTEGRATION_ENVIRONMENT_UNAVAILABLE | No licensed WordPress + GF/Flow/View site/browser execution |

### Integrated validation execution

Executed on 2026-09-08 with PHP 8.3.6, Composer 2.8.12, Node and GNU gettext:

| Required local check | Result |
| --- | --- |
| `composer validate --strict` and locked `composer install` | PASS; dependency graph unchanged |
| GNU `msgfmt --check`, `msgcmp`, and exact generated/committed MO comparison | PASS; existing PO header warnings for missing revision date, translator and language team |
| Shipped PHP syntax, including `languages/` | PASS; 19 PHP files |
| `composer cs` | PASS; 19 shipped PHP files |
| `composer compat` | PASS; configured PHP 8.2+ baseline |
| Scanner JavaScript tests | PASS; 15 tests |
| `composer i18n:check` | PASS; dormant source, provenance and artifact state unchanged |
| Pinned WordPress 6.7.2 (`6abb46bec8b58abba32602738a785ba1c83f2a1c`) | PASS; 97 localization contract checks |
| Pinned WordPress 7.1 (`b998fef9238af183f9523b3df71618e6e57498b6`) | PASS; 97 localization contract checks |
| Combined `composer test` | PASS; 72 tests, 708 assertions |

The integrated suite preserves every current-main test and PR #8 localization test.
An additional bootstrap regression verifies resolver registration with all six module
states disabled and all optional vendors absent. The runtime-integrity test now covers
all admin PHP files and uninstall code as well as includes; the legacy hook ban remains.
The workflow contains the union of the own-catalog GNU-gettext gate and pinned Core/provider
checks, plus the existing quality gates and PHP 8.2/8.3/8.4/8.5 unit jobs.

The two Core checkouts are read-only dependencies. Their translation implementations run
with host/DB/cache services stubbed; no licensed product integration is implied. Exact
resulting-head GitHub Actions and ancestry evidence are recorded in PR #8 after execution.
A fresh independent review of that exact Head remains required before any owner merge decision.

## WU-PGR-GF-FLOW-REAL-SOURCE-ADMISSION-01

Required and observed base: `b35453e68a1e71063d8f4212bed4f62791ca856e`.

| Evidence | Gravity Forms | Gravity Flow |
| --- | --- | --- |
| Observed version | 3.1.1.1 | 3.1.0 |
| Package SHA-256 | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` |
| Vendor POT SHA-256 | `a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf` | `09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961` |
| Source status | `PACKAGE_INSPECTED_METADATA_ONLY` | `PACKAGE_INSPECTED_METADATA_ONLY` |
| Vendor authenticity | `NOT_PROVEN` | `NOT_PROVEN` |
| Source-backed / vendor keys | 4207 / 4208 | 1098 / 1098 |
| Missing from vendor POT | 0 | 0 |
| Vendor-POT-only stale | 1 | 0 |
| Context / plural differences | 0 / 0 | 0 / 0 |

GravityView 3.3.4 remains `PACKAGE_UNAVAILABLE` / `NOT_ADMITTED_PACKAGE_UNAVAILABLE`.

Static source extraction used a deterministic PHP tokenizer plus byte-level reconciliation for distributed JavaScript and plugin-header strings. Temporary WP-CLI 2.12.0 regeneration was attempted but not executed because the environment blocked the tool download; this is recorded as a tooling gap, not a passed check.

Gravity Forms source confirms `gravityforms`, its explicit `load_textdomain()` path, `load_plugin_textdomain()`, one native script-translation attachment, and multiple `wp_localize_script()` surfaces. Gravity Flow confirms `gravityflow`, parent/add-on translation lifecycle, no native `wp_set_script_translations()` call in the admitted target, and multiple PHP-localized classic-script surfaces. Script Modules are `NOT_PRESENT_IN_TARGET_SOURCE` for both.

The committed Surface Registry contains 13 source-backed surfaces: six Gravity Forms and seven Gravity Flow. GF message census: 4207 unique, 1759 classified, 2448 unclassified, 55 multi-surface. Flow: 1098 unique, 732 classified, 366 unclassified, 30 multi-surface. Classification is explicit source-path-rule driven with no hidden semantic fallback.

RTL/Bidi inspection is source-informed but visual execution remains `NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`; no production RTL patch is included. Terminology baseline contains 5 common provisional entries with both-product evidence, 2 Gravity Forms extensions, 3 Gravity Flow extensions, and 9 anti-glossary tokens; `Entry` is not hard-locked.

Metadata-only scope invariants are enforced by `tools/i18n/admission.php`, `tools/i18n/build.php`, `SourceAdmissionTest`, and `CatalogBuildTest`: production product translated `msgstr` = 0; product MO = absent; product `.l10n.php` = absent; product translation JSON = absent; active product JS handles = 0; production RTL patches = 0; vendor ZIP/source/full POT committed = 0; PGR version bump = none. `source_pot_sha256` remains null in metadata-only state because no full source POT is committed; the inspected POT identity is held in `vendor_pot_sha256`.

The exact PR-head CI for this work unit is the completion authority for repository tests, existing pinned WordPress localization contracts, admission consistency, Surface Registry validation, glossary evidence, and runtime integrity. A green static/source CI run still does not prove licensed browser/runtime RTL behavior.

## WU-005 — Gravity Flow Status bounded content admission

Required base: `main@8c8a7642fa8362775bc0730c6db3e00dede3eb3a`.

This Work Unit changes only Gravity Flow translation-content authority. Source admission remains separately pinned. Production revision-2 authority must contain exactly Inbox plus Status, with no third content record. The Status path rule is exactly `includes/pages/class-status.php`.

Deterministic preparation and independent reproduction establish these locked values before exact-final-Head CI:

| Evidence | Value |
| --- | --- |
| Reviewed Gravity Flow PO | `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9` |
| Source census | `1098` |
| Inbox admitted count | `255` |
| Inbox keyset | `446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a` |
| Inbox path fingerprint | `6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09` |
| Inbox translation fingerprint | `6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f` |
| Status admitted count | `43` |
| Status keyset | `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d` |
| Status path fingerprint | `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed` |
| Status translation fingerprint | `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7` |
| Inbox/Status identical overlap | `10` |
| Aggregate admitted unique count | `288` |
| Aggregate keyset | `58167ac415f0367a5a64dc098273b81bdca07a38641deaf75fd29ff4be42f363` |
| Aggregate translation fingerprint | `00c79c563019f01f68e0c03c9671e3587779cd6757fb830c53d1c2da4ecb4139` |
| Aggregate PO | `bc52c11763b536e44e977a1417d9096a1e3086f016b0a31206291340f411b757` |
| Generated MO | `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640` |
| Generated `.l10n.php` | `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44` |

The aggregate admitted count `288` is intentionally reported separately from the full Gravity Flow source census `1098`; no full-product coverage claim is implied. Gravity Flow native JS handles remain zero and no Gravity Flow translation JSON is generated. Provider-first/upstream-fallback semantics are unchanged.

`GravityFlowInboxContentAdmissionTest`, `GravityFlowStatusContentAdmissionTest`, the generic revision-2 content-admission regression suite, `composer i18n:check`, the PHP 8.2–8.5 CI matrix, and pinned WordPress Core localization contracts are the automated completion gates. Exact PASS/FAIL claims belong to the final PR Head only. Real WordPress + licensed Gravity Flow browser/UI validation remains `NOT_EXECUTED` unless separately evidenced.
