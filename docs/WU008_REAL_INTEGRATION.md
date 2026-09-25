# WU-008 — Licensed real-integration harness

This harness is the repository-owned, fail-closed CI path for real WordPress/browser evidence using the owner's intentionally public Google Drive package locations. It does not commit, upload, or expose licensed ZIP contents.

## Exact runtime inputs

| Product | Version | Public Drive file ID | Expected bytes | Required SHA-256 |
| --- | --- | --- | ---: | --- |
| Gravity Forms | `3.1.1.1` | `1mnCxBZVDL5qBALXh9CvxEMwIwg-YASCy` | `5300290` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` |
| Gravity Flow | `3.1.0` | `1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf` | `2603034` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` |
| GravityView | `3.3.4` | `16gDLYvZyA0SYvNl44d2O1n5C9nxFbkm1` | `7569755` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` |
| Gravity Perks | `2.3.16` | `1s6bh8rcc-fGZcPWe2OzJQj48r7zaqeRG` | `156703` | `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c` |
| GP File Upload Pro | `1.5.13` | `1W7rVScg5N0X8_RWyWrc95NU2bUf_boWQ` | `3503920` | `fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce` |
| GP Advanced Select | `1.1.21` | `1_if3Eb9MctTgQaeYhGlSsyOsIJPIfJZ5` | `184531` | `d83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2` |

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

The disposable lane uses WordPress `6.8.3`, PHP `8.2.34`, MariaDB `11.4.8`, Playwright `1.55.0`, Chromium, and the exact PersianGravity source Head under test. The PHP patch pin moved from `8.2.33` to `8.2.34` after the hosted `setup-php` runtime stopped reproducing the older exact patch reliably. This is qualification-infrastructure drift only: the plugin production implementation was not changed by the pin. Current exact-Head evidence is generated under PHP `8.2.34`; no claim of complete semantic equivalence between the two PHP patch releases is made. It preserves the original WU008 checks for:

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
