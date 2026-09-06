# Persian Gravity Forms v4 Validation

Status date: `2026-09-06`

This document separates automated repository evidence from manual/real WordPress + Gravity Forms evidence. A green unit or CI result must not be promoted to browser integration proof.

## Evidence vocabulary

Use these terms consistently:

- `AUTOMATED_PASS` — the stated automated command/check executed and passed.
- `MANUAL_PASS` — the stated manual check executed in the named real environment and passed.
- `MANUAL_NOT_EXECUTED` — the manual check was not run.
- `NOT_PROVEN` — the behavior has not been demonstrated at the required authority boundary; this does not mean defective.
- `API_VERIFICATION_NOT_EXECUTED` — use only when a required external API contract verification was not performed.
- `EXECUTED_FAIL` — the stated check executed and failed.

Unit/stub tests are not equivalent to `MANUAL_PASS` or real Gravity Forms browser integration.

## Automated CI

Current workflow:

`.github/workflows/ci.yml`

The current workflow contains:

### Quality job

- Composer dependency installation
- syntax check against shipped PHP
- WordPress Coding Standards
- PHPCompatibility baseline
- Structured Scanner pure JavaScript tests
- runtime-integrity guard

### PHPUnit matrix

- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5

## Structured Scanner v0.1 validation record

Field type:

`pgr_structured_scanner`

Current structural profile:

`sayad_v01`

Ordered outputs:

1. `qr_version`
2. `owner_type`
3. `owner_identifier`
4. `iban`
5. `bank_branch`
6. `cheque_serial`
7. `sayad_id`

### Starting-head failure reproduced

Starting implementation Head:

`4cf1f60beada5eb1d4631c6a621a9043a72add4e`

Observed GitHub Actions run:

`34027704817`

Observed quality result:

`EXECUTED_FAIL`

The quality job reached WPCS and failed with exactly two `WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned` warnings in `includes/fields/class-gf-field-structured-scanner.php`. PHP unit jobs on PHP 8.2, 8.3, 8.4, and 8.5 passed in that run, but PHPCompatibility, Structured Scanner JavaScript tests, and runtime guard were skipped after WPCS failed. Skipped checks are not PASS.

### Source-repair automated checkpoint

Repair checkpoint Head before documentation-only synchronization:

`dbd6a51f31a677b3556417cba3858f8c0407bcf7`

Observed GitHub Actions run:

`34029180490`

Observed results:

| Check | Status | Evidence |
| --- | --- | --- |
| Shipped PHP syntax | AUTOMATED_PASS | quality job, run `34029180490` |
| WordPress Coding Standards | AUTOMATED_PASS | quality job, run `34029180490` |
| PHPCompatibility configured baseline | AUTOMATED_PASS | quality job, run `34029180490` |
| Structured Scanner Node tests | AUTOMATED_PASS | quality job, run `34029180490` |
| Runtime integrity guard | AUTOMATED_PASS | quality job, run `34029180490` |
| PHPUnit PHP 8.2 | AUTOMATED_PASS | unit job, run `34029180490` |
| PHPUnit PHP 8.3 | AUTOMATED_PASS | unit job, run `34029180490` |
| PHPUnit PHP 8.4 | AUTOMATED_PASS | unit job, run `34029180490` |
| PHPUnit PHP 8.5 | AUTOMATED_PASS | unit job, run `34029180490` |

The Structured Scanner Node suite contains 25 tests at this checkpoint. It preserves the original parser/mapping assertions and adds deterministic parser-driven completion assertions for Enter, Tab, idle, paste, and delimiter-less collapsed input rejection.

Documentation commits also trigger repository CI. The final implementation report must bind the final CI observation to the final exact PR Head; this document intentionally does not treat an earlier checkpoint as proof for a later Head.

## Structured Scanner automated contract coverage

### Capture/core behavior

Automated pure tests cover:

- valid synthetic seven-segment payload;
- LF parsing;
- CRLF normalization;
- Persian digit normalization;
- Arabic digit normalization;
- leading-zero preservation through string processing;
- outer/per-segment trimming behavior;
- six/eight segment rejection;
- empty required segment rejection;
- unknown profile rejection;
- delimiter-less collapsed payload rejection;
- parser-driven Enter continuation for one through six segments;
- parser-driven Enter finalization only for a parser-valid payload;
- explicit Tab finalization of incomplete input;
- idle continuation for incomplete input;
- idle finalization for a parser-valid payload;
- explicit paste finalization while preserving parser failure semantics.

### Mapping/atomicity behavior

Automated pure tests cover:

- complete valid update-plan construction;
- missing target rejection;
- unsupported target rejection;
- duplicate destination rejection;
- self-target rejection;
- late mapping failure without a partial update set;
- second valid scan producing a full replacement plan;
- failed later scan not changing the state produced by a prior successful plan.

Supported mapped destination types remain exactly `text` and `hidden`.

### Markup/non-persistence behavior

PHPUnit covers that the Structured Scanner:

- is `displayOnly`;
- uses profile `sayad_v01` by default;
- renders a `textarea` capture;
- retains `data-pgr-scanner-capture="1"`;
- does not render the old Scanner `input type="text"` capture;
- has no Gravity Forms `name="input_<scanner-id>"` on the raw capture;
- returns no raw value from save-entry, entry-detail, entry-list, merge-tag, or export surfaces;
- loads Scanner assets only for forms containing `pgr_structured_scanner`;
- preserves the existing National ID conditional asset behavior.

These assertions establish source/stub contracts. They do not prove what a licensed real Gravity Forms installation persists or renders in a browser.

## Real WordPress + Gravity Forms browser validation

Required test ID:

`T-REAL-GF-BROWSER-01`

Execution status for this work unit:

`MANUAL_NOT_EXECUTED`

Reason:

`NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`

A compatible real WordPress + licensed Gravity Forms browser environment is not available through the implementation tools used for this repository work unit. No simulated DOM, PHPUnit stub, source inspection, or reasoning result is promoted as a substitute.

Therefore the following integration claims remain `NOT_PROVEN`:

- Structured Scanner appears and behaves correctly in the real Gravity Forms Form Editor;
- profile/mapping controls persist through real Form Editor saves;
- Text and Hidden mappings execute correctly in a real frontend form;
- LF batch scan works in a real browser control;
- CRLF batch scan works in a real browser control;
- clipboard paste preserves segmentation in the real browser;
- realistic keyboard-wedge Enter separators remain in-progress and terminal Enter finalizes after seven segments;
- Tab finalization is deterministic in the real browser;
- invalid explicit payload does not partially write mapped targets in a real form;
- second valid scan replaces mapped values in the real form;
- failed later scan leaves the prior successful mapped state unchanged in the real form;
- raw payload is absent from actual Gravity Forms Entry persistence/detail/export/merge surfaces;
- Scanner assets are absent/present in actual generated pages according to field presence;
- `gform/post_render` reinitialization works through an actual AJAX or multi-page rerender without duplicate handlers/stale state.

### Deterministic checklist for the next real-environment run

Use a disposable test form and disposable entries only:

1. Install/activate the exact plugin build being validated with WordPress at or above 6.7 and Gravity Forms at or above 3.0.
2. Add one `Structured Scanner` field and confirm field type `pgr_structured_scanner` is available in Advanced Fields.
3. Confirm profile `sayad_v01` is available and save mappings to both a Single Line Text field and a Hidden field.
4. Reload the Form Editor and confirm `scanner_profile` and `scanner_mappings` persist.
5. Render the form in a browser and confirm the Scanner capture is a multiline `textarea` and has no Gravity Forms submission `name`.
6. Paste one valid seven-segment LF payload and verify all mapped fields update together.
7. Paste the equivalent CRLF payload and verify the same result.
8. Simulate a keyboard wedge: type/scan segments 1 through 6 with Enter separators and verify no invalid/final processing occurs; after segment 7, terminal Enter must process the complete payload without adding another separator.
9. Enter a non-empty incomplete payload and press Tab; verify explicit invalid/finalization behavior and deterministic focus handling.
10. Pause after an incomplete partial scan for longer than the idle timeout; verify the partial multiline payload remains and no invalid error is raised solely because of the pause.
11. Enter a complete valid payload without terminal Enter/Tab and allow the idle timeout; verify the valid payload may finalize.
12. Submit an invalid explicit pasted payload; verify no mapped destination changes partially.
13. Complete one valid scan, then a second different valid scan; verify mapped values are fully replaced.
14. After a successful scan, attempt a later invalid scan; verify prior mapped values remain unchanged.
15. Submit the form and inspect Entry Detail, export, and merge-tag behavior; verify the Scanner raw payload is absent while mapped destination values follow normal Gravity Forms persistence.
16. Render an otherwise comparable form without `pgr_structured_scanner`; verify Scanner JS/CSS assets are absent. Verify they are present on the Scanner form.
17. If the test form supports AJAX or multi-page rerender, trigger it and verify `gform/post_render` reinitializes the new Scanner DOM once without duplicate handlers or stale per-instance state.
18. Delete or archive only the disposable entries/forms created for this validation.

Record the WordPress version, Gravity Forms version, browser/version, exact plugin Head SHA, form mode (standard/AJAX/multi-page), and observed result for each step before changing any `NOT_PROVEN` status to `MANUAL_PASS`.

## Nested Forms

Conditional test ID:

`T-NESTED-FORMS-01`

Current status:

`NOT_APPLICABLE`

The current Structured Scanner v0.1 repository contract does not claim Gravity Forms Nested Forms compatibility, and no compatible Nested Forms environment is part of this work unit. Do not infer compatibility from generic `gform/post_render` handling.

## Runtime-integrity guarantees checked by CI

The current CI guards against reintroducing major removed architecture, including:

- parallel `src/` runtime;
- bundled general font assets;
- obsolete refactor entrypoint;
- historical `GFPersian_*` / `mellicart` / `ir_national_id` identifiers;
- broad external `load_textdomain_mofile` interception;
- SRWF-specific identifiers such as `registration_counter`.

It also requires the canonical field/profile files to exist:

- `includes/fields/class-gf-field-national-id.php`
- `includes/fields/class-gf-field-jalali-date.php`
- `includes/fields/class-gf-field-structured-scanner.php`
- `includes/class-pgr-scanner-profile-registry.php`

## Existing manual editor checkpoint for pre-Scanner fields

The v4 plugin had previously been observed loading these pre-Scanner custom fields in the Gravity Forms Form Editor under Advanced Fields:

- `Jalali Date`
- `Iranian National ID`

That historical checkpoint does not prove Structured Scanner integration and must not be reused as Scanner evidence.

## Current validation summary

| Surface | Status |
| --- | --- |
| Scanner pure parser/mapping/completion tests | AUTOMATED_PASS at source-repair checkpoint |
| Scanner markup/non-persistence stub contract | AUTOMATED_PASS at source-repair checkpoint |
| WPCS after narrow alignment repair | AUTOMATED_PASS at source-repair checkpoint |
| PHPCompatibility configured baseline | AUTOMATED_PASS at source-repair checkpoint |
| Runtime single-architecture guard | AUTOMATED_PASS at source-repair checkpoint |
| PHPUnit on PHP 8.2–8.5 | AUTOMATED_PASS at source-repair checkpoint |
| Structured Scanner real Form Editor/browser behavior | NOT_PROVEN |
| Structured Scanner real Entry/detail/export/merge non-persistence | NOT_PROVEN |
| Structured Scanner AJAX/multi-page `gform/post_render` behavior | NOT_PROVEN |
| `sayad_v01` bank/checksum/cross-bank authority | NOT_PROVEN and outside current structural contract |
| Nested Forms compatibility | NOT_APPLICABLE |

Update real-integration rows only from executed evidence. Do not erase a gap by assumption.
