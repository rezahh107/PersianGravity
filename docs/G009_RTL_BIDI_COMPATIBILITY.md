# G-009 — Gravity ecosystem Persian/RTL compatibility authority

## Scope and ownership

PersianGravity owns only bounded Persian/RTL compatibility for explicitly admitted Gravity ecosystem products and exact admitted versions. WordPress locale resolution and `is_rtl()` remain authoritative. Vendor-native RTL is preferred; a surface that works natively remains adapter-free.

PersianGravity does not own Gravity Flow workflow/data/assignment/search/sort/filter/paging/navigation/permissions/state, AG Grid state machinery, GravityView business behavior, GPP presentation, or theme/page-width layout. Vendor packages/updaters are immutable. There is no site-wide direction reset and no future-version compatibility promise. This batch does not expand GravityView-specific qualification; its pre-existing representative regression remains only a preservation check.

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

## Current representative dispositions

The current exact-package/current-Head WU008 qualification establishes these initial surface states:

- Gravity Forms 3.1.1.1 authentic frontend form: `NATIVE_PASS` for the exercised desktop and narrow RTL surfaces. Computed form/input direction, geometry, focus/Tab behavior and mixed Persian + ID/email token entry were exercised; the LTR control must independently remain native LTR.
- Gravity Flow 3.1.0 authentic frontend Inbox/AG Grid seam: `NATIVE_PASS` for the exercised desktop and narrow visible grid surface. The grid remains vendor-owned; no PersianGravity grid/search/filter/pager/state implementation is introduced.
- GravityView 3.3.4 native admin list table: `NATIVE_PASS` for the exercised desktop and narrow table geometry/direction. A small WordPress admin-shell horizontal overflow is retained as host evidence rather than reclassified as a GravityView-owned failure.
- Gravity Forms `gform_admin` reachability on the authentic Flow frontend request: `NOT_PROVEN` for production repair. Exact source/runtime reachability and direction causality are supported, but no material defect was observed on the exercised visible Inbox/AG Grid seam and stylesheet necessity/safe permanent repair seam are not established.
- Gravity Perks 2.3.16 exact admitted family baseline: `NATIVE_PASS` for the exercised combination of GP File Upload Pro 1.5.13 and GP Advanced Select 1.1.21 only. All three packages are exact hash/version/root verified and active in the same disposable runtime; no other Perk or future version is implied.
- GP File Upload Pro 1.5.13 authentic frontend: `NATIVE_PASS`. The exact installed source probe binds the real PHP gettext → `wp_localize_script()` path, and the browser renders `انتخاب فایل‌ها`, `فایل‌ها را اینجا رها کنید` and `یا` under fa_IR/RTL while en_US remains native English. The real mixed filename `گزارش-ID-1234.txt` is preserved with a BiDi-isolated filename node.
- GP Advanced Select 1.1.21 authentic Tom Select: `ADAPTER_REQUIRED_AND_VERIFIED`. The exact `gp-advanced-select-tom-select` handle and `.ts-wrapper.gfield_select.single.plugin-change_listener.rtl` state are present. The bounded adapter moves the caret to the RTL side and computes a 36px inline-end reserve versus 12px inline-start at both viewports; typing, search, ArrowDown/Enter selection and focus remain usable. The LTR control has no PersianGravity inline style and remains native.

These are not product-wide future-version claims. Each state is bounded by the exact version/package/surface contract recorded in the registry.

## Existing GP Advanced Select precedent

`PGR_Gravity_Perks_RTL` remains the only executable Gravity Perks RTL compatibility adapter. Its production gate is now the conjunction of exact GP Advanced Select `1.1.21`, effective `fa_IR`, WordPress RTL, and the exact registered `gp-advanced-select-tom-select` handle. Version/locale/RTL/handle absence fails closed before any inline CSS is attached.

Exact-package runtime showed the vendor sets RTL on the wrapper, specifically `.ts-wrapper.gfield_select.single.plugin-change_listener.rtl`, while its bundled direction selectors expect `.ts-control.rtl` and its single-select caret remains right-pinned. The first physical-padding repair moved the caret but did not reserve the mirrored text gap in the real browser. The final bounded rule therefore scopes to the authentic GP Advanced Select wrapper and uses logical `padding-inline-start/end`; exact browser evidence confirms 12px start / 36px end in RTL. This does not target unrelated Tom Select instances.

## WU008 qualification boundary

G-009 extends the existing WU008 real-integration lab instead of creating a second browser environment. The lab installs PersianGravity from the deterministic production ZIP built from the exact checked-out PR Head and binds evidence to that source commit/tree and ZIP SHA-256. Exact Gravity Forms, Gravity Flow, GravityView, Gravity Perks, GP File Upload Pro and GP Advanced Select packages are all verified before extraction; licensed ZIPs and extracted source remain outside uploaded artifacts.

The browser lane runs independently attributable `fa_IR`/RTL and effective WordPress `en_US`/LTR-control profiles. Structured evidence records computed direction, relevant alignment/padding, responsive geometry/overflow, focus/keyboard observations and the exact runtime identities. Screenshots remain supplemental artifacts.

The Gravity Flow frontend Inbox is reached through the authentic `[gravityflow page="inbox"]` shortcode. A disposable browser experiment may disable and restore the observed `gform_admin` stylesheet solely to establish CSS causality; this is qualification-only behavior and is never a production dequeue/override.

## `gform_admin` disposition

Exact Gravity Flow 3.1.0 source contains the frontend reachability path that results in Gravity Forms `gform_admin` being available/enqueued for the Inbox request; exact Gravity Forms 3.1.1.1 source owns the registered `gform_admin` stylesheet and its admin CSS.

On the authentic `fa_IR` frontend Flow Inbox request, the stylesheet is present and `html/body` compute LTR even though the document carries `dir="rtl"`. In the disposable qualification experiment, disabling only that loaded stylesheet changes computed root direction to RTL, and restoring it returns the roots to LTR. This supports CSS causality.

The exercised visible Inbox/AG Grid surface nevertheless computes RTL, remains inside both representative viewports and retains basic focus behavior. Dynamically observed interactive descendants were also recorded; no material visible defect was observed in those exercised states. The batch does not establish that `gform_admin` is unnecessary to every control, popup, dialog or future dynamically inserted state, nor does it establish an official narrow permanent removal/override seam.

Therefore the registry state remains `NOT_PROVEN` for a production repair, with the exact-version disposition closed as `NO_REPAIR_NO_ADMISSION`: no dequeue, no global RTL override and no G-009 production adapter is added for this interaction.

## Current registered products

The registry is intentionally bounded to the currently admitted exact packages:

- Gravity Forms 3.1.1.1
- Gravity Flow 3.1.0
- GravityView 3.3.4
- Gravity Perks 2.3.16
- GP File Upload Pro 1.5.13
- GP Advanced Select 1.1.21

No uninspected Gravity Perks product is implied by family membership.
