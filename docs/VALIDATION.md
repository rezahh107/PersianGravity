# Persian Gravity Forms v4 Validation

Status date: `2026-08-31`

This document separates what has been automatically verified from what still requires a real WordPress + Gravity Forms environment.

## Automated CI

Current workflow:

`.github/workflows/ci.yml`

The current v4 workflow contains:

### Quality job

- Composer dependency installation
- syntax check against shipped PHP
- WordPress Coding Standards
- PHPCompatibility baseline
- runtime-integrity guard

### PHPUnit matrix

- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5

Observed state after the v4 refactor:

- refactor branch CI: `success`
- post-merge `main` CI: `success`

This supports claims about the configured automated checks only.

## Runtime-integrity guarantees checked by CI

The current CI guards against reintroducing major removed architecture, including:

- parallel `src/` runtime
- bundled general font assets
- obsolete refactor entrypoint
- historical `GFPersian_*` / `mellicart` / `ir_national_id` identifiers
- broad external `load_textdomain_mofile` interception
- SRWF-specific identifiers such as `registration_counter`

It also requires both canonical field classes to exist:

- `includes/fields/class-gf-field-national-id.php`
- `includes/fields/class-gf-field-jalali-date.php`

## Manual editor registration checkpoint

The v4 plugin has been observed loading both custom fields in the Gravity Forms Form Editor under Advanced Fields:

- `Jalali Date`
- `Iranian National ID`

This confirms plugin initialization and `GF_Fields` registration in that environment.

It does **not** by itself prove frontend rendering, validation, entry persistence, AJAX behavior, or every editor interaction.

## Required real-integration checks before declaring UI behavior fully verified

When field/rendering logic changes, exercise at least:

### Iranian National ID

1. Add the field in the Form Editor.
2. Render it on the frontend.
3. Submit a valid Iranian National ID.
4. Submit an invalid checksum.
5. Submit Persian digits.
6. Submit Arabic digits.
7. Confirm canonical ten-digit ASCII entry storage.
8. If No Duplicates is enabled, verify normalized duplicate behavior.
9. Verify typing-time normalization when enabled and verify server behavior when JavaScript is disabled.

### Jalali Date

1. Add the field in the Form Editor.
2. Exercise each supported presentation format that is intended for release.
3. Submit valid and invalid Jalali dates.
4. Verify leap/month boundaries.
5. Confirm canonical ASCII `YYYY-MM-DD` entry storage.
6. Confirm the stored value retains Jalali semantics.
7. Verify Entry Detail display.
8. Verify merge-tag output.
9. Verify conditional logic where used.
10. Verify AJAX and multi-page forms if those execution modes are in scope.

## Evidence vocabulary

Use these terms consistently:

- `CI_VERIFIED` — passed the configured repository automation.
- `UNIT_VERIFIED` — exercised by PHPUnit/pure-runtime tests.
- `MANUALLY_VERIFIED` — directly observed in a real environment with recorded steps.
- `INTEGRATION_VERIFIED` — exercised against real WordPress + Gravity Forms behavior for the stated surface.
- `NOT_PROVEN` — not yet demonstrated; this does not mean defective.

Do not promote unit/stub coverage to `INTEGRATION_VERIFIED`.

## Current validation summary

| Surface | Status |
| --- | --- |
| Shipped PHP syntax | CI_VERIFIED |
| WordPress Coding Standards | CI_VERIFIED |
| PHPCompatibility configured baseline | CI_VERIFIED |
| PHPUnit on PHP 8.2–8.5 | CI_VERIFIED |
| Runtime single-architecture guard | CI_VERIFIED |
| Custom fields visible in Gravity Forms editor | MANUALLY_VERIFIED |
| Full National ID frontend round-trip | NOT_PROVEN in repository automation |
| Full Jalali frontend round-trip | NOT_PROVEN in repository automation |
| Licensed real Gravity Forms integration suite | NOT_PROVEN |

Update this document when real integration evidence is produced. Do not erase a gap by assumption.
