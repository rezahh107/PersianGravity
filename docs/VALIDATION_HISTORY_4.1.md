# Persian Gravity Forms v4 Validation

Status date: `2026-09-07`

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

## PR #6 Scanner Profiles repair validation record

Required starting Head:

`5a2b022ace6692b844e0cbc4429a0cf0c62b01ed`

The starting source exposed validated runtime profiles from PHP but the shipped JavaScript core still used a Sayad-only hard-coded registry. The admin/editor assets referenced by PHP were absent, Scanner field mappings were positional, and the DOM adapter expected a segment-count message attribute that PHP did not render.

### First repair checkpoint

Implementation checkpoint Head:

`0250800a221435c2487bd1b91cd705841c2c6071`

Observed GitHub Actions run:

`34124652182`

Observed result:

`EXECUTED_FAIL`

The PHP 8.2, 8.3, 8.4, and 8.5 PHPUnit jobs passed. The quality job passed shipped-PHP syntax but failed WordPress Coding Standards on current-change formatting, so PHPCompatibility, Structured Scanner JavaScript tests, and runtime integrity were skipped in that run. Skipped checks are not PASS.

### Green source checkpoint after WPCS repair

Source checkpoint Head before documentation synchronization:

`52921adce1f30833f4415a0ac219d97d26956124`

Observed GitHub Actions run:

`34126455493`

Observed results:

| Check | Status | Evidence |
| --- | --- | --- |
| Composer dependency installation | AUTOMATED_PASS | quality job, run `34126455493` |
| Shipped PHP syntax | AUTOMATED_PASS | quality job, run `34126455493` |
| WordPress Coding Standards | AUTOMATED_PASS | quality job, run `34126455493` |
| PHPCompatibility configured baseline | AUTOMATED_PASS | quality job, run `34126455493` |
| Structured Scanner Node tests | AUTOMATED_PASS | quality job, run `34126455493` |
| Runtime integrity guard | AUTOMATED_PASS | quality job, run `34126455493` |
| PHPUnit PHP 8.2 | AUTOMATED_PASS | unit job, run `34126455493` |
| PHPUnit PHP 8.3 | AUTOMATED_PASS | unit job, run `34126455493` |
| PHPUnit PHP 8.4 | AUTOMATED_PASS | unit job, run `34126455493` |
| PHPUnit PHP 8.5 | AUTOMATED_PASS | unit job, run `34126455493` |

This checkpoint proves the repaired source surfaces passed the repository's complete automated CI. Documentation commits also trigger CI; the final implementation report must bind completion to the final exact PR Head rather than reusing this earlier checkpoint as final-Head proof.

### Scanner Profiles repair automated coverage

Focused PHP tests cover:

- built-in `sayad_v01` exact ordered outputs and read-only behavior;
- valid custom-profile persistence in the versioned option;
- profile-ID validation and built-in collision rejection;
- duplicate/invalid output-key rejection;
- zero-output and unsupported-parser rejection;
- disabled custom-profile resolution versus active runtime exclusion;
- malformed stored-option fail-safe behavior;
- immutable profile ID;
- immutable executable parser/output contract after creation;
- allowed label/enabled-state edits that do not change executable meaning;
- runtime profile serialization through `wp_add_inline_script()` using the test bootstrap stub;
- existing positional Sayad mappings remaining compatible;
- canonical key-based mappings for new/edited fields;
- invalid keyed mapping metadata producing a configuration/unavailable state rather than semantic rerouting.

Focused JavaScript tests cover:

- unchanged built-in Sayad parsing behavior;
- valid custom `segments_v1`/newline profile parsing;
- malformed/unavailable profile rejection;
- parser-definition rejection outside the bounded profile model;
- required versus optional output semantics;
- trim and digit-normalization flags;
- ordered output-key mapping for custom profiles;
- unknown output mapping rejection;
- missing/unsupported/self/duplicate target rejection before updates;
- atomic update-plan construction and preservation of prior successful state after a failed later scan;
- parser-driven Enter/idle/Tab/paste behavior.

### Shipped asset existence

The repaired branch ships the assets referenced by production PHP:

- `assets/css/pgr-admin.css`
- `assets/js/pgr-admin-profiles.js`
- `assets/css/pgr-scanner-editor.css`
- `assets/js/pgr-structured-scanner-core.js`
- `assets/js/pgr-structured-scanner.js`
- `assets/css/pgr-structured-scanner.css`

The profile-admin script is limited to output-row add/reorder/remove behavior and delete confirmation. It does not store or execute user-defined parser code.

## Historical Structured Scanner v0.1 validation record

Field type:

`pgr_structured_scanner`

Built-in structural profile:

`sayad_v01`

Ordered outputs:

1. `qr_version`
2. `owner_type`
3. `owner_identifier`
4. `iban`
5. `bank_branch`
6. `cheque_serial`
7. `sayad_id`

### Historical starting-head failure reproduced

Starting implementation Head:

`4cf1f60beada5eb1d4631c6a621a9043a72add4e`

Observed GitHub Actions run:

`34027704817`

Observed quality result:

`EXECUTED_FAIL`

The quality job reached WPCS and failed with exactly two `WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned` warnings in `includes/fields/class-gf-field-structured-scanner.php`. PHP unit jobs on PHP 8.2, 8.3, 8.4, and 8.5 passed in that run, but PHPCompatibility, Structured Scanner JavaScript tests, and runtime guard were skipped after WPCS failed. Skipped checks are not PASS.

### Historical source-repair automated checkpoint

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

The Structured Scanner Node suite contained 25 tests at that historical checkpoint. It preserved the original parser/mapping assertions and added deterministic parser-driven completion assertions for Enter, Tab, idle, paste, and delimiter-less collapsed input rejection.

## Structured Scanner automated contract coverage

### Capture/core behavior

Automated pure tests cover:

- valid synthetic built-in and custom segmented payloads;
- LF parsing;
- CRLF normalization;
- Persian digit normalization;
- Arabic digit normalization;
- leading-zero preservation through string processing;
- profile-controlled trimming and digit normalization;
- invalid segment-count rejection;
- empty required segment rejection;
- optional empty segment acceptance when declared optional;
- unknown/unavailable profile rejection;
- malformed runtime profile rejection;
- delimiter-less collapsed payload rejection;
- parser-driven Enter continuation for incomplete payloads;
- parser-driven Enter finalization only for a parser-valid payload;
- explicit Tab finalization of incomplete input;
- idle continuation for incomplete input;
- idle finalization for a parser-valid payload;
- explicit paste finalization while preserving parser failure semantics.

### Mapping/atomicity behavior

Automated pure tests cover:

- complete valid update-plan construction for built-in and custom profiles;
- missing target rejection;
- unsupported target rejection;
- duplicate destination rejection;
- self-target rejection;
- unknown output-key rejection;
- late mapping failure without a partial update set;
- second valid scan producing a full replacement plan;
- failed later scan not changing the state produced by a prior successful plan.

Supported mapped destination types remain exactly `text` and `hidden`.

### Mapping compatibility behavior

PHP field/registry tests cover that:

- existing `sayad_v01` positional mappings keep their original output meaning;
- new Form Editor mappings are persisted by output key;
- an existing custom profile cannot reorder, add, remove, rename, or alter `required` semantics of outputs under the same profile ID;
- parser flags/type/separator also cannot change under the same existing profile ID;
- label and enabled-state edits remain possible because they do not change mapping semantics;
- a malformed keyed mapping or unavailable profile fails into explicit configuration state.

This is a compatibility rule, not a silent migration. Existing forms are not rewritten merely because the profile registry changes.

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
- serializes validated runtime profiles before the Scanner core;
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

- Persian Gravity top-level admin pages render with their shipped scoped assets in a real WordPress admin;
- Scanner Profiles add/reorder/remove controls and delete confirmation behave correctly in the real browser;
- Structured Scanner appears and behaves correctly in the real Gravity Forms Form Editor;
- custom profile selection and key-based mappings persist through real Form Editor saves;
- an existing legacy positional mapping reloads without semantic drift in the real Form Editor;
- Text and Hidden mappings execute correctly in a real frontend form for built-in and custom profiles;
- LF batch scan works in a real browser control;
- CRLF batch scan works in a real browser control;
- clipboard paste preserves segmentation in the real browser;
- realistic keyboard-wedge Enter separators remain in-progress and terminal Enter finalizes after the required segments;
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
2. Confirm the Persian Gravity top-level menu exposes Overview, Scanner Profiles, Settings, and System Status and that unrelated admin pages do not receive the product assets.
3. Create one bounded custom `segments_v1` Scanner Profile, exercise Add output / Move up / Move down / Remove, save it, and reload it.
4. Verify an incompatible executable-contract edit is rejected rather than changing existing profile meaning; verify label/enabled edits remain allowed.
5. Add one `Structured Scanner` field and confirm field type `pgr_structured_scanner` is available in Advanced Fields.
6. Confirm profile `sayad_v01` and the enabled custom profile are selectable and save mappings to both a Single Line Text field and a Hidden field.
7. Reload the Form Editor and confirm `scanner_profile` and keyed `scanner_mappings` persist. Repeat with a fixture containing an existing positional Sayad mapping and confirm the same semantic output-to-field mapping remains visible.
8. Render the form in a browser and confirm the Scanner capture is a multiline `textarea` and has no Gravity Forms submission `name`.
9. Paste one valid seven-segment LF Sayad payload and verify all mapped fields update together.
10. Execute one valid custom-profile payload and verify its ordered outputs map to the keyed destinations.
11. Paste the equivalent CRLF payload and verify the same result.
12. Simulate a keyboard wedge and verify parser-driven Enter completion does not prematurely finalize incomplete input.
13. Enter a non-empty incomplete payload and press Tab; verify explicit invalid/finalization behavior and deterministic focus handling.
14. Pause after an incomplete partial scan for longer than the idle timeout; verify the partial multiline payload remains and no invalid error is raised solely because of the pause.
15. Submit an invalid explicit pasted payload; verify no mapped destination changes partially and the invalid message is non-empty.
16. Make the selected profile unavailable or mappings invalid and verify the configuration message is non-empty and no target writes occur.
17. Complete one valid scan, then a second different valid scan; verify mapped values are fully replaced.
18. After a successful scan, attempt a later invalid scan; verify prior mapped values remain unchanged.
19. Submit the form and inspect Entry Detail, export, and merge-tag behavior; verify the Scanner raw payload is absent while mapped destination values follow normal Gravity Forms persistence.
20. Render an otherwise comparable form without `pgr_structured_scanner`; verify Scanner JS/CSS assets are absent. Verify they are present on the Scanner form.
21. If the test form supports AJAX or multi-page rerender, trigger it and verify `gform/post_render` reinitializes the new Scanner DOM once without duplicate handlers or stale per-instance state.
22. Delete or archive only the disposable entries/forms created for this validation.

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
| Scanner built-in/custom pure parser/mapping/completion tests | AUTOMATED_PASS at PR #6 source checkpoint |
| Custom-profile persistence/validation/immutable-contract tests | AUTOMATED_PASS at PR #6 source checkpoint |
| Legacy positional + canonical keyed mapping compatibility tests | AUTOMATED_PASS at PR #6 source checkpoint |
| Scanner markup/non-persistence/bootstrap stub contract | AUTOMATED_PASS at PR #6 source checkpoint |
| Shipped PHP syntax | AUTOMATED_PASS at PR #6 source checkpoint |
| WordPress Coding Standards | AUTOMATED_PASS at PR #6 source checkpoint |
| PHPCompatibility configured baseline | AUTOMATED_PASS at PR #6 source checkpoint |
| Runtime single-architecture guard | AUTOMATED_PASS at PR #6 source checkpoint |
| PHPUnit on PHP 8.2–8.5 | AUTOMATED_PASS at PR #6 source checkpoint |
| Structured Scanner / Scanner Profiles real Form Editor/browser behavior | NOT_PROVEN |
| Structured Scanner real Entry/detail/export/merge non-persistence | NOT_PROVEN |
| Structured Scanner AJAX/multi-page `gform/post_render` behavior | NOT_PROVEN |
| `sayad_v01` bank/checksum/cross-bank authority | NOT_PROVEN and outside current structural contract |
| Nested Forms compatibility | NOT_APPLICABLE |

Update real-integration rows only from executed evidence. Do not erase a gap by assumption.
