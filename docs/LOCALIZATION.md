# Gravity ecosystem localization provider foundation

Original foundation work unit: `WU-PGR-GRAVITY-LOCALIZATION-PROVIDER-02`.
Original foundation base: `b1a52c975988bf60f473d4b843de057ed5bd0c06`.
Reconciled with main `62ee8b2808640578bfdba0583342d29b7ef8a164` through `WU-PR8-SEMANTIC-RECONCILIATION-01`; active repository identity is **4.2.0**. The six-module manager, module-state class-load gates, bilingual admin/help and own-plugin GNU-gettext assets remain authoritative. Localization is cross-cutting infrastructure outside `PGR_Module_Registry`, independent of every module toggle.

Decision C is closed: **Shared Core + Declarative Product Manifests + Bounded Adapters**.

## Product evidence and honest activation boundary

| Product | Target / observed | Source status | Package SHA-256 | Vendor POT SHA-256 | Active JS handles |
| --- | --- | --- | --- | --- | --- |
| Gravity Forms | 3.1.1.1 / 3.1.1.1 | `PACKAGE_INSPECTED_METADATA_ONLY` + bounded content | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf` | 0 |
| Gravity Flow | 3.1.0 / 3.1.0 | `PACKAGE_INSPECTED_METADATA_ONLY` + bounded content | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | `09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961` | 0 |
| GravityView | 3.3.4 / 3.3.4 | `PACKAGE_INSPECTED_METADATA_ONLY` / content dormant | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` | `null` (vendor POT absent) | 0 |

For Gravity Forms, Gravity Flow and GravityView, `PROJECT_SOURCE_AUTHORITY=OWNER_SUPPLIED_EXACT_PACKAGE`, `VENDOR_AUTHENTICITY=NOT_PROVEN`, and source admission remains separately evidenced. Exact package bytes were inspected and pinned, but vendor authenticity is not claimed. Vendor ZIPs, extracted vendor source, full vendor POT/message corpora, and the full reviewed Persian baselines are not committed. GravityView's exact package contains no vendor POT, so `vendor_pot_sha256` remains `null`; no POT hash is inferred or fabricated.

`SOURCE VERIFIED != TRANSLATION CONTENT ADMITTED != JS SURFACE ACTIVATED`. Gravity Forms now has revision-2 production content authority for exactly one bounded surface: `gravityforms::frontend_runtime::shortcode:gravityform`, source-path rule exactly `form_display.php`, 41 identities from the independent 4207-message source census. The WU-003 reviewed full baseline SHA-256 `2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1` remains reviewed-input provenance; the final sparse provider content contains exactly seven explicit PR #15 semantic corrections within the same identity/keyset/path scope and is not represented as an untouched byte-for-byte subset of that baseline. Gravity Flow retains revision-2 authority for exactly Inbox and Status: 255 Inbox identities, 43 Status identities, 10 identical overlaps, and a deterministic 288-identity aggregate from a separate 1098-message source census. GravityView is source-admitted only: its primary `gk-gravityview` census contains 3127 canonical identities, 127 contextual identities, 42 plural identities and 3931 source references; production translated count remains zero, runtime activated handles remain zero, and no GravityView provider MO, `.l10n.php` or translation JSON is admitted. `gk-query-filters` remains a separate bounded Composer dependency/domain that PersianGravity does not manifest or runtime-activate. `source_pot_sha256` remains `null` for metadata-only admission because no full `source.pot` is committed; for Gravity Forms/Flow `vendor_pot_sha256` records inspected vendor POT bytes, while GravityView has no vendor POT at all.

The PR #14 Status sparse-PO header repair corrects only malformed gettext header escaping. The unchanged strict loader must parse `Language: fa_IR`, `X-Domain: gravityflow`, and `Plural-Forms: nplurals=2; plural=(n > 1);`. Current repaired raw SHA-256 values are Status `c160913904bf991b29274bfbda3615a1a12049c9b97b81f7b687ac9e5d0fd718` and aggregate provider PO `838c2409841c099d17d475e6290ec8fff49ad237331cd527f3e25769d2162267`; semantic identities, translations, content fingerprints, JS authority and runtime architecture are unchanged.

### Source ↔ POT consistency

The exact packages were inspected with deterministic source extraction plus byte-level reconciliation where required. A temporary WP-CLI 2.12.0 source-POT regeneration was attempted for the earlier Gravity Forms/Flow admission but could not execute because the environment blocked tool download; that tooling gap remains explicitly separate from the source-backed consistency result.

- Gravity Forms: vendor POT 4208 unique keys; 4207 source-backed keys; 0 missing from vendor POT; 1 vendor-POT-only stale test key whose referenced test path is absent from the supplied package; 22 context messages; 16 plural messages; context/plural differences 0.
- Gravity Flow: vendor POT 1098 unique keys; 1098 source-backed keys; 0 missing; 0 stale; 3 context messages; 6 plural messages; context/plural differences 0.
- GravityView: vendor POT absent; deterministic literal-gettext extraction yields 3127 `gk-gravityview` canonical identities, 127 contexts, 42 plurals and 3931 source references. Source consistency is bound to the committed keyset/reference fingerprints rather than a fabricated POT hash. One non-literal gettext call is recorded separately instead of being guessed into the canonical keyset.

Canonical identity is SHA-256 over `msgctxt + U+001F + msgid + U+001F + msgid_plural`. Aggregate keyset/reference hashes and the minimum individual evidence identities required by terminology validation live in `tools/i18n/admission/baseline.json`; GravityView's reproducible domain/source evidence is additionally recorded in `tools/i18n/admission/gravityview-source-evidence.json`.

## WordPress implementation authority

The executable contract tests load the actual files from WordPress **6.7.2**,
commit `6abb46bec8b58abba32602738a785ba1c83f2a1c`, and WordPress **7.1**,
commit `b998fef9238af183f9523b3df71618e6e57498b6`:

- [l10n.php](https://github.com/WordPress/WordPress/blob/6abb46bec8b58abba32602738a785ba1c83f2a1c/wp-includes/l10n.php)
- [WP_Textdomain_Registry](https://github.com/WordPress/WordPress/blob/6abb46bec8b58abba32602738a785ba1c83f2a1c/wp-includes/class-wp-textdomain-registry.php)
- [WP_Translation_Controller](https://github.com/WordPress/WordPress/blob/6abb46bec8b58abba32602738a785ba1c83f2a1c/wp-includes/l10n/class-wp-translation-controller.php)
- [WP_Translation_File_PHP](https://github.com/WordPress/WordPress/blob/6abb46bec8b58abba32602738a785ba1c83f2a1c/wp-includes/l10n/class-wp-translation-file-php.php)

Current official API references were also checked:
[discovery](https://developer.wordpress.org/reference/hooks/lang_dir_for_domain/),
[PHP file loading](https://developer.wordpress.org/reference/hooks/load_translation_file/),
[script content](https://developer.wordpress.org/reference/functions/load_script_translations/).
No legacy hook exception is needed for the Core-level prefix mapping, so the
existing `load_textdomain_mofile` ban remains intact. GravityView 3.3.4 source is now
inspected; WU-004 did not introduce an executable adapter, and real licensed loader/
browser integration remains `NOT_RUN` rather than inferred from source evidence.

## Runtime contracts

### Earliest registration and load order

The main plugin file requires `PGR_Localization`, reads declarative manifests and
registers four filters immediately after its constants. It does not call gettext,
load foreign translations, query vendor classes, scan installed plugins or depend
on `gform_loaded`, `plugins_loaded` or `init`. Existing GF fields/admin lifecycle
remains intact. Products may all be absent.

Activity before WordPress includes PersianGravity cannot be intercepted. Already
loaded upstream objects/catalogs are preserved, including their existing collision
precedence. No unload/reload is used to conceal that boundary. For the overlay
contract, load PersianGravity before the first managed translation request.
A previous failed JIT/NOOP cache before registration is also outside that guarantee.

### Discovery

`lang_dir_for_domain` returns the original path whenever it exists. Only a managed
`fa_IR` request with a provider artifact and no upstream path receives the provider
directory. Thus registry fallback knowledge is not replaced or reconstructed.
The file filter also handles requests against a registered custom directory that
contains no Persian file. No catalogs means no discovery override.

### PHP precedence and admitted source findings

During `load_translation_file`, a guarded targeted `load_textdomain()` loads the
provider before Core loads the original requested candidate. Core's translation
controller resolves the first loaded matching entry; later upstream files supply
missing keys. Its native loaded-file cache prevents duplicate catalog parsing.
The guard is cleared in `finally`, so unload/reload is not blocked by stale flags.

An absent upstream `.l10n.php` must not suppress an existing upstream `.mo` attempt.
If neither upstream format exists, a valid provider path lets the original Core
request succeed. Provider read/load failure returns the original path. Core lazily parses PHP data; a corrupt PHP catalog does not promise automatic MO recovery, and build validation must keep shipped catalogs valid. Upstream entries remain available. `.l10n.php` and MO are consumed by WordPress, never a runtime PO/MO parser here.

The prefix field maps only an exact Core-generated `{domain}-fa_IR` basename to
the approved `{prefix}-fa_IR` basename, in the same directory. Existing ordinary
paths are respected. This expresses GravityView's supplied prefix as data. The exact
3.3.4 package has now been inspected, but WU-004 adds no provider catalog or script
handle and does not claim real browser/runtime loader validation.

Gravity Forms 3.1.1.1 declares `gravityforms`. `gravityforms.php` calls `GFCommon::load_gf_text_domain()`; the implementation in `common.php` checks an explicit `WP_LANG_DIR/gravityforms/{domain}-{locale}.mo` path using `load_textdomain()` and then calls `load_plugin_textdomain()` for the plugin `languages` directory. Gravity Flow 3.1.0 declares `gravityflow` and does not independently call `load_plugin_textdomain()`/`load_textdomain()` in the admitted source; its add-on lifecycle delegates to the Gravity Forms add-on parent, with an older-GF compatibility translation manager path. GravityView 3.3.4 source admission establishes the `gk-gravityview` primary domain plus a separate `gk-query-filters` Composer dependency/domain boundary; that dependency is not manifested or activated by PersianGravity. These product findings are source evidence, not licensed browser/runtime proof.

### JavaScript precedence and admitted census

Only approved domain/handle pairs and effective locale `fa_IR` may participate.
`load_script_translations` composes a valid upstream Jed result with the local
provider messages. Provider nonempty values win; upstream-only keys remain.
Context keys, plural arrays and compatible metadata are retained. Incompatible
plural rules retain upstream to avoid reinterpreting its plural indexes.
Invalid provider content preserves upstream; invalid upstream content returns
false so Core can try its next candidate. Provider file reads are request-cached.

`pre_load_script_translations` supplies provider-only content **only at Core's
final `file=false` fallback**, after custom/handle/hash candidates are exhausted.
Injecting provider JSON at an earlier missing path would wrongly suppress a later
valid upstream file. Earlier non-null short-circuit results from other plugins
are respected. Competing third-party short-circuits are outside that guarantee.

No product handle is presently activated. `tools/i18n/admission/javascript.json` records source-proven evidence only. Gravity Forms has eight compact classic-script records: one native `wp_set_script_translations()` attachment, seven records without native translation attachment, and eight PHP-localized-JS surfaces. Gravity Flow has seven classic records, zero native translation attachments, and four PHP-localized-JS surfaces. GravityView has 15 compact classic-script records, one fixed native-translation-attached record, 14 remaining records, 13 PHP-localized-JS records, and two source `wp_set_script_translations()` attachment sites when the dynamic Gutenberg block-handle site is counted alongside the fixed Divi site. No WordPress Script Module API is present in any of the three target sources: `NOT_PRESENT_IN_TARGET_SOURCE`. GravityView's runtime activated handle count remains zero, its manifest `scripts` map remains empty, and `gk-query-filters` remains a separate dependency not runtime-activated by PersianGravity. PHP-localized JavaScript remains separate from JSON gettext-catalog activation.

## Ownership and provenance

For explicitly supported products and `fa_IR`, PersianGravity is the generic
local provider/overlay, **not an official upstream translation** or owner of
vendor files. A provider translation wins only for entries it supplies.
Upstream/vendor/TranslationsPress catalogs remain valid fallback sources. Their
updaters, directories and packages are not disabled, changed, overwritten or deleted.
No SRWF terminology or business semantic rewriting belongs in these catalogs.

Each source directory records target version separately from inspected source version, package hash, vendor POT hash, source-status and review state. In metadata-only source admission there is deliberately no committed full `source.pot`; `source_pot_sha256` therefore remains null. `tools/i18n/admission/` records the non-expressive source census, keyset/reference hashes, JavaScript evidence, surface registry, RTL/Bidi checklist, provisional terminology evidence, and bounded content-admission manifests/evidence indexes. Installed product version alone does not establish catalog compatibility.

## Deterministic development build

- Editable aggregate provider source: `languages/providers/<product>/source/fa_IR.po`.
- Per-record sparse content sources, when revision-2 content authority is present: `languages/providers/<product>/source/records/*.po`.
- Source authority: `source/provenance.json` plus `tools/i18n/admission/` evidence.
- Runtime output: sibling MO, `.l10n.php`, and per-approved-handle Jed JSON files only when translated content/handles are legitimately admitted.
- Metadata: sibling `metadata.json`; never consumed by the runtime.
- Development-only PO/MO dependency: `gettext/gettext` **5.7.3**, with Composer lock.
- PHP and Jed serialization is a small build-only mapping of PO entries; fuzzy, empty and incomplete plural entries are excluded.
- No make-json purge, runtime generation, runtime downloads, database or editor.
- `composer i18n:build` deliberately writes approved outputs. `composer i18n:check` generates comparison files in temporary storage and fails on any byte/hash drift or orphan artifact, without modifying canonical PO or committed artifacts.
- Products with source admission but zero content records remain runtime-content dormant; content-admitted products must match their validated aggregate exactly.
- CI validates shipped PHP including generated catalogs; generated arrays are checked by syntax, PHPCompatibility and deterministic build (not runtime WPCS).
- `.distignore` excludes tools, tests, dependencies, Core checkout and source PO provenance directories; runtime code/manifests and generated admitted catalogs ship.

## Surface Registry, RTL/Bidi and terminology

The canonical registry in `tools/i18n/admission/surfaces.json` contains 19 deterministic source-backed surfaces: six Gravity Forms, seven Gravity Flow and six GravityView. Classification uses explicit source-path rules only; there is no semantic fallback. GF: 4207 unique / 1759 classified / 2448 unclassified / 55 multi-surface. Flow: 1098 / 732 / 366 unclassified / 30 multi-surface. GravityView: 3127 / 461 / 2666 unclassified / 6 multi-surface. Control types are source-backed and do not imply RTL correctness.

`tools/i18n/admission/rtl-bidi.json` keys the inspection checklist to all 19 admitted RTL surfaces and records `runtime_execution=NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`; no production RTL patch is included. GravityView's six surfaces are source-only evidence and real licensed GravityView browser/UI validation remains `NOT_RUN`.

`tools/i18n/admission/glossary.json` contains five provisional common terms: Settings, Form, Status and Email have evidence in all three admitted products, while User has source evidence in Gravity Forms and Gravity Flow only. Product extensions remain two Gravity Forms terms, three Gravity Flow terms and zero GravityView-specific terms; ten technical/brand tokens are protected. `Entry` is not hard-locked. These are governance evidence, not production translation catalog entries.

## Source admission and bounded content admission

Source admission does not itself populate provider translations. Gravity Forms production content authority is intentionally partial: exactly one record for `gravityforms::frontend_runtime::shortcode:gravityform`, bounded by `form_display.php`, with 41 canonical identities from the separate 4207-message source census. The WU-003 reviewed full baseline remains provenance, while exactly seven PR #15 semantic corrections define the final admitted sparse content. The corrected record preserves the original 41-identity keyset and path fingerprint, activates zero native JS handles, generates zero translation JSON, and does not broaden runtime fallback semantics.

GravityView 3.3.4 is now source-admitted at `PACKAGE_INSPECTED_METADATA_ONLY` from exact package SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`; `project_source_authority=OWNER_SUPPLIED_EXACT_PACKAGE`, `vendor_authenticity=NOT_PROVEN`, and the package contains no vendor POT so `vendor_pot_sha256=null`. Its primary `gk-gravityview` census is 3127 canonical identities / 127 contexts / 42 plurals / 3931 references. `gk-query-filters` remains a bounded separate Composer dependency/domain and is not manifested or runtime-activated by PersianGravity. Six source-backed GravityView surfaces are registered, but production translated count remains zero, runtime JS handles remain zero, and no GravityView provider MO, `.l10n.php`, or translation JSON is admitted. RTL/BiDi evidence is source-only and real licensed browser/UI execution is `NOT_RUN`. WU-004 is source admission only and does not constitute translation-content admission.

Gravity Flow content authority is revision 2 and intentionally partial. The reviewed baseline SHA-256 is `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9`. Production contains exactly the Inbox and Status records. Inbox remains 255 identities with its locked fingerprints unchanged. Status derives exactly 43 identities from `includes/pages/class-status.php`; the records overlap on 10 identities with identical translation rows; aggregate authority is the deterministic 288-identity union while the source census remains separately 1098. The current aggregate provider PO SHA-256 is `838c2409841c099d17d475e6290ec8fff49ad237331cd527f3e25769d2162267`, with deterministic MO `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640` and `.l10n.php` `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

Internal source-admission consistency is checked by `tools/i18n/admission.php`; revision-2 content authority is checked by `tools/i18n/content-admission.php`; both are consumed by `tools/i18n/build.php`. CI can validate committed metadata/content evidence without possessing licensed vendor packages or the full reviewed baseline. That is not equivalent to re-running vendor extraction or licensed browser integration in CI.

## Core test command and licensed smoke matrix

```sh
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
composer i18n:check
git clone https://github.com/WordPress/WordPress.git /tmp/pgr-wordpress
git -C /tmp/pgr-wordpress checkout 6abb46bec8b58abba32602738a785ba1c83f2a1c
PGR_WP_CORE=/tmp/pgr-wordpress composer i18n:test
```

Core tests execute real gettext/JIT/registry/translation-controller/PHP/MO code.
Host/DB/cache services are stubbed; this is not a full WordPress site. They also
exercise the shared Jed composition separately, with no invented product handles.

Licensed integration remains `NOT_PROVEN_REAL_INTEGRATION_ENVIRONMENT_UNAVAILABLE`:

- Provider-only PHP with no upstream Persian file.
- PHP collision and upstream-only fallback across all three products.
- Actual custom/upstream paths, TranslationsPress behavior and GravityView loader.
- Verified-handle JSON collision, upstream-only and provider-only final fallback.
- One visible translated surface per product and one real browser `wp.i18n` surface.
- Non-Persian locale unchanged, user/site locale switching, and optional product absence.
- Load order before/after PersianGravity inclusion, without rewriting vendor files.

No real integration PASS may be inferred from green Core/unit tests. WU-005, the Status PO header repair, WU-007, PR #15 semantic recovery, and WU-004 source admission do not merge, release, deploy, broaden existing translation-content authority, or claim full-product/browser localization.
