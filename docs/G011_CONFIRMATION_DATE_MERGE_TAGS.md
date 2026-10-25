# G-011 — Gravity Forms Confirmation Date Merge Tags (exact-host qualification)

Status: **PARTIAL IMPLEMENTATION — G-011 OPEN**, not a release or a claim about Gravity Forms 3.2.

## Authority and baseline

Owner's original and refined G-011 decisions, both October 23, 2026, in `GOAL_AUTHORITY/OWNER_DECISIONS_ACTIVE_SCOPE_EXPANSIONS`, govern this change. All original destinations remain mandatory, including Page/Redirect Query Strings, conditional selection and browser/protection checks, even when not presently admitted.

The verified licensed qualification source is **Gravity Forms 3.1.1.1**, exact ZIP 5,300,290 bytes, SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`, installed privately by the existing CI package-validation method. Vendor files are not committed. Source inventory and method contracts were actually collected in runs [40188177655](https://github.com/rezahh107/PersianGravity/actions/runs/40188177655) and [40188308335](https://github.com/rezahh107/PersianGravity/actions/runs/40188308335); native host behavior was observed in [40188528420](https://github.com/rezahh107/PersianGravity/actions/runs/40188528420).

## Root cause and chosen seam

Gravity Forms invokes the common merge-tag replacement engine from **notifications, default values, calculations and confirmations**. `gform_pre_replace_merge_tags` fires in that shared engine and cannot safely be used as an unqualified global date override. In this exact package, `GFFormDisplay::get_confirmation_message()` calls `GFCommon::replace_variables()` and then the `gform_confirmation` filter. By contrast `GFFormDisplay::get_confirmation_url()` assembles Redirect/Page URLs separately.

The G-011 adapter observes the shared pre-replacement hook **only when the direct caller sequence is the exact Text Confirmation message method** and only for `fa_IR` and the enabled G-008 presentation module. It marks typed, whitelist-recognized date tokens, allows GF to perform its own merge-tag replacement, then transforms just those marked values on that same Confirmation's final display hook. Other Merge Tag consumers do not satisfy this caller condition; no global date override or stored Entry modification occurs. Failure to prove ownership, parse the source date, recognize formatting, or convert in G-008 causes native fallback.

This solution is intentionally pinned to exact GF 3.1.1.1 host behavior. This is an admission check, not a claim about newer GF versions. The adapter never parses arbitrary rendered date-looking text or changes a native `pgr_jalali_date` field. It skips markup attributes and script/style/textarea bodies conservatively. `:time` retains GF's native local-time suffix. `:format` accepts only a confirmed PHP numeric calendar-and-time subset that agrees with the native result, otherwise falls back to the actual native output. `:human` and `:diff` are never rewritten.

**Rejected alternative:** permanently overriding the global pre-merge-tag result for `{date_created}` would also mutate notifications, queries and other machine consumers. It is simpler code but violates G-011. The selected adapter is reversible by disabling `jalali_presentation` or removing its two registrations.

## Tag × modifier × confirmation-path evidence matrix

Statuses refer to the exact GF 3.1.1.1 scope. `VERIFIED` means actually executed in the licensed WordPress **confirmation render API**; it must not be read as actual browser submission until the separate browser lane succeeds. `NATIVE_FALLBACK` means the adapter intentionally preserves GF's output. `NOT_VERIFIED` means no sufficient end-to-end evidence. `BLOCKED` means the proposed transformation is deliberately not admitted for safety.

| Native Merge Tag / class | Modifier | Text ordinary | Text AJAX server-render | Page query string | Redirect query string | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `{date_created}` | default | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | UTC Entry source, WP timezone |
| `{date_updated}` | default | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | UTC Entry source |
| `{payment_date}` | default, empty | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Empty native result; populated payment remains NOT_VERIFIED |
| `{today}` | default | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Current WP-local civil date |
| `{date_created}` | `:format:Y-m-d` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Only exact supported numeric formatting |
| System dates | `:format:` escaped/literal/time layouts | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Native fallback unless GF/PHP semantics agree |
| `{date_created}` | `:time` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Native suffix preserved |
| System dates | `:raw`, `:timestamp` | NATIVE_FALLBACK | NATIVE_FALLBACK | NOT_VERIFIED | NOT_VERIFIED | Machine values left untouched |
| System dates | `:human`, `:diff` | NATIVE_FALLBACK | NATIVE_FALLBACK | NOT_VERIFIED | NOT_VERIFIED | Native relative/absolute behavior retained |
| `{entry:date_created}` | default | NATIVE_FALLBACK | NATIVE_FALLBACK | NOT_VERIFIED | NOT_VERIFIED | Raw Entry property, no reinterpretation |
| Native Gregorian Date field | default `ymd` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Typed GF Date field and valid ISO Gregorian source required |
| Native Gregorian Date field | `:year` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Jalali component |
| Native Gregorian Date field | `:month` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Jalali component |
| Native Gregorian Date field | `:day` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Jalali component |
| Native Gregorian Date field | `:ymd` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Jalali year/month/day |
| Native Gregorian Date field | `:dmy` | VERIFIED | VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Jalali day/month/year |
| `pgr_jalali_date` / text / Time fields | any | NATIVE_FALLBACK | NATIVE_FALLBACK | NOT_VERIFIED | NOT_VERIFIED | Type whitelist never interprets as Gregorian |
| System or field tags | unsupported modifier, malformed date | NATIVE_FALLBACK | NATIVE_FALLBACK | NOT_VERIFIED | NOT_VERIFIED | Runtime negative-case completeness still NOT_VERIFIED |
| All date tags | `:urlencode`, URL transport | BLOCKED | BLOCKED | NOT_VERIFIED | NOT_VERIFIED | No URL parameter rewriting authorized by this PR |
| Page/Redirect dates used as opaque machine identifiers | any | n/a | n/a | BLOCKED | BLOCKED | Not a human-facing value |
| Conditional confirmation choice | any | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Requires explicit selected-branch behavioral test |
| Gravity Forms 3.2 Repeater child Date | any | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | NOT_VERIFIED | Not admitted by Owner's exact-package qualification |

This matrix documents the **complete requested destination** and does not silently claim that server-side `$ajax=true` equals a browser-tested AJAX submission.

## Native safety and future admission

No modifications to vendor plugins/updaters, database schema, stored `date_created`, Gregorian sort/filter, workflows, GFAPI, REST, exports or notifications are intended. Only claims directly exercised by tests can be called verified. The existing [G-008 exact licensed CI](../.github/workflows/g008-jalali-presentation-runtime.yml) separately exercises Gregorian storage/sort/filter and REST; it is not automatically new G-011 notification/export proof.

Page/Redirect must be addressed in a **separate evidence-backed qualification** against `get_confirmation_url`: selected actual configuration, human-facing vs opaque parameter, GF's own `:urlencode` path and handling of commas, `&`, percent escapes, repeated parameters and URL structure must be proved before any adapter broadening. Do not reuse the Text marker method without this proof.

## Reproduce evidence

- New workflow: `.github/workflows/g011-host-contract.yml` (exact licensed ZIP, source inventory, real WordPress native confirmation event trace, enabled Text Confirmation test, ordinary/AJAX Chromium test).
- Unit and quality gates: repository `CI` at exact branch/PR Head; check latest successful run rather than stale interim attempts.
- Browser: the Playwright script `tests/real-integration/g011-browser.mjs` submits the same configured form by both frontend paths. Distinguish its pass/failure from direct PHP method tests.
- G-011 is **not overall Done**: unsupported/unsafe-to-combine URL approaches and any unexecuted matrix cells stay open.
