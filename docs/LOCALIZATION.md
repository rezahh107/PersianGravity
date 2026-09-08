# Gravity ecosystem localization provider foundation

Original foundation work unit: `WU-PGR-GRAVITY-LOCALIZATION-PROVIDER-02`.
Original foundation base: `b1a52c975988bf60f473d4b843de057ed5bd0c06`.
Reconciled with main `62ee8b2808640578bfdba0583342d29b7ef8a164` through `WU-PR8-SEMANTIC-RECONCILIATION-01`; active repository identity is **4.2.0**. The six-module manager, module-state class-load gates, bilingual admin/help and own-plugin GNU-gettext assets remain authoritative. Localization is cross-cutting infrastructure outside `PGR_Module_Registry`, independent of every module toggle.

Decision C is closed: **Shared Core + Declarative Product Manifests + Bounded Adapters**.
Implementation status: **PARTIAL**. The shared core is implemented; exact product
source/POT admission and supported JavaScript surface activation are blocked.

## Product evidence and honest activation boundary

| Product | Owner-supplied target version | PHP domain | Filename prefix | Exact package/POT inspected | Approved JS handles |
| --- | --- | --- | --- | --- | --- |
| Gravity Forms | 3.1.1.1 | gravityforms | gravityforms | No | None |
| Gravity Flow | 3.1.1 | gravityflow | gravityflow | No | None |
| GravityView | 3.3.3 | gk-gravityview | gravityview | No | None |

Domains, versions and GravityView's prefix above are supplied by the owner's
implementation contract. They are **not re-verified vendor-source evidence**.
No licensed product packages or authoritative POTs were available in this work
session. File searches found unrelated SMS and knowledge-checkpoint archives,
not these product packages. Public documentation did not establish the exact
requested version/handle pairs. No untrusted redistributed package was used.

The manifests therefore have empty `scripts` maps, and production PO sources
contain only headers. Build emits metadata only; there are no production MO,
PHP or JSON catalogs. With these scaffolds, all original vendor/WordPress
translation behavior remains intact. This PR does **not** deliver visible
product Persian translations. It is a reviewable foundation awaiting sources.

Counts in each committed PO: translated **0**, untranslated **0**, fuzzy **0**.
The authoritative product total and coverage percentage are **unknown/null**;
a header-only scaffold is neither a complete census nor 0/100% product coverage.
Smoke fixtures are clearly marked synthetic and excluded from distribution.

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

### PHP precedence

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
actual 3.3.3 loader and its paths still require package inspection.

### JavaScript precedence

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
are respected. Competing third-party short-circuits are outside this guarantee.

No handle is presently approved: this content implementation is dormant for all
products. Pure composition tests are not vendor script lifecycle proof.
Strings translated in PHP and passed through `wp_localize_script()` require only
PHP translation; no separate browser retranslation mechanism is added.

## Ownership and provenance

For explicitly supported products and `fa_IR`, PersianGravity is the generic
local provider/overlay, **not an official upstream translation** or owner of
vendor files. A provider translation wins only for entries it supplies.
Upstream/vendor/TranslationsPress catalogs remain valid fallback sources. Their
updaters, directories and packages are not disabled, changed, overwritten or deleted.
No SRWF terminology or business semantic rewriting belongs in these catalogs.

Each source directory records target version separately from **inspected source
version**, POT/package hashes, catalog revision and review status. Build metadata
adds PO hash, actual counts, artifact hashes and approved handles. Missing source
hashes/versions are null, not fabricated. Upstream availability in this validation
is **synthetic Core fixtures only**. Installed product version does not establish
catalog compatibility.

## Deterministic development build

- Editable source: `languages/providers/<product>/source/fa_IR.po`.
- Source authority: `source/provenance.json` and, after admission, `source/source.pot`.
- Runtime output: sibling MO, `.l10n.php`, and per-approved-handle Jed JSON files.
- Metadata: sibling `metadata.json`; never consumed by the runtime.
- Development-only PO/MO dependency: `gettext/gettext` **5.7.3**, with Composer lock.
- PHP and Jed serialization is a small build-only mapping of PO entries; fuzzy,
  empty and incomplete plural entries are excluded.
- No make-json purge, runtime generation, runtime downloads, database or editor.
- `composer i18n:build` deliberately writes approved outputs. `composer i18n:check`
  generates comparison files in temporary storage and fails on any byte/hash drift
  or orphan artifact, without modifying canonical PO or committed artifacts.
- CI validates shipped PHP including generated catalogs; generated arrays are
  checked by syntax, PHPCompatibility and deterministic build (not runtime WPCS).
- `.distignore` excludes tools, tests, dependencies, Core checkout and source PO
  provenance directories; runtime code/manifests and eventual generated catalogs ship.

## Source admission and update-time verification

1. Obtain the owner's exact licensed packages. Inspect headers/version and hash the
   original package plus POT. Do not commit licensed vendor ZIPs or vendor code.
2. Inspect PHP domains, JIT/custom loaders, upstream directories and file prefixes.
   Re-check GravityView's real `.mo`/`.l10n.php` behavior before accepting data-only
   compatibility. Add a bounded executable exception only from a failing test.
3. Inspect every `wp_set_script_translations()` call and registered handle/domain,
   plus actual `@wordpress/i18n` references and production bundled filenames.
   Exclude PHP `wp_localize_script()` surfaces from the JS manifest.
4. Populate canonical PO from authoritative POT, keeping all msgids/context/plurals,
   and only available reviewed generic Persian msgstrs. Review placeholders and
   plural semantics. Preserve provenance; do not invent completeness.
5. Record `source_status=VERIFIED`, actual source version, POT/package SHA-256 and
   review authority in provenance. Build checks a complete PO/POT key census.
6. Add each JS handle with `source_version`, `evidence` (source file/line/hash
   reference) and `references` (exact PO source paths). Build derives only that
   script's messages and fails on missing provenance fields/JSON or orphan JSON.
7. Generate, review diff, run all checks, and add real request-lifecycle tests for
   those **verified** handles. The dormant scaffolding test must be updated to
   assert the newly established evidence rather than retained as a false blocker.
8. Run the licensed smoke matrix below; keep coverage and integration separate.

Internal drift is automatically checked. Exact vendor surface drift is
`VENDOR_SURFACE_DRIFT_CHECK = NOT_EXECUTED_PACKAGE_UNAVAILABLE`.
CI has no licensed packages and does not claim automatic vendor drift detection.
A manually populated evidence field is not itself independent source verification.

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

No real integration PASS may be inferred from green Core/unit tests.
