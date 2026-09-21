# WU-008 — Licensed real-integration harness

This harness is the repository-owned, fail-closed CI path for real WordPress/browser evidence using the owner's intentionally public Google Drive package locations. It does not commit, upload, or expose licensed ZIP contents.

## Exact runtime inputs

| Product | Version | Public Drive file ID | Expected bytes | Required SHA-256 |
| --- | --- | --- | ---: | --- |
| Gravity Forms | `3.1.1.1` | `1mnCxBZVDL5qBALXh9CvxEMwIwg-YASCy` | `5300290` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` |
| Gravity Flow | `3.1.0` | `1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf` | `2603034` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` |
| GravityView | `3.3.4` | `16gDLYvZyA0SYvNl44d2O1n5C9nxFbkm1` | `7569755` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` |

PersianGravity is no longer fetched from the historical 4.2.0 commit. The workflow checks out the exact PR/workflow source SHA, builds the repository's deterministic production ZIP through `tools/build-production-package.sh`, installs that ZIP into the disposable WordPress site, and verifies the package's generated `release-manifest.json` source commit/tree against the checkout. Plugin version is derived from the same release authority used by the package builder; there is no separately synchronized WU008 PersianGravity version literal.

## Verification boundary

All three licensed downloads must pass before extraction. The package verifier requires expected filename, ZIP signature, ZIP MIME/type inspection, full archive integrity, exact byte size, exact SHA-256, expected plugin main file, and exact plugin version header. Any permission/login HTML response, corrupt archive, changed bytes, changed version, unavailable download, or wrong file fails the job. There is no substitute package or cache bypass.

The PersianGravity package is independently bound to exact checked-out commit/tree, derived version and built ZIP SHA-256. The runtime manifest records those identities together with the exact licensed vendor versions and package hashes.

The uploaded evidence is intentionally bounded to verification metadata, runtime manifests, source-discovery metadata, browser result JSON, screenshots, and the disposable server log. Package ZIPs and extracted vendor source are outside the artifact path and must never be uploaded.

## Canonical browser navigation and authentication

WordPress runtime URL APIs own application navigation for this harness. `runtime-manifest.json` publishes `page_url`, `login_url`, `admin_url`, `gravityflow_inbox_url`, and the authentic frontend `gravityflow_frontend_inbox_url`; browser tests consume those values rather than rebuilding WordPress application paths from `WU008_BASE_URL`.

The login step is a first-class diagnostics-gated browser operation. After login, the harness proves the authenticated WordPress admin boundary before product admin assertions run. GravityView's product URL is discovered from native authenticated admin navigation and the destination is verified as the exact `post_type=gravityview` surface.

## Diagnostics fail-closed contract

`browser-results.json` and the G-009 evidence files retain observed diagnostics. Every gated browser operation snapshots diagnostics before it starts and evaluates only newly observed diagnostics before recording its result. A new uncaught `pageerror`, or a failed request to the disposable WordPress runtime origin that was started by that operation, fails the operation. External request failures remain evidence but do not automatically become Persian/RTL compatibility defects.

The deterministic Node diagnostics test guards per-operation attribution so a suite-global synthetic failure cannot leave an affected operation falsely marked PASS.

## Real-runtime checks

The disposable lane uses WordPress `6.8.3`, PHP `8.2.33`, MariaDB `11.4.8`, Playwright `1.55.0`, Chromium, and the exact PersianGravity source Head under test. It preserves the original WU008 checks for:

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
- an `en_US` LTR control profile;
- representative `1280x900` and `390x844` browser geometry checks;
- computed direction/alignment/padding/overflow evidence;
- basic focus/keyboard/input checks where a deterministic control exists;
- an authentic frontend Gravity Flow Inbox shortcode request;
- a disposable disable/restore experiment for the observed `gform_admin` stylesheet, when present, to separate CSS causality from production repair authorization;
- strengthened GravityView native list-table/search-control evidence rather than page-load/`html dir` alone.

G-008 source discovery also reuses the exact installed vendor packages. `source-discovery.json` records only product/package identity plus normalized file/line references and candidate classifications; licensed source content itself is not uploaded. G-008 support/admission remains separate from source discovery.

A green workflow can support only the exact scenarios it actually ran. It does not prove future vendor versions, absent AG Grid states, unprovisioned Gravity Perks packages, exhaustive accessibility, or a production repair for `gform_admin`.
