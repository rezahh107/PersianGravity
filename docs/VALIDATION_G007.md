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

## CI / browser evidence ceiling

Repository CI is expected to execute Composer validation, deterministic provider drift checks, WordPress Core 6.7.2/7.1 provider contracts, PHPUnit on PHP 8.2–8.5, WPCS, PHPCompatibility, scanner tests, and runtime-integrity guards against the exact PR Head.

The local preparation sandbox did not provide GNU `msgfmt`, a full disposable WordPress + licensed Gravity Forms/Gravity Perks browser runtime, or direct GitHub network access. Therefore real frontend rendering of File Upload Pro and visual browser proof of the Advanced Select caret are `NOT_EXECUTED` locally. Those claims must remain separate from source/contract proof unless an exact-package browser workflow executes them.
