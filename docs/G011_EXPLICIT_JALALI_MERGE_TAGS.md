# G-011 — Explicit Jalali Merge Tags (Owner-approved architecture, 2026-10-27)

## Form editor quick guide / راهنمای ویرایش فرم

In PersianGravity → Overview, enable the **System-date Jalali Presentation** module (OFF by default). In Gravity Forms → Forms → Edit → Settings → Confirmations, add dedicated tags to a **Text** Confirmation. Do not rewrite the native tags automatically. Existing forms require manual editing.

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

Module disable performs an on-demand metadata-only `GFAPI::get_forms()` usage check. Saved custom-tag references block Disable as `USED`; unavailable/malformed form inspection follows the existing conservative `UNKNOWN` handling. Remove dedicated tags from forms before disabling. If the module is OFF from an earlier configuration, its handler is not loaded; if the entire plugin is inactive/missing, the handler is also absent. Native GF 3.1.1.1 can leave unresolved custom tags visible. This is an accepted Owner decision, not a guarantee for every future host version. Disabling never deletes forms or entries.

## Current support and evidence boundary

| Path | Ownership | Current admission |
| --- | --- | --- |
| Selected default and conditional Text Confirmation | Gravity Forms selects; post-native handler replaces **only explicit tags** | WU008 exact licensed GF 3.1.1.1 + ordinary/AJAX browser gate required |
| Notifications containing explicit tags | GF owns notification and transport; rendering hook may replace dedicated tags | WU008 synchronous isolated notification, no external email |
| Native Merge Tags, raw/timestamp/relative values | Gravity Forms native | Intentionally not converted |
| Page/Redirect Query String | GF owns URL composition/encoding and destination | **OPEN**: separate human-facing transport/destination qualification; no blanket claim |
| Gravity Forms 3.2+, nested Repeater children, other add-on contexts | Respective hosts | NOT QUALIFIED |

The October 23 original/refined G-011 and G011-POSTNATIVE-LOCK Revision 1.1 remain historical records. The October 27 Owner decision replaces *automatic Post-native conversion*, not independent product safety requirements, Text Confirmation/Notification validation, or the open Page/Redirect outcome. No G-011 DONE declaration while the latter remains unqualified.

Prior evidence: [PR #84](https://github.com/rezahh107/PersianGravity/pull/84), [disposable PR #85](https://github.com/rezahh107/PersianGravity/pull/85), [licensed prototype WU008](https://github.com/rezahh107/PersianGravity/actions/runs/40555944091). These establish the candidate and baseline, not this production implementation's final runtime result.
