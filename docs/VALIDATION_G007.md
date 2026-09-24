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
- The Advanced Select compatibility adapter is unit-covered for exact GP Advanced Select `1.1.21` + `fa_IR` + RTL + registered `gp-advanced-select-tom-select`; version/locale/RTL/handle drift is fail-closed. Its final CSS targets `.ts-wrapper.gfield_select.plugin-change_listener.rtl` and uses logical inline padding for the single-select caret reserve.

## Runtime/source evidence for acceptance defects

### GP File Upload Pro

Exact source shows uploader labels are evaluated by PHP gettext and then sent to the browser through `wp_localize_script()`. Current WU008 exact-package browser evidence additionally proves those resolved values reach the authentic visible uploader; source-path and browser evidence remain recorded as separate evidence classes.

### GP Advanced Select

Exact package source/CSS establishes a direction-state mismatch: Tom Select uses an RTL wrapper state, while bundled CSS has `.ts-control.rtl` direction selectors and a right-pinned single-select caret. Exact browser evidence then exposed that the prior physical padding rule did not create the mirrored text reserve. The final adapter keeps the exact version/handle gate, narrows to the authentic `gfield_select + plugin-change_listener + rtl` wrapper, and uses logical inline padding without changing vendor bytes.

## Merged exact-Head CI evidence

The merged G-007 PR Head `5b28d2767189327879d994510b6ad0950de89ad3` received these successful repository runs before merge:

- CI `35222576697`: GNU gettext validation, PHP syntax, WPCS, PHPCompatibility, deterministic provider drift, runtime-integrity guards, current/minimum WordPress localization contracts, and PHPUnit across PHP 8.2–8.5.
- Artifact Install Smoke `35222576683`: production ZIP built, installed and activated in WordPress without Gravity Forms.
- G006 Gravity Forms Runtime `35222576704`: exact Gravity Forms 3.1.1.1 + Gravity Flow 3.1.0 runtime checks, provider/fallback assertions and real form rendering.
- WU008 Licensed Real Integration `35222576658`: preservation evidence for the existing exact Gravity Forms/Gravity Flow/GravityView products, including Chromium integration; this does not establish G-007 package UI behavior.

## Exact-package browser closure

The later G-009/WU008 lane now provisions the exact owner-supplied Gravity Perks 2.3.16, GP File Upload Pro 1.5.13 and GP Advanced Select 1.1.21 packages through their public-read Drive IDs. Before extraction it fail-closes on filename, ZIP signature/MIME, full archive integrity, exact byte size, full SHA-256, expected plugin main file and exact version. Licensed ZIPs and extracted source stay outside uploaded artifacts.

The exact installed source metadata probe and authentic Chromium runtime establish the previously missing browser layer:

- GP File Upload Pro 1.5.13 visibly renders `انتخاب فایل‌ها`, `فایل‌ها را اینجا رها کنید` and `یا` in fa_IR/RTL through the real PHP gettext → `wp_localize_script()` path; en_US/LTR remains native English. The authentic uploader remains usable at 1280x900 and 390x844, and `گزارش-ID-1234.txt` is preserved with `unicode-bidi:isolate`.
- GP Advanced Select 1.1.21 registers the exact `gp-advanced-select-tom-select` handle and renders the authentic Tom Select wrapper. In fa_IR/RTL the final bounded adapter places the caret on the left and computes `padding-inline-start: 12px` / `padding-inline-end: 36px` at both viewports. Search, typing, ArrowDown/Enter selection and focus remain usable. In en_US/LTR, PersianGravity emits no inline adapter CSS and native behavior remains intact.
- Gravity Perks family browser qualification is limited to these two admitted Perks and these exact versions/surfaces. No unlisted Perk or future-version compatibility is inferred.

This closes the old G-007 browser-evidence ceiling without changing the 127/127 localization authority or vendor files.
