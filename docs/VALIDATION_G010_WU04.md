# G-010 / WU-04 — Timeline Contract Replacement Validation

Date: 2026-09-27

Planning and actual starting `main`: `c4d0aa165c34a9e5b6fa879cd631a17fb47b2637` (merge commit of PR #71). No intervening canonical-main change was present when WU-04 started.

This document is the current WU-04 validation record. Earlier dated Timeline/Print validation sections remain historical evidence for their own exact Heads and exact runtime policies. Where those historical records say production Timeline activation required exact Flow/GF version equality or whole-file source fingerprints, WU-04 supersedes that runtime-policy wording only; it does not rewrite or weaken the exact-package evidence those records captured.

## Claim classes

### Production runtime contract

`PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter` may present a Timeline row only after the current request proves the capability-local contract:

- canonical `gravityflow/gravityflow.php` host identity;
- bounded syntactically valid Gravity Flow and Gravity Forms version observations as provenance only;
- nearest complete contiguous date-format, time-format and `date_i18n` Timeline caller chains;
- exact host argument vectors at `GFCommon::format_date()` and `Gravity_Flow_Common::format_date()`;
- same note object plus immutable note snapshot;
- same notes collection plus object identity/order;
- same Entry snapshot/key and form identity;
- strict authoritative UTC `note->date_created` semantics;
- correct initial-vs-stored event identity, including duplicate-timestamp separation by object/event identity;
- independently derived WordPress-site localized timestamp matching the host call;
- integer timestamp plus `$gmt === true` semantics;
- request-local unpredictable one-shot marker and same-note time context;
- replay, stale-context and nested/re-entrant ownership isolation;
- successful same-row Jalali calendar admission before visible time digits may be shaped.

Failure at any required invariant retains native output. Runtime activation does not read persisted compatibility diagnostics, localization `target_version`, exact package fingerprints or whole-file runtime hashes.

### Print inheritance

`gravityflow.print` has no independent calendar engine. Print is evaluated only when the same live admitted `Gravity_Flow_Entry_Detail::timeline()` invocation is actually enclosed by `Gravity_Flow_Print_Entries::render()`.

The nearest Timeline invocation owns attribution. A second/outer Timeline frame terminates Print-owner discovery, so a nested Timeline cannot borrow Print ownership from an outer render. If Print stops traversing Timeline, ordinary Timeline remains independently eligible and Print remains native/unclaimed.

### Synthetic falsification evidence

Focused automated tests intentionally vary version strings while preserving the behavioral contract, prove version equality is not the runtime oracle, and prove production source no longer contains runtime whole-file hashing/version constants. They also falsify caller-chain continuity, note/notes/Entry/form identity, duplicate timestamp identity, one-shot consumption, time-without-calendar admission and nested Print ownership borrowing.

These are synthetic architecture/falsification results. They do **not** qualify Gravity Flow `3.1.1.1`, any other real Flow release, another Gravity Forms release or an unsupplied package.

### Exact package/source qualification retained

`tools/compatibility/gravityflow-package.json` remains unchanged and authoritative for qualification:

- product: `gravityflow`
- version: `3.1.0`
- authority: `OWNER_SUPPLIED_GOOGLE_DRIVE`
- file ID: `1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf`
- expected bytes: `2603034`
- SHA-256: `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404`
- plugin main: `gravityflow/gravityflow.php`

WU008 continues to hash-verify the exact package and reconcile exact Flow Entry Detail, Flow Common, Flow Print and Gravity Forms Common source fingerprints. A qualification fingerprint mismatch remains a CI failure even though fingerprint equality is not a permanent production runtime activation condition.

## Exact-final-Head completion gates

WU-04 is not runtime-verified merely because unit/static tests pass. Merge eligibility requires successful execution on the same final PR Head for:

1. `CI`;
2. `Artifact Install Smoke`;
3. `G008 Jalali Presentation Runtime`;
4. `WU008 Licensed Real Integration`;
5. any G006 lane actually triggered by the final diff.

The final implementation report must name the exact final Head and run IDs/statuses. If a required authentic lane cannot execute on that Head, the affected runtime claim remains `NOT_PROVEN` and the PR remains unmerged.

## Preserved boundaries

WU-04 does not change the WU-03 Entry Detail four-date exact repository-target-version safety exception or its future Owner-supplied-package requalification protocol. It does not change Inbox/Status contracts, Entry Detail Persian-digit eligibility, Status `due_date`, Entry Detail Scheduled, Gravity Forms/GravityView adapters, Jalali arithmetic/range, localization target-version policy, canonical package authority, vendor/updater files, plugin version/Stable tag, release/tag/deployment state, GPP or WU-05.

Database/API/query/sort/export/workflow/deadline/schedule/expiration non-interference remains established only to the extent exercised by the authentic exact-package G008/WU008 lanes. No broader operational claim is inferred from static/unit evidence.
