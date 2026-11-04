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

## 2026-11-04 fresh-main continuation

This branch is reconstructed from canonical main after merged Scanner #112 rather than rebasing the old #100 WU008 workflow. The six G013 browser/validator/release scripts are reused and changed narrowly: root HTML direction is diagnostic, authentic visible AG Grid mode is explicitly PASS/FAIL/NOT_PROVEN, and a common database snapshot is required for independent fresh-profile restores. The original fixed WU008 four-manifest / 67-file baseline gate stays byte-for-byte intact; G013 has its own untrusted-data gate before publication. G013 claims are restricted to observed captures and cannot be promoted if native Grid mode fails. The historical PR #100 failure remains historical evidence.

**Explicit open scope:** Nine-surface captures alone do not demonstrate GravityView View-block editor detached React Select/Datepicker portals, all module enabled/disabled states, standalone Gravity Perks Settings, full GP File Upload Pro crop/rerender behavior, or assistive-device acceptance. Those remain NOT_PROVEN until individually driven; successful 72-observation publication cannot silently close the larger G-013 destination.


## November 4, 2026 — independently observed runtime qualification boundary

- [WU008 first attempt](https://github.com/rezahh107/PersianGravity/actions/runs/41808444088), Head `e3f7085be6fcc6928bb49a7870ef0a8da5a1f08d`: exact-version WordPress + Gravity runtime reached G-013; 18 authentic `BASELINE/fa_IR` browser observations captured. Native Gravity Flow AG Grid was `ag-ltr` in all four measured fa_IR admin/frontend 1280/390 contexts. `BASELINE/en_US` was **BLOCKED** by a stale in-request locale readback in the test harness. The preflight release/evidence negative controls passed. This is historical evidence for that Head, not current-Head acceptance.
- [WU008 second attempt](https://github.com/rezahh107/PersianGravity/actions/runs/41810201817), Head `eda2279296d757e6495ffac690ce751cb3b9aea9`: fresh-request WordPress locale readback demonstrated `fa_IR` and `en_US` site/user values correctly; 18 authentic BASELINE browser captures per locale completed. Native Grid fa_IR persisted as `ag-ltr`, whereas en_US was classified PASS. The run then failed with HTTP 404 on the first official Vazir Release asset. The `rezahh107/Vazir` source repository is **private**, so the GitHub release download URL cannot be used anonymously by the distinct PersianGravity Actions workflow. No official archive was installed; **WITH_VAZIR, actual bundled WOFF2 responses, 36 paired comparisons and authenticated publisher acceptance are NOT_PROVEN**. Independent publisher did not run (skipped after failure), which is not a publisher PASS.
- The test-only recovery is an Owner-approved **read-only** path to the **exact three official v1.5.0 release artifacts**, using the already approved private CI package provisioning mechanism if available, or explicitly authorized scoped access. Every byte must match the pinned published SHA-256, manifest/entrypoint/version and checksum before installation. Do **not** use GitHub source snapshots, reconstructed ZIPs, alternative font files, unauthenticated 404 retries as proof, public repository disclosure, or global token grants.
- Later G-013-only harness changes require stable ready native Grid (not merely a visible shell), completed GP File Upload Pro 1.5.13 progress state (not an early filename), same-page HTTP 200 WOFF2 + loaded FontFace + computed-family proof per viewport, and exact README contents with no untrusted external links. These *definitions* are **NOT runtime-proven** until their matching Head CI runs finish.
- Nine surfaces × 2 viewports × 4 profiles are a **minimal initial matrix**, not permission to silently drop G-013 accepted admin/frontend scope, independent GravityView block-editor/portal behavior, standalone Perks settings, crop/cancel/save/rerender, module-state and keyboard/assistive interactions. Unreached items remain `NOT_PROVEN`; production-site acceptance is separate.

Current review policy: leave this evidence-only G-013 PR **OPEN / DRAFT / UNMERGED**. G-014 and GPP remain out of scope, and the private release prerequisite does not authorize changing security posture or publishing a release.


## 2026-11-04 — Official Vazir repository visibility changed

The Owner made `rezahh107/Vazir` public after the earlier 404 failures. A fresh GitHub repository read confirms `visibility=public`; the published, non-draft/non-prerelease `v1.5.0` GitHub release lists the exact three official assets (`release-manifest.json`, `vazir-font-wp-1.5.0.zip`, and `vazir-font-wp-1.5.0.zip.sha256`). GitHub's release asset metadata reports the ZIP as 300139 bytes with digest `sha256:95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5`, matching the pinned published manifest. No release, repository visibility, credential, or publication setting was changed by this qualification PR.

The previous private-asset 404 is historical evidence, **not a currently asserted access-control blocker**. Public metadata is not proof that an anonymous download or install has succeeded. The authentic WU008 job must still download and validate actual released bytes before WITH_VAZIR browser runtime evidence, comparison, and independent publisher claims can be promoted. Native fa_IR Gravity Flow AG Grid `ag-ltr` in the baseline remains an observed finding from the earlier pinned Head, not a Vazir regression. Leave PR #113 open/draft/unmerged.
