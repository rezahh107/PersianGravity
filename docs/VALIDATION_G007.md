# G-007 Validation — Gravity Perks Family

Status vocabulary in this record distinguishes source/build evidence from a real licensed browser execution.

## Verified in implementation preparation

- Exact owner-supplied package SHA-256 matched for all 3/3 packages.
- Exact plugin versions/text domains were inspected from supplied package source.
- Deterministic literal-gettext source census established 83 `gravityperks`, 39 `gp-file-upload-pro`, and 5 `gp-advanced-select` canonical primary-domain identities.
- Vendor POT comparison found zero primary source-backed identities missing from POT for all three packages; plugin metadata-only POT keys and cross-domain calls remain excluded from primary authority.
- 127/127 admitted translations are non-empty, non-fuzzy, and token-safe under the repository review checks; review indexes record two semantic passes, three second-pass wording corrections, and zero unreviewed/rejected identities.
- Generated MO files were independently parsed and contained the exact 83/39/5 message sets; the File Upload Pro acceptance strings resolve to `انتخاب فایل‌ها`, `فایل‌ها را اینجا رها کنید`, and `یا`.
- New/modified PHP files passed `php -l`; modified release shell scripts passed `bash -n` in the preparation sandbox.
- The Advanced Select compatibility adapter was exercised against the repository WordPress stubs: it attaches exactly once for `fa_IR` + RTL + registered `gp-advanced-select-tom-select`, and the emitted CSS targets `.ts-wrapper.rtl`, moves the caret left, and mirrors the vendor `!important` caret padding from right to left.

## Runtime/source evidence for acceptance defects

### GP File Upload Pro

Exact source shows uploader labels are evaluated by PHP gettext and then sent to the browser through `wp_localize_script()`. This proves the existing PersianGravity PHP provider is on the runtime data path for the screenshot labels; it does not by itself prove a browser rendered the page.

### GP Advanced Select

Exact package source/CSS establishes a direction-state mismatch: Tom Select uses an RTL wrapper state, while bundled CSS has `.ts-control.rtl` direction selectors and a right-pinned single-select caret. The PersianGravity fix attaches to the exact package style handle and corrects that wrapper state without changing vendor bytes.

## Merged exact-Head CI evidence

The merged G-007 PR Head `5b28d2767189327879d994510b6ad0950de89ad3` received these successful repository runs before merge:

- CI `35222576697`: GNU gettext validation, PHP syntax, WPCS, PHPCompatibility, deterministic provider drift, runtime-integrity guards, current/minimum WordPress localization contracts, and PHPUnit across PHP 8.2–8.5.
- Artifact Install Smoke `35222576683`: production ZIP built, installed and activated in WordPress without Gravity Forms.
- G006 Gravity Forms Runtime `35222576704`: exact Gravity Forms 3.1.1.1 + Gravity Flow 3.1.0 runtime checks, provider/fallback assertions and real form rendering.
- WU008 Licensed Real Integration `35222576658`: preservation evidence for the existing exact Gravity Forms/Gravity Flow/GravityView products, including Chromium integration; this does not establish G-007 package UI behavior.

## Exact-package browser evidence ceiling

A later read-only verification rechecked the exact G-007 package bytes against the locked SHA-256 values, but the disposable executor environment could not instantiate the required exact-package WordPress browser runtime. No real G-007 target page was rendered and no DOM/computed-style evidence or screenshots were produced.

Therefore these two claims remain `NOT_VERIFIED`:

- GP File Upload Pro 1.5.13 visibly renders `انتخاب فایل‌ها`, `فایل‌ها را اینجا رها کنید`, and `یا` in the real uploader UI.
- GP Advanced Select 1.1.21 visibly places the Tom Select single-select caret and text-reservation padding correctly in the real licensed-package RTL UI.

The negative/control browser case for non-`fa_IR`/non-RTL execution also remains `NOT_VERIFIED` at the exact-package browser level. The automated adapter lifecycle test remains valid source/runtime-contract evidence, not a substitute for that browser case.

No stronger exact-package browser evidence was obtained during release-readiness preparation. These limitations must remain explicit in release documentation; source inspection, unit tests, existing CI and package filenames must not be promoted to browser PASS.
