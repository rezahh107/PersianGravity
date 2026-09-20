# G-008 — Jalali System-Date Presentation V1

## Scope and ownership

`jalali_presentation` is an independently gated, opt-in PersianGravity module. It presents authoritative Gregorian/system dates as Jalali on explicitly admitted UI surfaces. It does not change stored values, API values, sorting/filtering keys, workflow state, or the semantics of `pgr_jalali_date`.

`jalali_date` remains the owner of true Jalali-domain user input and canonical Jalali storage. `jalali_presentation` never activates because a `pgr_jalali_date` field exists and never sends that field's stored value through Gregorian-to-Jalali conversion.

V1 admits one host surface only: Gravity Forms Entries List `date_created`.

## Runtime topology

```text
PGR_Module_Registry: jalali_presentation (default OFF)
        ↓
PGR_Gregorian_Jalali_Converter
        ↓
PGR_Jalali_Presentation
        ↓
PGR_GF_Jalali_Presentation_Adapter
        ↓
gform_entries_field_value / date_created only
```

The converter is pure calendar arithmetic. It has no WordPress, Gravity Forms, timezone, formatting, UI-hook, or arbitrary-string responsibilities.

The public presentation capability is feature-detectable through `PGR_Jalali_Presentation::format_datetime()`. Its primary input is `DateTimeInterface`; callers must establish source timezone semantics before calling. `format_date()` is the bounded date-only companion and never applies timezone shifting.

## Algorithm provenance

Production arithmetic follows the Borkowski lineage from:

- Kazimierz M. Borkowski, “The Persian Calendar for 3000 Years”, *Earth, Moon and Planets* 74 (1996), 223–230.
- PHP adaptation source: `jalaali-js` 2.0.1, commit `7ff10a0a4145c84a6911e87bfacf40ddf51a2adc`, MIT license.

The required upstream MIT notice is shipped in `includes/jalali-presentation/LICENSE.jalaali-js.txt`. The production package has no Node, `jalaali-js`, `ext-intl`, or third-party Jalali runtime dependency.

No 2820-year-cycle algorithm, naive four-year rule, runtime `ext-intl`, or undocumented Internet snippet is used.

## Range contract

Three evidence ranges are intentionally distinct:

- **Underlying Borkowski/jalaali-js arithmetic range:** Jalali years `-61..3177`. This is an algorithm capability, not a PersianGravity product-support promise.
- **REFERENCE_CROSSCHECK_RANGE:** Gregorian years `1800..2256`, recorded exactly as the current `jalaali-js` reference implementation documents its agreement with `Intl`.
- **VALIDATED_PRODUCT_RANGE:** Gregorian civil dates `1800-01-01..2124-03-19` inclusive.

For `VALIDATED_PRODUCT_RANGE`, the shipped PHP engine is exhaustively checked over `118,417` dates against both the locked `jalaali-js` 2.0.1 reference arithmetic and an independent Unicode ICU Persian Calendar oracle through Node `Intl`. A separate exhaustive PHP property check performs Gregorian → production Jalali → production Gregorian round-trip consistency over the same 118,417-day range; round-trip is consistency evidence, not official-calendar proof.

The product range stops immediately before the first observed divergence with the current independent ICU oracle: on `2124-03-20`, Borkowski/jalaali-js returns `1502-12-30`, while ICU 77.1 returns `1503-01-01`. This does not rewrite or dispute the upstream documented `REFERENCE_CROSSCHECK_RANGE`; it is why PersianGravity does not silently promote that broader reference claim into its V1 product-supported range.

Outside `VALIDATED_PRODUCT_RANGE`, the facade returns no Jalali presentation and adapters retain native output.

## Official golden evidence

Golden fixtures are kept separately from differential/reference tests. The current fixture records the University of Tehran Institute of Geophysics Calendar Center's final 1405 calendar provenance and includes the mandatory official anchor:

`2026-03-21 → 1405-01-01`

It also includes published 1405 month-boundary cases from the same official calendar. These official cases validate those published dates only; they are not described as official coverage for the full product range.

Official source identity:

`https://calendar.ut.ac.ir/documents/2139738/7092644/Calendar-1405.pdf`

## Timezone contract

For `DateTimeInterface` instants:

1. the input already represents an authoritative instant with explicit source timezone;
2. the facade applies the explicit target timezone, or `wp_timezone()` when appropriate;
3. it extracts local Gregorian date/time;
4. only the local Gregorian Y/M/D is calendar-converted;
5. local time-of-day is preserved in the output.

Gravity Forms `date_created` is parsed by its host adapter as strict UTC `Y-m-d H:i:s`, matching the documented Entry Object contract. No PHP default timezone, hard-coded Iran offset, `strtotime()`, or year/string heuristic is used.

Date-only calls do not timezone-shift.

## Output and fallback

V1 uses one Persian-facing numeric profile, for example:

`۱۴۰۵/۰۶/۲۵، ۰۷:۳۴`

Native output is preserved when the module is disabled, source semantics are unsupported or malformed, target timezone is unavailable, the date is outside `VALIDATED_PRODUCT_RANGE`, conversion fails, or the host surface is not admitted.

## Gravity Forms V1 adapter

The adapter registers only `gform_entries_field_value`. It acts only when the Entry List identifies the property as `date_created`, and it parses `entry['date_created']` rather than the already-formatted `$value`. This keeps host parsing bounded and prevents arbitrary display strings or `pgr_jalali_date` values from entering conversion.

Gravity Forms documents `date_created` as a UTC datetime and `gform_entries_field_value` as an Entry List display filter. The exact-package runtime workflow additionally asserts, against Gravity Forms `3.1.1.1`, that the real Entry List sends `date_created` through that seam and that the rendered Entries List HTML contains the expected Jalali presentation.

The raw Entry remains authoritative Gregorian UTC. No save/update/export/global WordPress date filter is registered by G-008.

## Verification

Repository tests cover official golden cases, Nowruz/Esfand/leap/month/year boundaries, malformed dates, product-range boundaries, deterministic repeatability, timezone day-crossings and historical IANA behavior, opt-in module state, all four `jalali_date` / `jalali_presentation` state combinations, native fallback, and existing Jalali-domain regression behavior.

`tests/js/g008-jalali-oracles.test.js` provides reproducible exhaustive differential/property verification. `.github/workflows/g008-jalali-presentation-runtime.yml` performs exact-package Gravity Forms `3.1.1.1` source/runtime proof without shipping the licensed package or adding any production dependency.
