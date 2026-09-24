# Gravity ecosystem localization provider foundation

Original foundation work unit: `WU-PGR-GRAVITY-LOCALIZATION-PROVIDER-02`.
Original foundation base: `b1a52c975988bf60f473d4b843de057ed5bd0c06`.
Reconciled with main `62ee8b2808640578bfdba0583342d29b7ef8a164` through `WU-PR8-SEMANTIC-RECONCILIATION-01`; active repository identity is **4.7.0**. The six-module manager, module-state class-load gates, bilingual admin/help and own-plugin GNU-gettext assets remain authoritative. Localization is cross-cutting infrastructure outside `PGR_Module_Registry`, independent of every module toggle.

Decision C is closed: **Shared Core + Declarative Product Manifests + Bounded Adapters**.

## Product evidence and honest activation boundary

| Product | Target / observed | Source status | Package SHA-256 | Vendor POT SHA-256 | Native provider JS handles |
| --- | --- | --- | --- | --- | --- |
| Gravity Forms | 3.1.1.1 / 3.1.1.1 | `PACKAGE_INSPECTED_METADATA_ONLY` + full reviewed content | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf` | 0 |
| Gravity Flow | 3.1.0 / 3.1.0 | `PACKAGE_INSPECTED_METADATA_ONLY` + full reviewed content | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | `09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961` | 0 |
| GravityView | 3.3.4 / 3.3.4 | `PACKAGE_INSPECTED_METADATA_ONLY` + partial reviewed content | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` | `null` (vendor POT absent) | 0 |
| Gravity Perks | 2.3.16 / 2.3.16 | exact-package source + full reviewed primary-domain content | `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c` | `d56ae3bab39365193e17f9c4113ed30e65f33565d2697749df2931890b6f453d` | 0 |
| GP File Upload Pro | 1.5.13 / 1.5.13 | exact-package source + full reviewed primary-domain content | `fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce` | `f2ccb660acd7b6952950eef9a617386da6b4bc19a22ede8f765765578ece1b7b` | 0 |
| GP Advanced Select | 1.1.21 / 1.1.21 | exact-package source + full reviewed primary-domain content | `d83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2` | `1b595f32532235e931a5572b880fa03840c3420dbcfd40384cac52d566301923` | 0 |

For Gravity Forms, Gravity Flow and GravityView, `PROJECT_SOURCE_AUTHORITY=OWNER_SUPPLIED_EXACT_PACKAGE`, `VENDOR_AUTHENTICITY=NOT_PROVEN`, and source admission remains separately evidenced. Exact package bytes were inspected and pinned, but vendor authenticity is not claimed. GravityView's exact package contains no vendor POT, so `vendor_pot_sha256` remains `null`; no POT hash is inferred or fabricated. G-007 likewise binds source/content authority only to the three exact owner-supplied package bytes above; it does not establish vendor authenticity, broader Gravity Perks ecosystem authority, or future-version compatibility. Licensed package ZIPs and extracted vendor source are not committed.

`SOURCE VERIFIED != TRANSLATION CONTENT ADMITTED != JS SURFACE ACTIVATED != BROWSER VERIFIED`. Gravity Forms and Gravity Flow are revision-3 `CONTENT_ADMITTED_FULL`: Gravity Forms is 4207/4207 and Gravity Flow is 1098/1098. GravityView remains revision-2 `CONTENT_ADMITTED_PARTIAL` at 461/3127 with exactly 2666 identities still mandatory. Separately, G-007 admits the complete primary-domain censuses for its exact packages: Gravity Perks 83/83, GP File Upload Pro 39/39 and GP Advanced Select 5/5, for 127/127 reviewed accepted identities. The G-007 review indexes record two semantic passes, three second-pass wording corrections, and zero fuzzy, empty, rejected or unreviewed identities. These G-007 counts are not folded into the historical G-006 8432-identity census, whose accepted state remains 5766/8432 because GravityView is still incomplete.

The PR #14 Status sparse-PO header repair corrects only malformed gettext header escaping. The unchanged strict loader must parse `Language: fa_IR`, `X-Domain: gravityflow`, and `Plural-Forms: nplurals=2; plural=(n > 1);`. Current repaired raw SHA-256 values are Status `c160913904bf991b29274bfbda3615a1a12049c9b97b81f7b687ac9e5d0fd718` and aggregate provider PO `838c2409841c099d17d475e6290ec8fff49ad237331cd527f3e25769d2162267`; semantic identities, translations, content fingerprints, JS authority and runtime architecture are unchanged.

### Source ↔ POT consistency

The exact packages were inspected with deterministic source extraction plus byte-level reconciliation where required. A temporary WP-CLI 2.12.0 source-POT regeneration was attempted for the earlier Gravity Forms/Flow admission but could not execute because the environment blocked tool download; that tooling gap remains explicitly separate from the source-backed consistency result.

- Gravity Forms: vendor POT 4208 unique keys; 4207 source-backed keys; 0 missing from vendor POT; 1 vendor-POT-only stale test key whose referenced test path is absent from the supplied package; 22 context messages; 16 plural messages; context/plural differences 0.
- Gravity Flow: vendor POT 1098 unique keys; 1098 source-backed keys; 0 missing; 0 stale; 3 context messages; 6 plural messages; context/plural differences 0.
- GravityView: vendor POT absent; deterministic literal-gettext extraction yields 3127 `gk-gravityview` canonical identities, 127 contexts, 42 plurals and 3931 source references. Source consistency is bound to the committed keyset/reference fingerprints rather than a fabricated POT hash. One non-literal gettext call is recorded separately instead of being guessed into the canonical keyset.
- Gravity Perks: vendor POT 86 keys; 83 source-backed `gravityperks` primary-domain identities; 3 plugin-header metadata-only POT keys; 0 primary source identities missing from POT.
- GP File Upload Pro: vendor POT 44 keys; 39 source-backed `gp-file-upload-pro` primary-domain identities; 5 plugin-header metadata-only POT keys; 0 primary source identities missing from POT.
- GP Advanced Select: vendor POT 10 keys; 5 source-backed `gp-advanced-select` primary-domain identities; 5 plugin-header metadata-only POT keys; 0 primary source identities missing from POT.

G-007 cross-domain calls are evidence, not authority expansion. Gravity Perks has one `gravity-perks` reference plus four calls without an explicit managed domain; File Upload Pro has one `gravityperks` bootstrap reference; Advanced Select has four `gp-advanced-phone-field`, one `gp-populate-anything`, and one `gravityperks` reference. None is silently merged into the three G-007 primary providers.

Canonical identity is SHA-256 over `msgctxt + U+001F + msgid + U+001F + msgid_plural`. Historical GF/Flow/View aggregate keyset/reference hashes live under `tools/i18n/admission/`; G-007 exact package, POT, census, cross-domain and content fingerprints are locked by `tools/i18n/admission/g007-gravity-perks.json` and its review indexes.

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
No legacy hook exception is needed for the Core-level prefix mapping, so the existing `load_textdomain_mofile` ban remains intact.

## Runtime contracts

### Earliest registration and load order

The main plugin file requires `PGR_Localization`, reads the declarative localization registry and registers the shared provider filters immediately after its constants. G-007 extends that registry with `includes/localization/g007-products.php`; it does not add a parallel gettext engine. The provider does not call gettext, load foreign translations, query vendor classes, scan installed plugins or depend on `gform_loaded`, `plugins_loaded` or `init`. Existing GF fields/admin lifecycle remains intact and optional products may all be absent.

Activity before WordPress includes PersianGravity cannot be intercepted. Already loaded upstream objects/catalogs are preserved, including their existing collision precedence. No unload/reload is used to conceal that boundary. For the overlay contract, load PersianGravity before the first managed translation request. A previous failed JIT/NOOP cache before registration is also outside that guarantee.

### Discovery

`lang_dir_for_domain` returns the original path whenever it exists. Only a managed `fa_IR` request with a provider artifact and no upstream path receives the provider directory. Thus registry fallback knowledge is not replaced or reconstructed. The file filter also handles requests against a registered custom directory that contains no Persian file. No catalogs means no discovery override.

### PHP precedence and admitted source findings

During `load_translation_file`, a guarded targeted `load_textdomain()` loads the provider before Core loads the original requested candidate. Core's translation controller resolves the first loaded matching entry; later upstream files supply missing keys. Its native loaded-file cache prevents duplicate catalog parsing. The guard is cleared in `finally`, so unload/reload is not blocked by stale flags.

An absent upstream `.l10n.php` must not suppress an existing upstream `.mo` attempt. If neither upstream format exists, a valid provider path lets the original Core request succeed. Provider read/load failure returns the original path. Core lazily parses PHP data; a corrupt PHP catalog does not promise automatic MO recovery, and build validation must keep shipped catalogs valid. Upstream entries remain available. `.l10n.php` and MO are consumed by WordPress, never a runtime PO/MO parser here.

The prefix field maps only an exact Core-generated `{domain}-fa_IR` basename to the approved `{prefix}-fa_IR` basename, in the same directory. Existing ordinary paths are respected. This expresses GravityView's supplied prefix as data.

Gravity Forms 3.1.1.1 declares `gravityforms`. `gravityforms.php` calls `GFCommon::load_gf_text_domain()`; the implementation in `common.php` checks an explicit `WP_LANG_DIR/gravityforms/{domain}-{locale}.mo` path using `load_textdomain()` and then calls `load_plugin_textdomain()` for the plugin `languages` directory. Gravity Flow 3.1.0 declares `gravityflow` and does not independently call `load_plugin_textdomain()`/`load_textdomain()` in the admitted source; its add-on lifecycle delegates to the Gravity Forms add-on parent, with an older-GF compatibility translation manager path. GravityView 3.3.4 source admission establishes the `gk-gravityview` primary domain plus a separate `gk-query-filters` dependency/domain boundary; that dependency is not manifested or activated by PersianGravity.

For G-007, all three primary providers use the same shared PHP catalog path. GP File Upload Pro 1.5.13 evaluates uploader labels with PHP gettext and passes the resolved values into its browser runtime through `wp_localize_script()`. The admitted acceptance strings are `select files` → `انتخاب فایل‌ها`, `Drop files here` → `فایل‌ها را اینجا رها کنید`, and `or` → `یا`; no parallel script-translation subsystem is needed for those labels. GP Advanced Select 1.1.21 additionally has the bounded `PGR_Gravity_Perks_RTL` compatibility adapter: it requires exact version `1.1.21`, effective `fa_IR` + WordPress RTL and the exact registered `gp-advanced-select-tom-select` style handle, then scopes logical caret/padding correction to the authentic `.ts-wrapper.gfield_select.plugin-change_listener.rtl` state. Vendor CSS/package bytes remain untouched.

The source/runtime-path evidence remains independently recorded, and G-009/WU008 now adds exact-package browser proof for the authentic File Upload Pro and Advanced Select surfaces without converting source evidence into browser evidence.

### JavaScript precedence and admitted census

Only approved domain/handle pairs and effective locale `fa_IR` may participate in native WordPress script translations. `load_script_translations` composes a valid upstream Jed result with local provider messages. Provider nonempty values win; upstream-only keys remain. Context keys, plural arrays and compatible metadata are retained. Incompatible plural rules retain upstream to avoid reinterpreting its plural indexes. Invalid provider content preserves upstream; invalid upstream content returns false so Core can try its next candidate. Provider file reads are request-cached.

`pre_load_script_translations` supplies provider-only content only at Core's final `file=false` fallback, after custom/handle/hash candidates are exhausted. Earlier non-null short-circuit results from other plugins are respected.

No native provider script-translation handle is presently activated for the historical GF/Flow/View set or for G-007. The three G-007 product manifests have empty script maps and generate no provider translation JSON. File Upload Pro's `wp_localize_script()` data is PHP localization, not native JSON/Jed script translation. GP Advanced Select's `gp-advanced-select-tom-select` identifier is a style handle used by the bounded RTL adapter, not a script-translation handle.

## Ownership and provenance

For explicitly supported products and `fa_IR`, PersianGravity is the generic local provider/overlay, not an official upstream translation or owner of vendor files. A provider translation wins only for entries it supplies. Upstream/vendor/TranslationsPress catalogs remain valid fallback sources. Their updaters, directories and packages are not disabled, changed, overwritten or deleted. No SRWF terminology or business semantic rewriting belongs in these catalogs.

Each source directory records target version separately from inspected source version, package hash, vendor POT hash, source-status and review state. `tools/i18n/admission/` records the source census, keyset/reference hashes, JavaScript evidence, surface registry, RTL/Bidi evidence and bounded content-admission manifests/evidence indexes. Installed product version alone does not establish catalog compatibility.

## Deterministic development build

- Editable aggregate provider source: `languages/providers/<product>/source/fa_IR.po`.
- Per-record sparse content sources, when record-scoped content authority is present: `languages/providers/<product>/source/records/*.po`.
- Source authority: `source/provenance.json` plus `tools/i18n/admission/` evidence.
- Runtime output: sibling MO, `.l10n.php`, and per-approved-handle Jed JSON files only when translated content/handles are legitimately admitted.
- Metadata: sibling `metadata.json`; never consumed by the runtime.
- Development-only PO/MO dependency: `gettext/gettext` **5.7.3**, with Composer lock. G-007 MO generation uses the shared compiler plus its documented source-order header normalization; the translation table/runtime loader is unchanged.
- PHP and Jed serialization is build-only; fuzzy, empty and incomplete plural entries are excluded.
- No make-json purge, runtime generation, runtime downloads, database or editor.
- `composer i18n:build` deliberately writes approved outputs. `composer i18n:check` generates comparison files in temporary storage and fails on byte/hash drift or orphan artifacts without modifying canonical source.
- CI validates shipped PHP including generated catalogs; generated arrays are checked by syntax, PHPCompatibility and deterministic build.
- `.distignore` excludes tools, tests, dependencies, Core checkout and source PO provenance directories; runtime code/manifests and generated admitted catalogs ship.

## Surface Registry, RTL/Bidi and terminology

The historical GF/Flow/GravityView Surface Registry remains 19 deterministic source-backed surfaces: six Gravity Forms, seven Gravity Flow and six GravityView. G-007 does not invent or rewrite those historical surface records. Gravity Forms remains 4207/4207, Gravity Flow 1098/1098 and GravityView 461/3127 with 2666 mandatory identities remaining.

The historical `rtl-bidi.json` evidence for those 19 surfaces remains historical. G-007's Advanced Select adapter has its own source-proven wrapper/caret/padding evidence and automated lifecycle coverage in `docs/VALIDATION_G007.md`; exact-package visual browser proof remains separate and `NOT_VERIFIED`.

`tools/i18n/admission/glossary.json` remains governance evidence for the historical admitted products; G-007 content authority is independently locked by its exact-package manifest/review evidence rather than silently broadening historical glossary/surface claims.

## Source admission and bounded content admission

Source admission does not itself populate provider translations. Current production authority is:

- Gravity Forms 3.1.1.1: revision-3 `CONTENT_ADMITTED_FULL`, 4207/4207 accepted source-backed identities; known vendor-POT-only stale identity excluded; script map empty.
- Gravity Flow 3.1.0: revision-3 `CONTENT_ADMITTED_FULL`, 1098/1098 accepted identities; script map empty.
- GravityView 3.3.4: revision-2 `CONTENT_ADMITTED_PARTIAL`, 461/3127 accepted identities; exactly 2666 remain mandatory; script map empty.
- Gravity Perks 2.3.16: G-007 exact-package `CONTENT_ADMITTED_FULL`, 83/83 primary-domain identities; script map empty.
- GP File Upload Pro 1.5.13: G-007 exact-package `CONTENT_ADMITTED_FULL`, 39/39 primary-domain identities; script map empty; uploader labels flow through PHP gettext → `wp_localize_script()`.
- GP Advanced Select 1.1.21: G-007 exact-package `CONTENT_ADMITTED_FULL`, 5/5 primary-domain identities; script map empty; bounded exact-style-handle RTL compatibility adapter enabled only for `fa_IR` + RTL.

G-007 cross-domain calls remain excluded and broader Gravity Perks products/versions remain outside authority. Missing identities in products that remain partial retain upstream/vendor/TranslationsPress fallback and then source English. Vendor packages/updaters remain unmodified.

The historical G-006 census remains 5766/8432 accepted across Gravity Forms, Gravity Flow and GravityView, with exactly 2666 GravityView identities still mandatory. G-007 completion does not close or reduce that separate GravityView obligation.

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

Core tests execute real gettext/JIT/registry/translation-controller/PHP/MO code. Host/DB/cache services are stubbed; this is not a full WordPress site. They also exercise shared provider composition separately, with no invented product handles.

Historical licensed WU-008/WU-009 evidence for exact Gravity Forms/Gravity Flow/GravityView products is preserved in `docs/VALIDATION.md`. It is preservation/integration evidence for those products and must not be used as exact G-007 browser proof.

Exact-package G-007 browser rendering is now qualified by the G-009/WU008 lane for the two explicitly admitted Perk surfaces. The lane verifies all three locked package hashes/versions/roots before activation, binds evidence to the exact PersianGravity Head, and records independent fa_IR/RTL and en_US/LTR profiles at 1280x900 and 390x844.

- File Upload Pro 1.5.13 visibly renders the three admitted Persian uploader labels through its real PHP gettext → `wp_localize_script()` path, preserves native English in LTR, keeps the uploader usable, and preserves a mixed Persian/technical filename as a BiDi-isolated token.
- Advanced Select 1.1.21 is `ADAPTER_REQUIRED_AND_VERIFIED` for the authentic `gp-advanced-select-tom-select` / `.ts-wrapper.gfield_select.single.plugin-change_listener.rtl` seam; the RTL caret reserve is physically and logically mirrored while keyboard/search/selection/focus remain usable, and the LTR control receives no adapter CSS.
- No other Perk or future version is admitted by this evidence.

See `docs/G007_GRAVITY_PERKS_LOCALIZATION.md` and `docs/VALIDATION_G007.md` for the exact evidence boundary.
