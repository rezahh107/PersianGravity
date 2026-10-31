# G-013 — Real Vazir v1.5.0 comparison inside WU008

## Scope and status

This is a **test-laboratory change only**. The existing licensed WU008 checks run before any Vazir artifact is downloaded or activated. The preexisting four-manifest, 67-file administration baseline publisher gate is unchanged. No Vazir code/font is a PersianGravity runtime dependency or part of its installable ZIP. Release/merge are separately Owner-controlled.

The official external fixture is [Vazir v1.5.0](https://github.com/rezahh107/Vazir/releases/tag/v1.5.0), not a source checkout. CI downloads three release assets to its ephemeral package directory, verifies individually pinned SHA-256 values for the ZIP, manifest and checksum, verifies manifest publication identity, archive path/entrypoint/font layout, plugin header version and ZIP integrity, and rejects any mismatch **before extraction or activation**.

Expected official ZIP SHA-256: `95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5`.

## Exact profile order and evidence

1. Finish every existing WU008 baseline task, including the original administration UI evidence, responsive System Status and module-disable checks.
2. On the same disposable WU008 site, launch fresh authenticated Chromium sessions at fa_IR and en_US with Vazir absent (`BASELINE`). Store numerical computed styles, FontFace status, WOFF2 requests, page errors, document widths and the 1280px/390px screenshots.
3. Verify and install the actual ZIP, activate it through WP-CLI, assert plugin version 1.5.0 and saved real plugin settings. Record the settings safely (boolean switches, weights and selector exclusions only). Repeat the same browser cases with Vazir active (`WITH_VAZIR`). Explicitly verify actual loaded font faces, HTTP 200 WOFF2 responses and computed use of Vazirmatn for fa_IR.
4. Compare 36 paired observations, with separate classification for existing overflow, new WITH_VAZIR-only overflow, baseline-only overflow and no document-level overflow. Keep the six historical PR #96 System Status findings and later PR #97 fix distinct from font causality.
5. Deactivate and remove the ephemeral Vazir plugin and option records, restore fa_IR, and recheck that the PersianGravity production ZIP contains no Vazir/font artifact.

Nine authentic surfaces are exercised in each viewport: Overview, Settings, Scanner Profiles, System Status, Gravity Forms frontend, Gravity Flow Admin Inbox, Flow frontend Inbox, GravityView frontend and Gravity Perks frontend. GravityView editor portals and additional detached widget states remain outside this matrix.

Each run yields four profile JSON manifests, 32 JPEG screenshots, a comparison JSON, real settings JSON, release-verification JSON and a linked README in a **separate** `vazir-coexistence-evidence` directory. The publisher (without write credentials) checks the exact PR Head and separate allowlist, profile identities, real matrix, font release/settings and JPEG/link integrity before allowing the existing write-token publisher to add that directory. It never expands the original baseline 67-file inventory. Public licensed vendor ZIPs, Vazir ZIP and font payloads do not enter committed or published evidence.

## Evidence boundaries

Browser checks establish only what they observe in the exact disposable WordPress 6.8.3 + qualified Gravity package/runtime matrix, not all host themes/Gravity versions, live site settings, OS font substitution, speech output, or unexercised GravityView/Perks portals. Each profile is sequential with separate Chromium contexts and tested deactivation/cleanup; **it is not a separate WordPress database**. A real same-host comparison is useful but does not prove absolute database isolation. An unqualified font load or missing screen must fail or remain NOT_PROVEN, never become a substitute fake PASS.

Inspect the exact candidate CI and licensed WU008 run before merging. If CI or browser checks fail, diagnose the root cause and keep this G-013 PR open. Do not change production typography code, the Vazir repository, historical evidence refs or release workflows as a shortcut.

## G-013 evidence corrections and RTL verification boundary

The producer, comparator and credential-free publisher independently enforce four identities, exactly 18 unique cases per manifest (nine surfaces × 1280/390), 72 observations, 36 uniquely keyed paired comparisons and 32 JPEGs. Numeric widths, classifications, file links, release/settings identity and per-viewport font controls must agree; artifact consistency never proves that a browser was truly run. BASELINE and WITH_VAZIR must not be conflated. The 67-file original WU008 gate stays separate.

Font responses and page errors are captured between each page navigation and measurement, not assigned to the last page in a viewport. A missing 390px required font observation cannot borrow 1280px success. No additional vendor/product behaviors or release assurances are inferred.

R4 is diagnostic only: before keeping the exact authentic RTL assertion, record computed HTML/body direction, visible Admin Inbox candidate geometry/direction, page locale/lang, relevant stylesheet paths and a failure screenshot. The failed old run did not establish whether its Admin Inbox visible surface was RTL; the earlier G009 frontend Inbox observation is not a substitute. No vendor CSS override, exemption or production repair is authorized without new authentic evidence.
