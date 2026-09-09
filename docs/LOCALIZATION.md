# Gravity ecosystem localization provider foundation

Original foundation work unit: `WU-PGR-GRAVITY-LOCALIZATION-PROVIDER-02`.
Original foundation base: `b1a52c975988bf60f473d4b843de057ed5bd0c06`.
Reconciled with main `62ee8b2808640578bfdba0583342d29b7ef8a164` through `WU-PR8-SEMANTIC-RECONCILIATION-01`; active repository identity is **4.2.0**. The six-module manager, module-state class-load gates, bilingual admin/help and own-plugin GNU-gettext assets remain authoritative. Localization is cross-cutting infrastructure outside `PGR_Module_Registry`, independent of every module toggle.

Decision C is closed: **Shared Core + Declarative Product Manifests + Bounded Adapters**.

## Product evidence and honest activation boundary

| Product | Target / observed | Source status | Package SHA-256 | Vendor POT SHA-256 | Active JS handles |
| --- | --- | --- | --- | --- | --- |
| Gravity Forms | 3.1.1.1 / 3.1.1.1 | `PACKAGE_INSPECTED_METADATA_ONLY` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf` | 0 |
| Gravity Flow | 3.1.0 / 3.1.0 | `PACKAGE_INSPECTED_METADATA_ONLY` + bounded content | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | `09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961` | 0 |
| GravityView | 3.3.4 / — | `PACKAGE_UNAVAILABLE` | — | — | 0 |

For Gravity Forms and Gravity Flow, `PROJECT_SOURCE_AUTHORITY=OWNER_SUPPLIED_EXACT_PACKAGE`, `VENDOR_AUTHENTICITY=NOT_PROVEN`, and source admission remains separately evidenced. Exact package bytes were inspected and pinned, but vendor authenticity is not claimed. Vendor ZIPs, extracted vendor source, full vendor POT/message corpora, and the full reviewed Persian baseline are not committed.

`SOURCE VERIFIED != TRANSLATION CONTENT ADMITTED != JS SURFACE ACTIVATED`. Gravity Forms remains source-admitted without production content authority in this boundary. Gravity Flow has revision-2 production content authority for exactly Inbox and Status: 255 Inbox identities, 43 Status identities, 10 identical overlaps, and a deterministic 288-identity aggregate from a separately reported 1098-message source census. Status evidence is restricted exactly to `includes/pages/class-status.php`; Inbox locked fingerprints remain unchanged. All runtime `scripts` maps remain empty and no Gravity Flow translation JSON is generated. `source_pot_sha256` remains `null` because no full `source.pot` is committed; `vendor_pot_sha256` records the exact inspected vendor POT bytes.

### Source ↔ POT consistency

The exact packages were inspected with a deterministic PHP tokenizer plus byte-level reconciliation for distributed JavaScript and plugin-header strings. A temporary WP-CLI 2.12.0 source-POT regeneration was attempted but could not execute because the environment blocked tool download; that tooling gap remains explicitly separate from the source-backed consistency result.

- Gravity Forms: vendor POT 4208 unique keys; 4207 source-backed keys; 0 missing from vendor POT; 1 vendor-POT-only stale test key whose referenced test path is absent from the supplied package; 22 context messages; 16 plural messages; context/plural differences 0.
- Gravity Flow: vendor POT 1098 unique keys; 1098 source-backed keys; 0 missing; 0 stale; 3 context messages; 6 plural messages; context/plural differences 0.

Canonical identity is SHA-256 over `msgctxt + U+001F + msgid + U+001F + msgid_plural`. Aggregate keyset/reference hashes and the minimum individual evidence identities required by terminology validation live in `tools/i18n/admission/baseline.json`.

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
existing `load_textdomain_mofile` ban remains intact. Exact GravityView custom
loader behavior is still unproven; no executable adapter has been invented.

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
request succeed. Provider read/load failure returns the original path. Core lazily parses PHP data;
a corrupt PHP catalog does not promise automatic MO recovery, and build validation
must keep shipped catalogs valid. Upstream entries remain available. `.l10n.php`
and MO are consumed by WordPress, never a runtime PO/MO parser here.

The prefix field maps only an exact Core-generated `{domain}-fa_IR` basename to
the approved `{prefix}-fa_IR` basename, in the same directory. Existing ordinary
paths are respected. This expresses GravityView's supplied prefix as data; the
actual 3.3.4 loader and its paths still require package inspection.

Gravity Forms 3.1.1.1 declares `gravityforms`. `gravityforms.php` calls `GFCommon::load_gf_text_domain()`; the implementation in `common.php` checks an explicit `WP_LANG_DIR/gravityforms/{domain}-{locale}.mo` path using `load_textdomain()` and then calls `load_plugin_textdomain()` for the plugin `languages` directory. Gravity Flow 3.1.0 declares `gravityflow` and does not independently call `load_plugin_textdomain()`/`load_textdomain()` in the admitted source; its add-on lifecycle delegates to the Gravity Forms add-on parent, with an older-GF compatibility translation manager path. These product findings are `STATIC_SOURCE_CONFIRMED`, not browser/runtime proof.

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

No handle is presently activated. `tools/i18n/admission/javascript.json` records source-proven evidence only. Gravity Forms has eight compact classic-script records: one native `wp_set_script_translations()` attachment (`gform_editor_block_form`), seven without native translation attachment, and eight PHP-localized-JS surfaces. Gravity Flow has seven classic records, zero native translation attachments, and four PHP-localized-JS surfaces. No WordPress Script Module API is present in either target source: `NOT_PRESENT_IN_TARGET_SOURCE`. PHP-localized JavaScript remains separate from JSON gettext-catalog activation.

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

The canonical registry in `tools/i18n/admission/surfaces.json` contains 13 deterministic source-backed surfaces: six Gravity Forms and seven Gravity Flow. Classification uses explicit source-path rules only; there is no semantic fallback. GF: 4207 unique / 1759 classified / 2448 unclassified / 55 multi-surface. Flow: 1098 / 732 / 366 / 30. Control types are source-backed and do not imply RTL correctness.

`tools/i18n/admission/rtl-bidi.json` keys the inspection checklist to all admitted RTL surfaces and records `runtime_execution=NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`; no production RTL patch is included.

`tools/i18n/admission/glossary.json` contains five provisional common terms with evidence in both admitted products, two Gravity Forms extensions, three Gravity Flow extensions, and nine protected technical/brand tokens. `Entry` is not hard-locked. These are governance evidence, not production translation catalog entries.

## Source admission and bounded content admission

Source admission does not itself populate provider translations. Gravity Forms remains source-admitted without production content authority in this boundary. GravityView remains `PACKAGE_UNAVAILABLE` until its exact target package is supplied and inspected.

Gravity Flow content authority is revision 2 and intentionally partial. The reviewed baseline SHA-256 is `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9`. Production contains exactly the Inbox and Status records. Inbox remains 255 identities with its locked fingerprints unchanged. Status derives exactly 43 identities from `includes/pages/class-status.php`; the records overlap on 10 identities with identical translation rows; aggregate authority is the deterministic 288-identity union while the source census remains separately 1098. The aggregate provider PO SHA-256 is `bc52c11763b536e44e977a1417d9096a1e3086f016b0a31206291340f411b757`, with deterministic MO `8f00043eae653e1993eb7d07d3dd1c6ad832348499b0bd59aa932b03bafbe640` and `.l10n.php` `7d3f2231831377e3a75d2e745a43553f5535fdabd85d68fbda6d360d93614e44`.

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

No real integration PASS may be inferred from green Core/unit tests. WU-005 does not merge, release, deploy, broaden product content authority, or claim full Gravity Flow localization.
