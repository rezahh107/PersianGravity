# WU008 admin UI evidence — verification-only

Baseline main SHA: 97b9fedb4b8d2743ce645c60a8a467fac0de6e76 (PersianGravity 4.10.0). This is a test-only extension of the existing licensed WU008 disposable WordPress and authenticated Chromium harness. The checked-out build is bound to the PR Head. No production PHP/CSS/JS, permissions, stored business data, module persistence, nonce policy, Gravity Forms contracts or goal definitions change.

## Exact capture scope

Licensed products remain those of WU008: Gravity Forms 3.1.1.1; Gravity Flow 3.1.0; GravityView 3.3.4; Gravity Perks 2.3.16; File Upload Pro 1.5.13; Advanced Select 1.1.21. WordPress is 6.8.3, PHP is an observed stable 8.2.x patch, Playwright is 1.55.0, and Chromium is recorded from the actual runner.

- Normal: Overview / seven module cards, Scanner Profiles list, blank new form, edit of a persisted synthetic long-label profile, built-in Sayad read-only inspect, Settings, System Status, and first-stage currency disable confirmation. Capture desktop 1280×900, mobile 390×844, focused 320×700 for Overview/edit/status/confirmation.
- Disabled visual warnings: Scanner runtime off, National ID off, Overview, Profiles warning, Settings notice, System Status. Capture desktop/mobile plus 320px Profiles and Settings.
- Both modes: genuine WordPress fa_IR/RTL and en_US/LTR, DPR 1, login via authentic WordPress, real plugin CSS, full-page JPEG with measured fonts, direction, document widths, overflow candidates, version, locale, exact Head and observed module state.
- Isolated negative control: fixed 800px-wide HTML element at 320px in a separate page, labeled as NOT PRODUCT, must be detected as overflow.

The fixture ID is wu008_ui_long_label. The real Scanner Profile Registry persists one custom profile with a long mixed Persian/English display name and one long output label in the disposable CI database only. No fixture overwrites preexisting records; no live production data is touched. Built-in inspection is read-only.

The currency disable warning uses a real first-stage authenticated admin POST and nonce. Source-defined usage is UNKNOWN, so the expected page presents the required second-step confirmation, but the second submission is intentionally NEVER made and the module remains enabled. The two temporary warning-state module toggles are restored to their pre-capture states by an EXIT trap, including on failure, together with site fa_IR locale.

## Evidence classes and limits

A screenshot establishes visual output at a particular viewport, not full behavioral correctness. VISUAL_FINDING is recorded when overflow candidates appear; CAPTURED_NO_DETECTED_OVERFLOW means only these measurements found none. The isolated 320px control must trigger. Effective font-family is recorded from computed CSS, not glyph-by-glyph font fallback. Profile save/delete/duplicate, second-stage disable, assistive-technology output and real production effects remain NOT VERIFIED.

Authentication, source/vendor identity, CSS, viewport/locale, missing routes, screenshot presence and per-operation browser diagnostics are fail-closed. Unexpected visual overflow is recorded for review rather than suppressed or treated as a blanket failure.

## Permanent delivery

Only after a successful exact-Head WU008 job, a second job with narrowly scoped write permission publishes approved synthetic screenshots, four JSON manifests and CONTACT_SHEET.md to a fresh evidence/wu008-admin-ui-<run_id> branch. It never checks out or executes tested PR code, treats the downloaded artifact as data, checks extensions, size and symlinks, and never uploads licensed ZIPs or private vendor source. The clickable Markdown contact sheet links to the full screenshots. The evidence-branch link appears in the Actions summary. Failed CI or publication means the required evidence is NOT DELIVERED; expiring workflow artifacts are not a substitute.


### Pre-publication completeness gate (PRI-FND-001)

The publisher validates the *downloaded* evidence as untrusted data, **before** creating an evidence branch or reaching `git push`. It does not run downloaded content or check out/execute the tested PR source with its write token. The trusted gate is inline in the publisher step and checks the GitHub-event exact PR Head, four manifest identities, the fixed 20+20+10+10 scenario matrix, 60 unique exact screenshot references, two detected isolated negative controls, a closed 67-file allowlist (no nested directories/symlinks), nonempty structurally valid JPEG marker/segment streams, and all 60 clickable contact-sheet thumbnail/target pairs. It accepts `VISUAL_FINDING` as captured evidence, not as design approval.

`tests/real-integration/admin-ui-evidence-publish-gate.test.mjs` extracts that **same inline gate** from the workflow, feeds an independent synthetic complete fixture plus disposable mutations, and asserts that each incomplete artifact returns a specific diagnostic and does not reach the simulated publication step. WU008 runs these assertions before the licensed runtime setup. JPEG structural validation does not claim exhaustive per-pixel visual fidelity; the authentic browser screenshots remain the source for visual review. A failed gate blocks publication, without invalidating genuinely complete historical captures.

Do not report browser PASS until the exact new run, produced screenshots, negative control and durable branch are actually inspected. No merge, release or deployment is authorized.
