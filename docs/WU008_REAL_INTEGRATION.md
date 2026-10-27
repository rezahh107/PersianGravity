# WU-008 — Licensed real-integration harness

This harness is the repository-owned, fail-closed CI path for real WordPress/browser evidence using the owner's intentionally public Google Drive package locations. It does not commit, upload, or expose licensed ZIP contents.

## Exact runtime inputs

| Product | Version | Public Drive file ID | Expected bytes | Required SHA-256 |
| --- | --- | --- | ---: | --- |
| Gravity Forms | `3.1.1.1` | `1mnCxBZVDL5qBALXh9CvxEMwIwg-YASCy` | `5300290` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` |
| Gravity Flow | manifest-owned | `tools/compatibility/gravityflow-package.json` | manifest-owned | manifest-owned |
| GravityView | `3.3.4` | `16gDLYvZyA0SYvNl44d2O1n5C9nxFbkm1` | `7569755` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` |
| Gravity Perks | `2.3.16` | `1s6bh8rcc-fGZcPWe2OzJQj48r7zaqeRG` | `156703` | `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c` |
| GP File Upload Pro | `1.5.13` | `1W7rVScg5N0X8_RWyWrc95NU2bUf_boWQ` | `3503920` | `fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce` |
| GP Advanced Select | `1.1.21` | `1_if3Eb9MctTgQaeYhGlSsyOsIJPIfJZ5` | `184531` | `d83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2` |

Gravity Flow qualification package identity is intentionally not duplicated here. `tools/compatibility/gravityflow-package.json` is the canonical operational authority for its version, Owner-supplied Drive file ID, expected filename/bytes, SHA-256 and plugin main file; qualification consumers must resolve those values through the repository helper and fail closed if the manifest is invalid.

PersianGravity is no longer fetched from the historical 4.2.0 commit. The workflow checks out the exact PR/workflow source SHA, builds the repository's deterministic production ZIP through `tools/build-production-package.sh`, installs that ZIP into the disposable WordPress site, and verifies the package's generated `release-manifest.json` source commit/tree against the checkout. Plugin version is derived from the same release authority used by the package builder; there is no separately synchronized WU008 PersianGravity version literal.

## Verification boundary

All six licensed downloads must pass before extraction. The package verifier requires expected filename, ZIP signature, ZIP MIME/type inspection, full archive integrity, exact byte size, exact SHA-256, expected plugin main file, and exact plugin version header. Any permission/login HTML response, corrupt archive, changed bytes, changed version, unavailable download, or wrong file fails the job. There is no substitute package or cache bypass.

The PersianGravity package is independently bound to exact checked-out commit/tree, derived version and built ZIP SHA-256. The runtime manifest records those identities together with the exact licensed vendor versions and package hashes.

The uploaded evidence is intentionally bounded to verification metadata, runtime manifests, source-discovery metadata, browser result JSON, screenshots, and the disposable server log. Package ZIPs and extracted vendor source are outside the artifact path and must never be uploaded.

## Canonical browser navigation and authentication

WordPress runtime URL APIs own application navigation for this harness. `runtime-manifest.json` publishes `page_url`, `login_url`, `admin_url`, `gravityflow_inbox_url`, the authentic frontend `gravityflow_frontend_inbox_url`, and the authentic Gravity Perks fixture `gravityperks_frontend_url`; browser tests consume those values rather than rebuilding WordPress application paths from `WU008_BASE_URL`.

The login step is a first-class diagnostics-gated browser operation. After login, the harness proves the authenticated WordPress admin boundary before product admin assertions run. GravityView's product URL is discovered from native authenticated admin navigation and the destination is verified as the exact `post_type=gravityview` surface.

## Diagnostics fail-closed contract

`browser-results.json` and the G-009 evidence files retain observed diagnostics. Every gated browser operation snapshots diagnostics before it starts and evaluates only newly observed diagnostics before recording its result. A new uncaught `pageerror`, or a failed request to the disposable WordPress runtime origin that was started by that operation, fails the operation. External request failures remain evidence but do not automatically become Persian/RTL compatibility defects.

One exact WordPress capability probe is classified separately: `/wp-admin/admin-ajax.php?action=wp-compression-test&test=yes` may be aborted by a browser navigation with exactly `net::ERR_ABORTED`. That observed navigation-abort shape remains recorded but is non-blocking. The same endpoint with another error, or another action with `ERR_ABORTED`, still blocks. Deterministic Node tests guard this narrow exception and the ordinary fail-closed behavior.

## Real-runtime checks

The disposable lane uses WordPress `6.8.3`, stable PHP `8.2.x` (the repository's PHP 8.2 minor-line compatibility target, strictly below `8.3.0`), MariaDB `11.4.8`, Playwright `1.55.0`, Chromium, and the exact PersianGravity source Head under test. `setup-php` is source-SHA-pinned but selects and updates the PHP `8.2` minor line; the shared CI guard checks the installed stable 8.2 minor line *before provisioning* and fails visibly on another minor, an older major, or a prerelease. The historical `8.2.34` is not a compatibility minimum required by the Owner, Composer or the licensed package manifests. The installed patch is printed in the Actions step summary and recorded in the real WordPress `runtime-manifest.json` as `php_version`. Earlier exact-Head qualification under `8.2.34` remains historical evidence only; this change does not retroactively prove PHP patch equivalence. The former hard-coded `8.2.34` equality check failed when GitHub-hosted `setup-php` installed `8.2.35`, despite remaining in the accepted PHP minor line. No production PHP, licensed package identity, WordPress version, behavioral assertion, or authentic browser/runtime requirement is relaxed. Any later patch receives its own WU008 exact-Head behavioral evidence. The lane preserves the original WU008 checks for:

- exact runtime versions and package identities;
- Persian provider resolution for admitted Gravity Forms, Gravity Flow, and GravityView strings;
- provider precedence plus deterministic upstream-only fallback through WordPress' real translation loader without modifying vendor files;
- authentic Gravity Forms frontend validation;
- manifest-derived authentication and authenticated WordPress admin identity;
- authentic Gravity Flow Inbox rendering;
- authentic GravityView admin navigation;
- per-operation fail-closed browser diagnostics.

G-009 extends the same lab rather than duplicating it. It runs:

- an exact `fa_IR` / WordPress RTL profile;
- an effective WordPress `en_US` / LTR control profile;
- representative `1280x900` and `390x844` browser geometry checks;
- computed direction/alignment/padding/overflow evidence;
- basic focus/keyboard/input checks where a deterministic control exists;
- an authentic frontend Gravity Flow Inbox shortcode request;
- a disposable disable/restore experiment for the observed `gform_admin` stylesheet, when present, to separate CSS causality from production repair authorization;
- strengthened GravityView native list-table/search-control evidence rather than page-load/`html dir` alone;
- exact anonymous-download/hash/version/root verification and activation for Gravity Perks 2.3.16, GP File Upload Pro 1.5.13 and GP Advanced Select 1.1.21;
- an exact-installed-source metadata probe that proves File Upload Pro's PHP gettext → `wp_localize_script()` path and Advanced Select's exact style handle/change-listener seam without exporting licensed source;
- an authentic File Upload Pro field with visible Persian labels, RTL/LTR geometry and mixed Persian/technical filename BiDi evidence;
- an authentic GP Advanced Select field with exact Tom Select wrapper/handle evidence, caret side/padding computation, and search/keyboard/selection/focus checks in RTL plus native LTR control.

The LTR control intentionally asserts WordPress' effective `get_locale()` and `is_rtl()` values rather than requiring a literal `WPLANG=en_US` option. WordPress represents its default English locale without that stored literal. The control removes the Persian locale option, proves effective `en_US`/LTR, runs the browser profile, and an `always()` restore step reactivates `fa_IR`/RTL and rechecks every plugin version so test state cannot leak across the lane.

G-008 source discovery also reuses the exact installed vendor packages. `source-discovery.json` records only product/package identity plus normalized file/line references and candidate classifications; licensed source content itself is not uploaded. G-008 support/admission remains separate from source discovery.

### GravityView G-008 production-admission lane

WU008 now exercises the production `PGR_GravityView_Jalali_Presentation_Adapter` for exact GravityView `3.3.4` `gravityview.date-created` and `gravityview.date-updated`. The earlier qualification MU prototype is explicitly absent before every production-admission request and is not used to close admission. The production adapter is loaded only through the ordinary `jalali_presentation` module gate.

Fixture construction uses GravityView's own `InspectorRoute::add_search_bar()` host API to persist a native `entry_date` Search Bar field instead of synthesizing internal widget metadata. Production browser/state evidence exercises Persian enabled, module-disabled, effective English/LTR, and forced exact-version-drift modes; authentic ascending/descending sorting for both system fields; the native `entry_date` filter path for `date_created`; raw DB/GFAPI/REST equality; stable row/sort-link structure; and untouched date-looking user text. The `date_updated` direct-request check remains intentionally limited to the unconfigured native no-op case and does not claim exhaustive optional search-field coverage.

The forced-version-drift control mutates only the disposable GravityView plugin header, verifies the observed header identity changed, and then requires the production adapter to return native output. The original package bytes are restored and checksum-verified before downstream regressions continue.

A passing `g008-gravityview-admission.json` closes production admission only for exact GravityView `3.3.4` and the two exact field-output seams. The shared evidence reconciler requires `RUNTIME_PROVEN + ADMITTED_VERIFIED`, adapter identity `PGR_GravityView_Jalali_Presentation_Adapter`, exact package/Head identity, metadata-only source provenance, module/locale/version fail-closed controls, unchanged machine semantics and absence of qualification-only diagnostic wrappers.

GravityView source inspection remains exact but ephemeral. The probe reads the verified installed GravityView/Gravity Forms source in memory and persists only metadata: package/Head identity, boolean contract results, relative file path, bounded line range, file SHA-256 and normalized contract IDs. It never serializes source lines, excerpts or search windows. A repository unit guard rejects any source-evidence shape outside that allowlist and independently falsifies each target field. WU008 runs the artifact privacy verifier before upload staging; if that guard fails, the raw working artifact directory is not uploaded.

A green workflow can support only the exact scenarios it actually ran. It does not prove future vendor versions, absent AG Grid states, unlisted/future Gravity Perks products, exhaustive accessibility, or a production repair for `gform_admin`.

### Native Confirmation baseline preserved from G-011 exploration (2026-10-27)

**Scope:** Evidence preservation and native Gravity Forms host testing only. The existing WU008 installation, licensed-package checksum gate, GFAPI, real WordPress database, and G-008 calendar engine **already existed on main**. This change does not load a G-011 adapter or add a runtime hook, module, dependency, translation, or calendar method.

The test-only file **tests/real-integration/gf-native-confirmation-baseline.php** adds one immediately reusable native oracle: nine independently GFAPI-persisted and re-read native Date-field source layouts (mdy/dmy/ymd, slash/dash/dot), their stored Entry values, authentic GFCommon::parse_date components, and the selected native GFFormDisplay::handle_confirmation output. A disposable read-only shared Merge Tag callback and a separate non-idempotent callback must each execute exactly once, with the output effect checked byte-for-byte. Form definitions and Entries must remain unchanged after rendering. Artifacts contain synthetic metadata, counts and output SHA-256s, not licensed source or personal data. This is a **native** baseline, not a simulated G-011 enabled case.

**Why persisted fixtures matter:** [PR #83](https://github.com/rezahh107/PersianGravity/pull/83) established that an in-memory GF_Field_Date dateFormat override can disagree with the metadata used by the licensed host Confirmation. The original dmy_dash source-format positive falsely expected success; independently persisted GFAPI Form/Entry fixtures rendered all nine intended native source layouts. See [Head 51ecd7b](https://github.com/rezahh107/PersianGravity/commit/51ecd7bec7a90677e5e37c7234164f5486611b97) and [licensed WU008 40480349167](https://github.com/rezahh107/PersianGravity/actions/runs/40480349167). This is a lesson about authoritative test fixtures, not a requirement to reuse PR #83 code.

**Ownership and normalization:** [WU008 40495017784](https://github.com/rezahh107/PersianGravity/actions/runs/40495017784) on [Head 727a3c84](https://github.com/rezahh107/PersianGravity/commit/727a3c84dd442247ff97c663c1de7b3f97c5a804) confirmed the mixed native Gregorian Date / pgr_jalali_date / Text ownership scenario on that PR's adapter, including preserved non-owned values and 1-vs-1 callbacks. Nine negative inputs yielded six preserved inputs reaching the host confirmation, while three were defaulted/normalized by GFAPI *before the tested adapter boundary*: missing date_updated acquired a valid value, bad-utc payment_date became null, and 2026-02-30 became 2026-03-02. These are host-specific observations, not general Gravity Forms promises. Invalid-requested but normalized-persisted data cannot prove raw-invalid adapter fallback.

**Callbacks and machine values:** [Head 963b07e](https://github.com/rezahh107/PersianGravity/commit/963b07e8a949f87eb37553a10b2456d3c042357c), [licensed WU008 40506762940](https://github.com/rezahh107/PersianGravity/actions/runs/40506762940), reproduced a real PR #83 production *post-native classifier* failure: extensionless receipt-{date_created:format:Y/m/d} and fragment-bearing ./archive/report#release-{date_created:format:Y/m/d} were calendar-converted despite being machine text; a separate Created= label legitimately converted. Four selected Text confirmation combinations were exercised in licensed host and Chromium, shared callbacks remained 1 vs 1, and Form/Entry definitions were unchanged after rendering. The failing acceptance step preserved a meaningful production regression signal; unrelated CI/G-008 succeeded. **This does not mean main has or activates the PR #83 adapter.**

**What remains only on PR #83:** PGR_GF_Confirmation_Jalali_Adapter, source/output span alignment, HTML anchor reconstruction, adapter-specific candidate tests, browser simulation hooks, and its 32-row method-specific harness. Importing them would implicitly adopt the complex production method rather than merely preserve evidence.

**Proposed future product direction, not an approved change:** An explicit opt-in Jalali Merge Tag may avoid ambiguous inference from native date-looking human versus machine tokens. The Owner has **not superseded** G-011 Revision 1.1. Its binding native-tag, Text/Page/Redirect, modifier, preservation, and evidence requirements remain in force until an explicit Owner product-contract decision. This PR implements no future G-011 method, does not close G-011, and does not authorize changing that lock.

The new baseline qualifies **GFAPI/PHP only** after exact-Head WU008 success. It does not qualify browser transport, Post-native Alignment, newer vendor versions, or future G-011 presentation.
