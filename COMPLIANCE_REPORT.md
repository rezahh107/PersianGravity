# Persian Gravity Forms Refactor — AGENTS.md Compliance Report

## 1. Repository Structure
| Area | Status | Notes | Suggestion |
| --- | --- | --- | --- |
| Entry file & metadata | ❌ | Legacy bootstrap lives in `index.php:3-45`; required `persian-gravityforms-refactor.php`, `PGR_VERSION`, slug `persian-gravityforms-refactor`, and text-domain header are missing. | Create `persian-gravityforms-refactor.php` at the root with the canonical header (version 1.0.0, text domain, domain path) and define `PGR_VERSION`, then require legacy bootstrap for backward compatibility. |
| Admin module directory | ❌ | Root listing has no `admin/` directory; admin UI logic is embedded in `includes/class-admin.php:5-131`, violating AGENTS §2 separation. | Move settings/dashboard controllers into `admin/` with loader classes and limit `includes/` to core runtime classes. |
| assets/css separation | ❌ | `assets/css/` only contains legacy files (`rtl-admin.css`, `font-admin.css`); mandated `pgr-admin.css` and `pgr-frontend.css` do not exist. | Split admin/frontend styles into `assets/css/pgr-admin.css` and `assets/css/pgr-frontend.css`, migrate relevant rules, and update enqueue handles. |
| assets/js modules | ❌ | `assets/js/` lacks the prescribed `pgr-admin.js` and frontend bundles; only legacy scripts like `national_id.js` are present. | Introduce `assets/js/pgr-admin.js`/`pgr-frontend.js` wrapped in AGENTS-compliant IIFEs and enqueue them conditionally. |
| Translation assets | ❌ | `languages/` holds third-party `.mo` trees but no `languages/persian-gravityforms-refactor.pot`. | Generate `languages/persian-gravityforms-refactor.pot` via `wp i18n make-pot` and add project-specific `.po/.mo` files. |
| Autoloader contract | ❌ | `composer.json:8-11` maps the PSR-4 namespace `PersianGravityForms\` instead of providing the required SPL autoloader for `PGR_` classes. | Replace the PSR-4 entry with an SPL autoloader (e.g., `includes/autoload.php`) that resolves all `PGR_` prefixed classes. |
| tests/ coverage | ⚠️ | `tests/` exists but only exercises `src/` services (e.g., `tests/Core/PluginTest.php`); legacy `includes/` classes remain untested. | Expand PHPUnit coverage or add manual verification notes for the refactored entrypoint and admin flows per AGENTS §10. |

## 2. Code Standards
| Area | Status | Notes | Suggestion |
| --- | --- | --- | --- |
| PHP class prefix | ❌ | Classes such as `GFPersian_Admin` in `includes/class-admin.php:5` and `PersianGravityForms\Core\Plugin` in `src/Core/Plugin.php:14` ignore the mandated `PGR_` prefix. | Rename classes and files to the `PGR_` prefix (`class-pgr-admin.php`, `PGR_Admin`, etc.) and align references accordingly. |
| Comparison style | ❌ | Multiple literal comparisons use non-Yoda order, e.g., `includes/class-admin.php:20` and `includes/class-core.php:83`. | Refactor conditionals to Yoda style (`'1' === $this->option(...)`) across PHP sources. |
| Indentation standard | ⚠️ | Modern files (`src/Core/Plugin.php:15-33`) use four-space indentation contrary to the “tabs for indentation” rule. | Reformat PHP files to tab indentation while keeping spaces for alignment. |
| Superglobal handling | ❌ | `includes/class-national-id.php:418-428` writes directly to `$_POST` without sanitizing or validating per AGENTS §4. | Introduce sanitized setters (e.g., `sanitize_text_field`) before mutating globals and document the behavior. |
| Output escaping | ⚠️ | Admin markup in `includes/class-admin.php:93-109` echoes URLs and attributes without `esc_url`/`esc_attr`. | Escape all dynamic output in admin views to satisfy WordPress coding standards. |
| JS module encapsulation | ❌ | `assets/js/national_id.js:1-34` defines globals instead of using an IIFE tied to `window.PGR`. | Wrap scripts in `(function ( $ ) { 'use strict'; ... })( jQuery );` and expose only the approved namespace. |
| CSS scoping | ❌ | Styles like `assets/css/rtl-admin.css:1-37` target broad selectors without the `.pgr-` prefix, risking conflicts. | Rework selectors to prepend `.pgr-` or scoped containers and ensure RTL support via logical properties. |

## 3. Security Checks
| Area | Status | Notes | Suggestion |
| --- | --- | --- | --- |
| Nonce enforcement | ❌ | No project PHP file calls `wp_verify_nonce`; settings handlers in `includes/class-settings.php:30-110` rely solely on GFAddOn defaults. | Add nonce fields to admin forms and verify them before processing state changes or AJAX actions. |
| Direct access guards | ❌ | Refactored classes under `src/` (e.g., `src/Core/Plugin.php:1-35`) lack the required `defined( 'ABSPATH' )` guard. | Prepend guard clauses or ensure autoloaded files exit early when accessed directly. |
| User input sanitization | ❌ | `includes/class-national-id.php:418-428` mutates `$_POST` values without sanitization, and `src/Services/CurrencyService.php:52-57` outputs formatted values without escaping. | Sanitize inputs via `sanitize_text_field`/`absint` and escape outputs with `esc_html` before rendering. |
| Database query hygiene | ⚠️ | `includes/class-core.php:73-84` executes `$wpdb->query()` with interpolated table names and fragments. | Wrap dynamic SQL in `$wpdb->prepare()` and centralize migration routines to prevent accidental injections. |

### Additional Domain Observations
- **Performance — ⚠️**: `includes/class-core.php:122-156` instantiates every feature class on each load regardless of settings, and no transients/cache are used for repeated GF lookups. Introduce lazy-loading and transient caching per AGENTS §6.
- **Internationalization — ❌**: Plugin header lacks `Text Domain`; numerous strings (`includes/class-core.php:32-110`, `includes/class-national-id.php:30-200`, `src/Services/NationalIdService.php:26-33`) are not wrapped in translation functions or use the legacy `persian-gravity-forms` domain. Standardize on `persian-gravityforms-refactor` and wrap all user-facing strings.
- **Accessibility — ❌**: Admin widgets (`includes/class-admin.php:91-109`) render images without `alt` text and inject markup via JS without focus management; additional form elements lack explicit labels beyond Gravity Forms defaults. Add `alt`/`aria` attributes and keyboard support per AGENTS §8.
- **Licensing — ⚠️**: `index.php` omits license headers, and `readme.txt:22-24` cites “GPL 2” instead of “GPLv2 or later” required by AGENTS §9. Update headers and ensure bundled font licenses are documented alongside GPL compatibility.
- **Release & versioning — ❌**: Version constants mismatch (`index.php:6` is `2.9.0-beta`, `readme.txt:22` is `2.8.0`); `GF_PERSIAN_VERSION` supersedes the required `PGR_VERSION`, and no release checklist artifacts are documented. Align versions, introduce `PGR_VERSION`, and document release steps.

## 4. Missing Components
- [ ] `persian-gravityforms-refactor.php` — canonical entry file with updated plugin header, `PGR_VERSION`, and autoloader bootstrap (root).
- [ ] `includes/autoload.php` — SPL autoloader mapping for `PGR_` classes to satisfy AGENTS §1.
- [ ] `admin/` module — dedicated controllers/views for settings with nonce checks and capability gating.
- [ ] `assets/css/pgr-admin.css` & `assets/css/pgr-frontend.css` — scoped styles using `.pgr-` prefixes and RTL-aware logical properties.
- [ ] `assets/js/pgr-admin.js` & `assets/js/pgr-frontend.js` — ES5 IIFEs under `window.PGR` handling admin/front interactions.
- [ ] `languages/persian-gravityforms-refactor.pot` — generated POT plus localized `.po/.mo` files.
- [ ] Nonce and sanitization layer — add nonce fields plus `wp_verify_nonce` handling in admin save routines (e.g., new `admin/class-pgr-admin.php`).
- [ ] Escaping & sanitization audit — refactor legacy output (e.g., `includes/class-admin.php`, `src/Services/CurrencyService.php`) to use `esc_*` helpers.

## 5. Overall Compliance Score: 22% ❌
| Domain | Status | Evidence | Required Action |
| --- | --- | --- | --- |
| Repository structure | ❌ | Missing canonical entry file, admin directory, and mandated asset layout (`index.php:3-45`, `assets/`, `languages/`). | Restructure directories and add required files per AGENTS §1-2. |
| Coding standards | ❌ | Legacy prefixes (`includes/class-admin.php:5`), non-Yoda comparisons, unsanitized globals, and unscoped CSS/JS. | Rename classes, enforce Yoda comparisons, sanitize/escape data, and scope assets under `.pgr-`/`window.PGR`. |
| Security | ❌ | No nonce verification, missing `ABSPATH` guards in `src/`, direct `$_POST` mutations. | Implement nonces, guards, sanitization, and prepared statements across admin flows. |
| Performance | ⚠️ | All services load eagerly (`includes/class-core.php:122-156`); no caching of repeated Gravity Forms data. | Introduce lazy-loading and transient caching around expensive hooks/queries. |
| Internationalization | ❌ | Absent text domain in header, untranslated strings, inconsistent domains (`src/Services/NationalIdService.php:26-33`). | Standardize on `persian-gravityforms-refactor` and wrap every string with translation helpers. |
| Accessibility | ❌ | Admin markup lacks `alt`/`aria` attributes (`includes/class-admin.php:103-107`) and JS inserts controls without focus cues. | Add accessible labels, `aria` attributes, and keyboard handling for injected UI. |
| Licensing | ⚠️ | License header missing; `readme.txt:22-24` cites GPL 2 without “or later”; bundled fonts require compatibility notice. | Update headers/readme to GPLv2+, document third-party licenses alongside assets. |
| Release & versioning | ❌ | Version mismatch (`index.php:6`, `readme.txt:22`), missing `PGR_VERSION`, no release checklist artifacts. | Sync version constants, add `PGR_VERSION`, document release steps, and regenerate translation files. |

## Summary Classification
### ✅ Full Compliance
- None — every audited domain requires remediation.

### ⚠️ Partial Compliance
- Direct-access guards exist in legacy `includes/*.php`, but new `src/` classes must adopt the same pattern.
- Some accessibility attributes are present in `includes/class-national-id.php:300-337`, yet coverage is incomplete.

### ❌ Missing
- Canonical entry file with `PGR_` bootstrap and version constant.
- Project-specific admin/frontend assets (`pgr-admin.css/js`, `pgr-frontend.css/js`).
- Nonce validation and sanitization for admin actions.
- Translation infrastructure (`persian-gravityforms-refactor.pot` and corrected text domains).
- Release/version governance aligning code, constants, and documentation.

---
**Recommended Next Steps:**
1. Stand up the new `persian-gravityforms-refactor.php` entry file, introduce `PGR_VERSION`, and align the autoloader with `PGR_`-prefixed classes.
2. Restructure admin assets and controllers into dedicated `admin/` modules, adding nonce verification and sanitized handlers.
3. Refactor JS/CSS assets into scoped `pgr-*` bundles and wrap scripts in `window.PGR` IIFEs.
4. Perform an i18n/accessibility sweep: wrap all strings with translation calls, fix text domains, add `alt`/`aria`, and regenerate `languages/persian-gravityforms-refactor.pot`.
5. Harmonize release metadata (version numbers, README, changelog) and document a repeatable release checklist per AGENTS §11.
