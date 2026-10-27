# G-011 — Gravity Forms confirmation date Merge Tags

**Status:** Owner-approved `G011-POSTNATIVE-LOCK` Revision 1.1; bounded Text repair authorized and under exact-Head qualification. G-011 remains **OPEN**. Host baseline: Owner-supplied Gravity Forms **3.1.1.1**, SHA-256 542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b. No newer vendor version qualifies automatically.

## Boundary

Gravity Forms selects the actual conditional confirmation in GFFormDisplay::handle_confirmation(). It renders Text through get_confirmation_message() or obtains the Page/Redirect URL with get_confirmation_url(), then invokes gform_confirmation. AJAX Redirect script transformation follows the filter. gform_pre_replace_merge_tags is shared by notifications and is intentionally **not** registered by PersianGravity.

The opt-in jalali_presentation adapter registers **only gform_confirmation**. It processes the selected Text confirmation under fa_IR, using authoritative raw UTC Entry system dates or strictly parsed Gregorian-owned GF_Field_Date values. It delegates calendar conversion solely to PGR_Jalali_Presentation::format_date().

The authorized post-native repair operates on the **already rendered selected Text confirmation**. It reads the selected source template solely for per-occurrence context and position, and independently derives the expected host-native value from authoritative Gregorian/UTC dates or parsed native Date-field values using bounded native date formatting primitives. It admits Jalali replacement only where native representation, unique static context, order and non-overlap prove the rendered span's ownership; bytes outside admitted spans are preserved. It invokes neither `GFCommon::replace_variables()` nor `GFFormDisplay::get_confirmation_message()` nor any other host Merge Tag rendering pass. Only positively recognized human text nodes may be converted; attributes, raw URLs, opaque/code/script/style/comment content, hidden or unknown elements and machine-like text remain native. Ambiguous markup, changed upstream anchors, duplicate/nonunique alignment or native value mismatch cause unchanged received native fallback. No form/Entry/API/mail/feed/workflow state is written by the adapter. Positive liveness and callback parity still require independently observed exact-package runtime/browser evidence.

For Text confirmations, root-relative (`/archive/{date_created}`), dot-relative (`./archive/{date_created}`), parent-relative (`../archive/{date_created}`), and other unambiguous path-bearing text tokens remain native per occurrence; a separate human-facing `Created={date_created}` remains eligible. Ambiguous contexts retain native fallback. This is not Page/Redirect transport conversion.

Page/Redirect Query String evaluation is already complete before the hook. This batch deliberately keeps those transport paths native until their human-facing versus machine-valued parameters, escaping and :urlencode can be proven safe.

## Tag × modifier × confirmation-path evidence matrix

Evidence below is **specific to the exact licensed GF 3.1.1.1 package** and the production Adapter on source Head `cdd6a58fe2f0643512294706f68defb75c74e02b`. `HOST_PASS` means the authentic Gravity Forms `GFFormDisplay::handle_confirmation()` was exercised by WP-CLI; `BROWSER_PASS` means a real Chromium HTTP/AJAX submission. Neither implies every format was tried under every path. `NATIVE_PASS` means the tested exclusion remained byte-identical to native. `NOT_VERIFIED` names an untested interaction, not a product defect. Page/Redirect remain outside the **repair** and intentionally native.

| Capability | Selected Text, host/runtime | Text, genuine browser | Page/Redirect |
| --- | --- | --- | --- |
| `date_created` default, `:format:Y/m/d`, escaped `Y-m-d H\\:i`, `\\Y Y`, `:time` | **HOST_PASS** for all enumerated variants | **BROWSER_PASS** `:format:Y/m/d` Ordinary + AJAX, Default + Conditional; other formats individually NOT_VERIFIED in browser | Native; conversion not admitted |
| `date_updated` valid UTC, `payment_date` nonempty | **HOST_PASS** for both populated dates | Individual variants NOT_VERIFIED in browser | Native |
| `payment_date` empty/missing or invalid | **NATIVE_PASS** for empty, unit/host fallback controls | Individual empty/missing cases NOT_VERIFIED in browser | Native |
| `today` default, `:format:Y/m/d`, `:time` | **HOST_PASS** all three | **BROWSER_PASS** supported `:format:Y/m/d` Ordinary + AJAX; others individually NOT_VERIFIED in browser | Native |
| Calendrical `Y y m n d j F`, escaped literal, selected time/weekday primitives | **HOST_PASS** combined calendrical-token grammar and escaped examples; full time-token Cartesian product NOT_VERIFIED | Only specified date/time cases tested | Native |
| Native Gregorian `GF_Field_Date` `:year`, `:month`, `:day`, unmodified `mdy`, `:ymd`, `:mdy`, `:dmy`, and all nine named separator variants | **HOST_PASS** on native field with `mdy` source | **BROWSER_PASS** for `:year` in heterogeneous scenario; full selector-by-AJAX matrix NOT_VERIFIED | Native |
| `:human`, `:diff`, `:raw`, `:timestamp`, `entry:date_created`, Date-field `:raw`/`:urlencode`, unsupported week format | **NATIVE_PASS** on explicit host grammar cases | Native invariants on selected browser fixture; not every variant individually | Native |
| Already Jalali `pgr_jalali_date`, non-Date fields, out-of-range, malformed HTML, unsupported modifiers and locale/module off | Source/unit and bounded exact-host negative controls; do not infer universal coverage | Disabled/non-`fa_IR` and malformed/opaque controls exercised in licensed browser lanes | Native |
| Human text vs `href`/`data-*`, filenames `.tar.gz`, `-v2.pdf`, `_final.pdf`, relative URLs, hidden/code/script content | **HOST_PASS** for mixed human/file/attribute controls and no extra shared callback | **BROWSER_PASS** for relevant human/transport/filename/URI occurrences; some raw-text exclusions are unit-only | Native |
| Selected Text ordinary/AJAX, conditional selection, same-priority hook ties, post-filter visibility | **HOST_PASS** callbacks + selection; test-only tie qualification | **BROWSER_PASS** for Default + Conditional on Ordinary + AJAX, no page errors | Native |

Native `:human` relative/absolute and `:diff` remain GF-owned. Dates with Jalali-domain ownership are never double-converted. Gregorian system instants are shifted to `wp_timezone()` before presentation only; stored UTC/Entry values are unchanged. `Page`/`Redirect` URL query encoding, repeated keys and reserved characters retain the native host implementation and are not promoted to Jalali by this repair.

## Executable qualification

The existing WU008 workflow validates the exact private package and Head before installation, and the newly included G-011 paths trigger that workflow on PR. It now exercises real GFFormDisplay::handle_confirmation selection, Text, AJAX handler, Page/Redirect native controls, module toggle, en_US locale, raw GFAPI unchanged and global notifications negative control. Real Chromium ordinary/AJAX form submissions verify human-facing Jalali output and untouched unrelated text. PHPUnit separately tests bounded grammar, date ownership, timezone and fallback logic. No vendor archive or excerpt is permitted in CI artifacts.

**Do not upgrade any matrix cell to VERIFIED or claim CI/browser PASS without observing a successful exact-Head run.** Append exact run evidence after execution.

## Revision 1.1 post-native repair — evidence boundaries

The Owner approved exact `G011-POSTNATIVE-LOCK` Revision 1.1 and separately authorized bounded `repair_and_verify` on PR #83. Neither approval authorizes merge, tag, release or deployment. All 32 Matrix B `P=Y` rows are binding, including both positive conversion and intentionally native exclusions. The historical callback counts below are **not expected behavior** of the corrected Adapter.

Exact-package preservation integration now reports **27 positive conversion cases PASS** and **8 explicitly native grammar cases NATIVE_PASS** on the configured selected Text Confirmation, with genuine Chromium Default/Conditional submissions in ordinary and AJAX modes. These test-case counts do **not** equate to all 32 preservation rows or a Cartesian product of every context. Exact independent row mapping is the table above; untested browser/modifier interactions remain `NOT_VERIFIED`.

On a changed upstream anchor, invalid source/output mapping or ambiguous interval, the Adapter returns the exact received native output without re-render. Such fallback is an important safety property, but does not substitute for positive liveness under supported, independently attributable contexts.

## Executed suffix-context repair evidence (2026-10-26)

The G-011 occurrence-local classifier must also preserve native values when the date tag is *followed* by path/filename syntax. Deterministic negative controls for `{date_created:format:Y/m/d}/detail`, `{date_created:format:Y/m/d}#details`, `receipt-{date_created:format:Y/m/d}.pdf`, related filenames, relative paths, and repeated mixed HTML contexts initially failed on [pre-repair CI 40302218012](https://github.com/rezahh107/PersianGravity/actions/runs/40302218012). A first candidate also failed on [CI 40302754813](https://github.com/rezahh107/PersianGravity/actions/runs/40302754813) because the expression delimiter collided with a character class; no success is inferred from either failed candidate.

With the corrected local suffix matcher at Head `230a8bcf5633079549f44734a2f163e045bac076`, [CI 40302963646](https://github.com/rezahh107/PersianGravity/actions/runs/40302963646) passed focused G-011 tests (10 tests / 94 assertions), four supported PHP jobs, and the existing full PHPUnit and quality checks. [Exact-package WU008 40302963658](https://github.com/rezahh107/PersianGravity/actions/runs/40302963658) passed on licensed Gravity Forms 3.1.1.1 (locked SHA-256 above), including native-output comparisons for path/filename suffixes, visible date labels, negative controls and authentic Chromium ordinary/AJAX DOM assertions. No notification, Page/Redirect, storage or module authority was added.

**Historical pre-repair evidence:** WU008 on Head `22bdf84f67cba2b9a02424df4e934deae375c29e` independently measured the original rerendering Adapter's `gform_pre_replace_merge_tags` callback seam:

| Controlled probe | Module disabled / native | Module enabled / selected Text |
| --- | ---: | ---: |
| Counting callback, unchanged input | 1 invocation | 3 invocations |
| Non-idempotent callback, varying text | 1 invocation | 3 invocations |

For this **historical, now-replaced Adapter**, the mutating probe preserved host output but did **not** undo extra callback executions. The production replacement's exact-host counter tests below now demonstrate 1 native versus 1 enabled call under their two controlled scenarios. Those results do not prove all possible third-party extensions. G-011 stays **OPEN** for Owner/merge governance and other still-unqualified surfaces.

## Executed exact-host repair evidence (2026-10-27)

At production Adapter source Head `cdd6a58fe2f0643512294706f68defb75c74e02b`:
- [WU008 Licensed Real Integration run 40467290897](https://github.com/rezahh107/PersianGravity/actions/runs/40467290897): **SUCCESS**, exact GF 3.1.1.1 package SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`.
- [CI 40467290920](https://github.com/rezahh107/PersianGravity/actions/runs/40467290920): **SUCCESS** including PHP 8.2, 8.3, 8.4 and 8.5 jobs and WPCS.
- [G008 Runtime 40467290967](https://github.com/rezahh107/PersianGravity/actions/runs/40467290967) and [Artifact Install Smoke 40467290958](https://github.com/rezahh107/PersianGravity/actions/runs/40467290958): **SUCCESS**.

The WU008 artifact `g011-production-preservation.json` confirms **27/27 positive** and **8/8 native** checks, each with its own result. `g011-confirmation.json` shows the repaired controlled counting/non-idempotent shared-hook invocations `native=1, enabled=1` in both cases. `g011-architecture-qualification.json` separately reports source-marked positive presentation, preservation of three machine filename forms, non-idempotent native fallback and priority 20. `g011-browser.json` reports all four genuine Chromium combinations: Default/Conditional × Ordinary/AJAX `CONFIRMED`, Jalali visible and zero page errors. Stored Entry and configured Form checks are exercised in the licensed WU008 tests. This establishes the **examined scenarios**, not arbitrary third-party callback safety.

## Remaining G-011 outcomes

1. Page/Redirect human-facing Query String conversion and `:urlencode` transport handling were **intentionally not authorized in this Text-only repair**, so stay native. They remain part of the broader G-011 goal, not a regression of this repair.
2. Unexercised exact-host combinations remain bounded: non-`mdy` native Date-field input formats, additional weekday/time formatting permutations, broad daylight/timezone browser cases, every negative data shape on live browser, and unrelated real third-party extensions. Do not promote these from CI alone.
3. Gravity Forms 3.2+ Repeater children, mobile Datepicker, and new package qualification require separate Owner-supplied package evidence and decision.

