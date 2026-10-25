# G-011 — Gravity Forms confirmation date Merge Tags

**Status:** Scoped production candidate. G-011 remains **OPEN**. Host baseline: Owner-supplied Gravity Forms **3.1.1.1**, SHA-256 542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b. No newer vendor version qualifies automatically.

## Boundary

Gravity Forms selects the actual conditional confirmation in GFFormDisplay::handle_confirmation(). It renders Text through get_confirmation_message() or obtains the Page/Redirect URL with get_confirmation_url(), then invokes gform_confirmation. AJAX Redirect script transformation follows the filter. gform_pre_replace_merge_tags is shared by notifications and is intentionally **not** registered by PersianGravity.

The opt-in jalali_presentation adapter registers **only gform_confirmation**. It processes the selected Text confirmation under fa_IR, using authoritative raw UTC Entry system dates or strictly parsed Gregorian-owned GF_Field_Date values. It delegates calendar conversion solely to PGR_Jalali_Presentation::format_date().

Because the hook runs after host replacement, the adapter re-renders a copy of the **selected Text template** with collision-resistant opaque markers in place of eligible date tags. Restoring native date replacements must exactly reproduce the original host confirmation HTML. Any mismatch causes complete native fallback. This comparison protects unrelated HTML, text and ordinary Merge Tags; it also entails an additional render. Non-idempotent third-party Merge Tag callbacks may run twice and remain **not qualified**. No form/Entry/API/mail/feed/workflow state is written by the adapter.

Page/Redirect Query String evaluation is already complete before the hook. This batch deliberately keeps those transport paths native until their human-facing versus machine-valued parameters, escaping and :urlencode can be proven safe.

## Tag × modifier × confirmation-path evidence matrix

VERIFIED requires actual exact-package runtime/browser execution on this PR Head. NATIVE_FALLBACK means intentionally retained host semantics. BLOCKED identifies a specific unadmitted transport/host boundary. NOT_VERIFIED means no exact-Head execution.

| Tag and modifier | Text ordinary | Text AJAX | Page Query String | Redirect Query String |
| --- | --- | --- | --- | --- |
| date_created default, :format:Y/m/d, :format:Y-m-d H:i, :time | NOT_VERIFIED | NOT_VERIFIED | BLOCKED (native) | BLOCKED (native) |
| date_updated with valid UTC source | NOT_VERIFIED | NOT_VERIFIED | BLOCKED (native) | BLOCKED (native) |
| payment_date with valid UTC source | NOT_VERIFIED | NOT_VERIFIED | BLOCKED (native) | BLOCKED (native) |
| payment_date empty/missing | NOT_VERIFIED (native) | NOT_VERIFIED (native) | NATIVE_FALLBACK | NATIVE_FALLBACK |
| today default and supported :format / :time | NOT_VERIFIED | NOT_VERIFIED | BLOCKED (native) | BLOCKED (native) |
| Gregorian Date field :year, :month, :day, :ymd, :dmy and separator modifiers | NOT_VERIFIED | NOT_VERIFIED | BLOCKED (native) | BLOCKED (native) |
| :human and :diff | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK |
| :raw, :timestamp, entry:date_created, pgr_jalali_date | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK |
| Invalid/unsupported modifiers, malformed/out-of-range date, time-only value | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK | NATIVE_FALLBACK |
| URL :urlencode and repeated/reserved parameters | Not applicable | Not applicable | BLOCKED (native) | BLOCKED (native) |

:human relative and :diff durations stay host-owned; any absolute :human branch stays native. Native Date fields are explicitly distinct from already-Jalali pgr_jalali_date and arbitrary text. Date-only values are not timezone shifted. System dates are interpreted as UTC instants and converted after applying the WordPress site timezone. Native time and weekday semantics remain intact; unsupported calendar-dependent formatting symbols fail native rather than mixing calendars.

## Executable qualification

The existing WU008 workflow validates the exact private package and Head before installation, and the newly included G-011 paths trigger that workflow on PR. It now exercises real GFFormDisplay::handle_confirmation selection, Text, AJAX handler, Page/Redirect native controls, module toggle, en_US locale, raw GFAPI unchanged and global notifications negative control. Real Chromium ordinary/AJAX form submissions verify human-facing Jalali output and untouched unrelated text. PHPUnit separately tests bounded grammar, date ownership, timezone and fallback logic. No vendor archive or excerpt is permitted in CI artifacts.

**Do not upgrade any matrix cell to VERIFIED or claim CI/browser PASS without observing a successful exact-Head run.** Append exact run evidence after execution.

## Remaining G-011 outcomes

1. Human-facing Page/Redirect Query String conversion with correct :urlencode, comma, percent, delimiter and repeated-key behavior, while opaque transport parameters remain unchanged.
2. Additional exact-package Text branches not independently exercised: valid nonempty payment_date, supported modifiers outside the enumerated set, non-idempotent third-party Merge Tag callbacks, broad timezone/browser edges and the absolute :human branch.
3. Newer native Repeater Date children and mobile Datepicker behavior remain a separate Owner-supplied exact-package research opportunity, not implicit qualification here.
