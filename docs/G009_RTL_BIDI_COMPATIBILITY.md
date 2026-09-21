# G-009 — Gravity ecosystem Persian/RTL compatibility authority

## Scope and ownership

PersianGravity owns only bounded Persian/RTL compatibility for explicitly admitted Gravity ecosystem products and exact admitted versions. WordPress locale resolution and `is_rtl()` remain authoritative. Vendor-native RTL is preferred; a surface that works natively remains adapter-free.

PersianGravity does not own Gravity Flow workflow/data/assignment/search/sort/filter/paging/navigation/permissions/state, AG Grid state machinery, GravityView business behavior, GPP presentation, or theme/page-width layout. Vendor packages/updaters are immutable. There is no site-wide direction reset and no future-version compatibility promise.

The source-owned evidence registry is `tools/compatibility/g009-surfaces.json`. It is development/qualification data, not a runtime registry, service container, feature framework, or project-management system. Production does not load it.

## Evidence model

The only surface evidence states are:

- `NATIVE_PASS`
- `ADAPTER_REQUIRED_AND_VERIFIED`
- `NOT_PROVEN`
- `NOT_APPLICABLE`
- `FAIL_CLOSED_VERSION_DRIFT`

A source/unit observation is not browser qualification. A screenshot is supplemental evidence only. A green CI job proves only the exact scenarios recorded by that job.

Each registered product binds an exact admitted version and package SHA-256. Each registered surface records its authentic request/surface identity, RTL relevance, native expectation, relevant vendor handles, DOM/state signature when needed, current evidence ceiling, provenance, and drift behavior. Unexecuted surfaces remain `NOT_PROVEN`.

## Bounded adapter contract

A production adapter is admitted only when evidence establishes the narrowest supported seam and the strongest practical conjunction of gates. Depending on the vendor surface this can include:

1. effective WordPress locale and `is_rtl()`;
2. exact admitted product/version;
3. exact vendor-owned CSS/JS handle;
4. authentic request/surface identity;
5. source-proven DOM/state signature where vendor runtime state matters.

Logical CSS properties are preferred. Legitimate LTR/BiDi islands such as URLs, email addresses, IDs, codes and technical tokens must not be forced RTL merely because the surrounding interface is Persian.

A version/handle/signature mismatch invalidates the corresponding qualification and must fail closed. It never silently extends authority to a future vendor version.

## Existing GP Advanced Select precedent

`PGR_Gravity_Perks_RTL` remains the only existing executable Gravity Perks RTL compatibility adapter. Its source-proven contract is limited to effective `fa_IR` + WordPress RTL plus the exact registered `gp-advanced-select-tom-select` handle and the `.ts-wrapper.rtl` vendor state that motivated the bounded CSS correction.

The exact GP Advanced Select 1.1.21 package is admitted for G-007 source/localization authority, but the current WU008 delivery lane does not provision its exact licensed bytes. Therefore this batch does not promote the adapter to browser-verified G-009 support. Exact-package browser requalification stays `NOT_PROVEN` until those bytes are safely available to the lab.

## WU008 qualification boundary

G-009 extends the existing WU008 real-integration lab instead of creating a second browser environment. The lab now installs PersianGravity from the deterministic production ZIP built from the exact checked-out PR Head and binds evidence to that source commit/tree and ZIP SHA-256. The existing exact Gravity Forms, Gravity Flow and GravityView package verification remains intact.

The browser lane runs independently attributable `fa_IR`/RTL and `en_US`/LTR-control profiles. Structured evidence records computed direction, relevant alignment/padding, responsive geometry/overflow, focus/keyboard observations and the exact runtime identities. Screenshots remain supplemental artifacts.

The Gravity Flow frontend Inbox is reached through the authentic `[gravityflow page="inbox"]` shortcode. A disposable browser experiment may disable and restore the observed `gform_admin` stylesheet solely to establish CSS causality; this is qualification-only behavior and is never a production dequeue/override.

## `gform_admin` disposition rule

The observed presence of Gravity Forms admin CSS on a Gravity Flow frontend request is an evidence input, not a repair authorization. Qualification separately asks:

- how the exact Gravity Flow 3.1.0 source reaches `gform_admin`;
- whether the exact frontend request actually loads it;
- whether toggling only that stylesheet changes computed root direction;
- whether a material visible control, popup/dialog, geometry, focus, keyboard or accessibility-visible behavior is broken;
- whether the stylesheet is required by the UI and whether an official narrow seam exists.

CSS causality alone does not justify dequeueing `gform_admin` or adding a global RTL override. If material effect or a safe supported repair seam is not proven, the repair disposition remains `NOT_PROVEN`.

## Current registered products

The registry is intentionally bounded to the currently admitted exact packages:

- Gravity Forms 3.1.1.1
- Gravity Flow 3.1.0
- GravityView 3.3.4
- Gravity Perks 2.3.16
- GP File Upload Pro 1.5.13
- GP Advanced Select 1.1.21

No uninspected Gravity Perks product is implied by family membership.
