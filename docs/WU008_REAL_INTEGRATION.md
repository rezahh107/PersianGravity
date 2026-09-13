# WU-008 — Licensed real-integration harness

This harness is the repository-owned, fail-closed CI path for real WordPress/browser evidence using the owner's intentionally public Google Drive package locations. It does not commit, upload, or expose licensed ZIP contents.

## Exact runtime inputs

| Product | Version | Public Drive file ID | Expected bytes | Required SHA-256 |
| --- | --- | --- | ---: | --- |
| Gravity Forms | `3.1.1.1` | `1mnCxBZVDL5qBALXh9CvxEMwIwg-YASCy` | `5300290` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` |
| Gravity Flow | `3.1.0` | `1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf` | `2603034` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` |
| GravityView | `3.3.4` | `16gDLYvZyA0SYvNl44d2O1n5C9nxFbkm1` | `7569755` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` |

PersianGravity itself is installed into the disposable WordPress site from exact commit `a92a09cd5ef21456c56d95726e325b545d893592` and must report plugin version `4.2.0`.

## Verification boundary

All three downloads must pass before any licensed ZIP is extracted into WordPress. The package verifier requires the expected filename, ZIP signature, ZIP MIME/type inspection, full archive integrity, exact byte size, exact SHA-256, expected plugin main file, and exact plugin version header. Any permission/login HTML response, corrupt archive, changed bytes, changed version, unavailable download, or wrong file fails the job. There is no substitute package or cache bypass.

The uploaded evidence is intentionally bounded to verification metadata, runtime manifests, browser result JSON, screenshots, and the disposable server log. Package ZIPs and extracted vendor source are outside the artifact path and must never be uploaded.

## Canonical browser navigation and authentication

WordPress runtime URL APIs own application navigation for this harness. `runtime-manifest.json` publishes `page_url`, `login_url`, `admin_url`, and `gravityflow_inbox_url`; the Playwright browser must consume those values rather than rebuilding WordPress application paths from `WU008_BASE_URL`. `WU008_BASE_URL` remains only the disposable server/site-origin input used while provisioning the runtime.

The login step is a first-class diagnostics-gated browser operation. The manifest-derived login URL carries the manifest admin URL as its redirect target. After login, the harness proves that the browser landed inside that authenticated WordPress admin boundary before Flow or GravityView assertions run, without issuing a redundant second navigation that could abort in-flight admin requests. Each product admin check repeats that authenticated-admin predicate after navigation. GravityView's product URL is discovered from the authenticated admin navigation, including the exact plugin's runtime `gravityview_all_views` redirect entry when present, and the loaded destination is then verified as the `post_type=gravityview` surface.

## Diagnostics fail-closed contract

`browser-results.json` retains the full observed diagnostics stream. Every gated browser operation snapshots diagnostics before it starts and evaluates only the newly observed diagnostics before recording PASS. A new uncaught `pageerror`, or a failed request to the disposable WordPress runtime origin that was started by that operation, fails that same operation. Request start order is tracked so a request already in flight before the operation baseline remains in the full evidence but is not misattributed to the next navigation when the browser aborts the old document. Diagnostics that existed before the operation baseline are retained as evidence but do not retroactively fail the next operation. External request failures are retained but are not treated as WordPress-runtime blockers.

The deterministic Node diagnostics test guards this per-operation behavior so a suite-global synthetic failure cannot leave the affected operation falsely marked PASS.

## Real-runtime checks

The disposable lane uses WordPress `6.8.3`, PHP `8.2.33`, MariaDB `11.4.8`, `fa_IR`, Playwright `1.55.0`, and Chromium. It checks:

- exact runtime versions for all four plugins;
- Persian provider resolution for admitted Gravity Forms, Gravity Flow, and GravityView strings;
- provider precedence plus deterministic upstream-only fallback through WordPress' real translation loader without modifying vendor files;
- authentic Gravity Forms frontend validation in a real browser;
- manifest-derived authentication and authenticated WordPress admin identity;
- authentic Gravity Flow Inbox rendering in the real admin UI;
- an authentic GravityView admin surface loading without fatal/critical runtime failure;
- per-operation fail-closed browser diagnostics;
- RTL direction in frontend/admin runtime;
- screenshot and structured JSON evidence.

A green workflow can support the automatable real-browser/runtime portion of WU-008 for the exact pinned environment. It does not replace residual human/perceptual UI judgment, and it does not prove any package or commit other than the exact hashes/versions/commit recorded above.
