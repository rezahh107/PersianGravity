AGENTS.md — Persian Gravity Forms Refactor (v5.2)
================================================

Welcome!
This document defines the development and contribution rules for both human contributors and autonomous agents working on this plugin. Follow these instructions carefully for consistent, secure, and high-quality code.

1. Repository Snapshot
----------------------

- Plugin slug: `persian-gravityforms-refactor`
- Primary entrypoint: `persian-gravityforms-refactor.php`
- PHP namespace/prefix: `PGR_`
- Current plugin version: `1.0.0`
- Text domain: `persian-gravityforms-refactor`
- Domain Path: `/languages`
- Assets path: `assets/`
- Autoloader: SPL autoloader using `PGR_` prefix for all class names

2. Directory Expectations
-------------------------

| Path             | Purpose                                  | Notes                                   |
| ---------------- | ---------------------------------------- | --------------------------------------- |
| `includes/`      | Core PHP classes (`class-*.php`)         | Guard each file with `defined( 'ABSPATH' )` |
| `admin/`         | Admin UI and settings page logic         | Keep logic modular; enqueue only necessary assets |
| `assets/css/`    | Stylesheets (frontend/admin)             | Use `pgr-admin.css` and `pgr-frontend.css` separately |
| `assets/js/`     | Scripts (admin, forms, frontend)         | Wrap code in IIFE and enqueue via hooks |
| `languages/`     | `.pot`, `.po`, `.mo` translation files   | Must exist before shipping translations |
| `tests/`         | Optional PHPUnit or manual verification scripts | For refactor regression testing |

3. Local Environment & Tooling
------------------------------

- PHP ≥ 7.4 and WordPress ≥ 5.8 are required.
- Install dependencies with Composer: `composer install`
- Run code linting: `vendor/bin/phpcs --standard=WordPress --extensions=php,inc .`
- JavaScript/CSS linting is manual; document deviations in PRs.
- Generate translations if needed: `wp i18n make-pot . languages/persian-gravityforms-refactor.pot`

4. Coding Standards
-------------------

**PHP**

- Follow PSR-12 + WordPress Core coding standards.
- Use tabs for indentation, spaces for alignment.
- Always sanitize inputs and escape outputs (`esc_html`, `esc_attr`, etc).
- Wrap all database queries with `$wpdb->prepare()`.
- Use `current_user_can()` for admin capabilities.
- Yoda conditions required for literal comparisons.

**JavaScript**

- Use ES5 syntax for maximum admin compatibility.
- Wrap all code with `(function($){ 'use strict'; ... })(jQuery);`.
- Localize strings via `wp_localize_script()` instead of hard-coded Persian text.
- Avoid global variables; namespace your JS logic under `window.PGR`.

**CSS**

- Use `.pgr-` prefix for all plugin-related styles.
- Maintain RTL compatibility with `[dir="rtl"]` or logical properties.
- Keep selectors scoped—avoid global overrides in Gravity Forms UI.

5. Security Checklist
---------------------

- Block direct access at top of every PHP file.
- Use nonces for all form submissions and AJAX actions.
- Never trust user input; sanitize and validate all data.
- Escape all outputs and admin settings.
- Use `wp_verify_nonce()` in every state-changing request.
- No arbitrary file writes; use `WP_Filesystem` APIs if necessary.

6. Performance Guidelines
-------------------------

- Avoid unnecessary database queries or Gravity Forms hooks.
- Cache repetitive queries using transients where possible.
- Only enqueue assets on pages that actually use Gravity Forms.
- Defer or async-load frontend JS if compatible.
- Keep form manipulation lightweight and modular.

7. Internationalization & RTL
-----------------------------

- Wrap all user-facing strings with translation functions (`__()`, `_e()`).
- Text domain: `persian-gravityforms-refactor`.
- Keep RTL support consistent across form editor, entries, and frontend display.
- Use `is_rtl()` to apply direction-aware styles or behaviors.

8. Accessibility
----------------

- Add `aria-label` or `<label>` tags for every input.
- Avoid using color as the only visual cue.
- Ensure form field focus states are clear and keyboard-accessible.
- Test with screen readers for critical admin pages.

9. Asset & License Rules
------------------------

- Code is licensed under GPLv2+.
- Any third-party dependencies must be GPL-compatible.
- Include all custom JS/CSS in `assets/` folder with proper headers.
- Avoid bundling large libraries unless justified (e.g., performance or compatibility).

10. Testing Expectations
------------------------

- Every functional refactor or bug fix should include one of:
  - Manual verification notes (steps, screenshots).
  - Unit or integration test for core logic.
  - Confirmation of compatibility with latest Gravity Forms version.
  - Validation under `WP_DEBUG` and `SCRIPT_DEBUG` enabled.

11. Release Checklist
---------------------

- Bump version in plugin header and `PGR_VERSION` constant.
- Update `README.md` and changelog (if present).
- Regenerate translation file (`languages/persian-gravityforms-refactor.pot`).
- Test integration with latest Gravity Forms and WordPress.
- Tag version using semantic versioning (`major.minor.patch`).
- Validate that `.pot` and `.mo` files are properly loaded.

12. Git & PR Guidance
---------------------

- Use descriptive English commit messages.
- Split multi-feature work into smaller commits.
- Reference related issues or tickets in commit bodies.
- Include before/after screenshots if UI-related.
- Keep PRs focused on a single change type (refactor, fix, doc).

13. File-Specific Notes
-----------------------

| File / Path                               | Purpose                                                        | Notes                                       |
| ----------------------------------------- | -------------------------------------------------------------- | ------------------------------------------- |
| `includes/class-pgr-form-handler.php`     | Main logic for Persian date/time, number conversion, and field normalization | Ensure compatibility with GF field filters |
| `includes/class-pgr-admin.php`            | Handles admin options and plugin settings UI                   | Check capabilities and nonce                |
| `assets/css/pgr-admin.css`                | Minimal admin styling for settings page                        | Load only on plugin settings page           |
| `assets/js/pgr-admin.js`                  | Admin JS for AJAX saves or toggles                             | Use localized strings                       |
| `languages/`                              | Translations for text domain                                   | Must include `.pot` template before release |

14. Integration Standards with Gravity Forms
--------------------------------------------

**Hooks & Filters**

- Use Gravity Forms filters like `gform_pre_render`, `gform_pre_submission`, `gform_after_submission` responsibly.
- Always check if `class_exists( 'GFForms' )` before calling GF methods.
- Avoid direct modification of GF core files.

**Persian Support Layer**

- Handle date, time, and number conversion via dedicated helpers.
- Provide admin toggle for enabling/disabling Persian formatting per form.
- Use caching or memoization for repeated lookups.

15. RTL & Localization Optimization
-----------------------------------

- Test forms under both LTR and RTL themes.
- Use logical CSS properties (`margin-inline-start`, etc.).
- When injecting JS-based styling, ensure direction detection is dynamic (`document.dir`).

Adhering to this `AGENTS.md` ensures your refactor of Persian Gravity Forms remains secure, performant, and WordPress-compliant. If a rule doesn’t exist yet — document your rationale and extend this file responsibly.
