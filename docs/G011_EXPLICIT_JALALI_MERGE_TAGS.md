# G-011 — Explicit Jalali Merge Tags (Owner-approved architecture, 2026-10-27)

## Form editor quick guide / راهنمای ویرایش فرم

In PersianGravity → Overview, enable the **System-date Jalali Presentation** module (OFF by default). In Gravity Forms → Forms → Edit → Settings → Confirmations, add dedicated tags to a **Text** Confirmation or to the Query String of **Page/Redirect** Confirmations. Do not rewrite the native tags automatically. Existing forms require manual editing.

در تنظیمات افزونه، ماژول «نمایش جلالی تاریخ‌های سیستمی» را فعال کنید؛ پیش‌فرض خاموش است. سپس در متن Confirmation فرم، تگ اختصاصی را تایپ کنید. تگ‌های قدیمی خودکار تغییر نمی‌کنند.

| Tag | Output / source |
| --- | --- |
| `{pgr_jdate_created}` | Entry creation date/time in site-local Jalali |
| `{pgr_jdate_created_date}` | Creation date only |
| `{pgr_jdate_created_time}` | Creation local time only |
| `{pgr_jdate_updated}` | Last Entry update date/time |
| `{pgr_jdate_updated_date}`, `{pgr_jdate_updated_time}` | Update local date/time parts |
| `{pgr_jdate_payment}` | Payment date/time; empty when absent |
| `{pgr_jdate_payment_date}`, `{pgr_jdate_payment_time}` | Payment local date/time parts |
| `{pgr_jdate_today}` | Current site-local Jalali date/time at rendering |
| `{pgr_jdate_today_date}`, `{pgr_jdate_today_time}` | Current date/time parts |
| `{pgr_jdate_field_2}` | Native Gregorian `GF_Field_Date` with field ID 2, civil calendar date only |

Example: `تاریخ ثبت: {pgr_jdate_created_date} — تاریخ انتخابی: {pgr_jdate_field_2}`

**Never use** `{pgr_jdate:2}`: the exact locked Gravity Forms host interprets colon-number as an ordinary field Merge Tag. Use `{pgr_jdate_field_2}` and replace **2** with the real Date field ID.

Output is Persian-facing only when `jalali_presentation` is enabled and site locale is `fa_IR`. Conversion reuses the G-008 typed calendar facade and its supported Gregorian range `1800-01-01..2124-03-19`. Entry timestamps are interpreted as UTC instants then converted to WordPress site time; native Date field values are Gregorian civil dates without timezone shifting. Other field types, including `pgr_jalali_date`, cannot be sent through the Gregorian converter.

A missing payment date yields an empty value. Invalid Gregorian data, unsupported years, wrong field IDs/types or malformed/unsupported tags remain visibly unresolved. No fallback grammar, background storage, second merge-tag render, HTML/source reconstruction, or Gregorian database/API conversion is introduced.

## Disable and missing-plugin expectations

Module disable performs an on-demand metadata-only `GFAPI::get_forms()` usage check. Saved custom-tag references block Disable as `USED`; unserializable or otherwise indeterminate form metadata is `UNKNOWN` (requires the existing explicit second confirmation), unless another form has confirmed `USED` (which takes precedence). Valid inspected forms without use are `UNUSED`. Remove dedicated tags from forms before disabling. If the module is OFF from an earlier configuration, its handler is not loaded; if the entire plugin is inactive/missing, the handler is also absent. Native GF 3.1.1.1 can leave unresolved custom tags visible. This is an accepted Owner decision, not a guarantee for every future host version. Disabling never deletes forms or entries.

## Current support and evidence boundary

| Path | Ownership | Current admission |
| --- | --- | --- |
| Selected default and conditional Text Confirmation | Gravity Forms selects; post-native handler replaces **only explicit tags** | Exact GF 3.1.1.1 real ordinary/AJAX Chromium submissions **PASS** on [Head 84267d0](https://github.com/rezahh107/PersianGravity/actions/runs/40649882507) |
| Notifications containing explicit tags | GF owns notification and transport; rendering hook may replace dedicated tags | Exact GF 3.1.1.1 synchronous GFAPI no-send notification **PASS**; external mail delivery NOT PROVEN |
| Native Merge Tags, raw/timestamp/relative values | Gravity Forms native | Intentionally not converted |
| Page/Redirect Query String | GF owns URL composition/encoding and destination | Exact GF 3.1.1.1 disposable WordPress/Chromium: Page + Redirect, ordinary + AJAX, enabled + disabled **8/8 PASS** with server-rendered destination receipts; duplicate/existing parameters, UTF-8, native tags and persisted Form/Entry verified in [WU008 #40649882507](https://github.com/rezahh107/PersianGravity/actions/runs/40649882507) |
| Gravity Forms 3.2+, nested Repeater children, other add-on contexts | Respective hosts | NOT QUALIFIED |

The October 23 original/refined G-011 and G011-POSTNATIVE-LOCK Revision 1.1 remain historical records. The October 27 Owner decision replaces *automatic Post-native conversion*, not independent product safety requirements. Page/Redirect destination behavior is now qualified **only** for the locked WordPress 6.8.3 / PHP 8.2 / GF 3.1.1.1 WU008 combinations above, not for newer GF packages, untested consumers or real production destinations. The complete 13-token and en_US tests are separately gated by WU008; their status must be read from the exact-Head artifact, not inferred from the earlier 8/8 browser result.

Prior evidence: [PR #84](https://github.com/rezahh107/PersianGravity/pull/84), [disposable PR #85](https://github.com/rezahh107/PersianGravity/pull/85), [licensed prototype WU008](https://github.com/rezahh107/PersianGravity/actions/runs/40555944091). These establish the candidate and baseline, not this production implementation's final runtime result.


## Post-merge implementation and proof — 2026-10-28

PR [#86](https://github.com/rezahh107/PersianGravity/pull/86) **MERGED**, final tested Head `cc1f88807e5dedeb560c647df62169eaafb37ed2`, main `06a5406a7b477a0db6485c0901ff894b53a3a0ca`. The earlier historical sentence describing the PR #84 proposal must not be read as current product status. Explicit tags are now production source in main; public release remains `v4.8.0` and deployment is not claimed.

Exact licensed GF 3.1.1.1 [WU008 #40653366355](https://github.com/rezahh107/PersianGravity/actions/runs/40653366355) SUCCESS: 29 token-matrix checks, Text browser 3/3, Page/Redirect real-browser destination 8/8 enabled/disabled, enabled-handler en_US fallback 13, native 9/9, no-send notification and preserved source data; [G008 #40653366365](https://github.com/rezahh107/PersianGravity/actions/runs/40653366365), [CI #40653366413](https://github.com/rezahh107/PersianGravity/actions/runs/40653366413) and [install smoke #40653366393](https://github.com/rezahh107/PersianGravity/actions/runs/40653366393) SUCCESS. Post-merge main CI and install smoke were also successful. Separate explicit-tag usage in third-party add-on contexts, newer GF packages, real live-site destination routes and actual outbound email delivery remain NOT QUALIFIED. Do not equate merged code or CI success with a public release.
