# G-010 / WU-04 — Timeline Contract Replacement

Status: implementation candidate; final admission depends on exact-final-Head CI and licensed WU008 evidence.

Planning and actual starting `main`: `c4d0aa165c34a9e5b6fa879cd631a17fb47b2637` (PR #71 merge commit). No intervening mainline change was present when WU-04 started.

## Runtime compatibility authority

The Gravity Flow Timeline adapter no longer treats exact Gravity Flow `3.1.0`, exact Gravity Forms `3.1.1.1`, or whole-file source SHA-256 values as production activation authorities. Runtime admission is capability-local and requires all of the following:

1. canonical Gravity Flow plugin basename `gravityflow/gravityflow.php`;
2. bounded syntactically valid Flow and Gravity Forms version observations (provenance only, never equality/range/whitelist authority);
3. the nearest complete contiguous Timeline caller chain for date-format, time-format and `date_i18n` seams;
4. the qualified exact argument vectors for `GFCommon::format_date()` and `Gravity_Flow_Common::format_date()`;
5. the same note object and immutable note snapshot across the owned path;
6. the same notes collection and object order, including duplicate-timestamp rows identified by object/event identity rather than timestamp alone;
7. the same Entry snapshot/key and form identity;
8. strict authoritative UTC `note->date_created` semantics plus initial-vs-stored event identity;
9. an independently derived WordPress-site localized timestamp matching the host call, with integer timestamp and `$gmt === true` semantics;
10. a request-local unpredictable one-shot date marker plus a same-note one-shot time context;
11. stale/replayed/re-entrant ownership isolation; and
12. successful Jalali calendar admission before ASCII time digits may be shaped to Persian digits.

Any failed invariant returns native output. No database, GFAPI, REST, query, sort, export, workflow, deadline, scheduling or expiration authority is modified.

## Guard replacement map

| Previous runtime guard | Safety property it proxied | WU-04 replacement | Disposition |
| --- | --- | --- | --- |
| Flow version exactly `3.1.0` | expected host family/path | canonical basename + bounded version observation + exact behavioral caller/data contract | remove as activation authority |
| Gravity Forms version exactly `3.1.1.1` | expected GF formatting behavior | authentic `GFCommon` caller/argument/timestamp contract | remove as activation authority |
| Flow Entry Detail whole-file SHA | exact Timeline renderer shape | nearest contiguous named caller chain + arguments + note/notes/Entry/form/event identity | remove from runtime; retain CI qualification |
| Flow Common whole-file SHA | exact Flow formatting delegation | exact `Gravity_Flow_Common::format_date()` caller and argument vector | remove from runtime; retain CI qualification |
| GF Common whole-file SHA | exact GF date/time behavior | exact `GFCommon::format_date()` paths, timestamp/GMT semantics and matched formats | remove from runtime; retain CI qualification |
| Flow Print whole-file SHA | current Print reuses Timeline | live Print renderer must actually contain the same admitted Timeline stack | remove from ordinary Timeline runtime; retain CI qualification |

The replacement does **not** introduce a version range, whitelist, method hash, source-line/line-number dependency, persisted diagnostic oracle or generic compatibility service.

## Print boundary

`gravityflow.print` has no independent calendar engine. Print is attributed only when the current stack proves `Gravity_Flow_Print_Entries::render()` is outside the same live `Gravity_Flow_Entry_Detail::timeline()` invocation whose row contract is being admitted. The Print flag is stored in the one-shot row context and must match at the date/time consumption seams.

The nearest Timeline invocation owns Print attribution. Owner discovery stops at another outer Timeline frame, so a nested/re-entrant Timeline cannot borrow Print ownership from a more distant outer render.

Therefore:

- ordinary Timeline remains independent of Print source identity;
- a Print path that no longer traverses the admitted Timeline stack receives no inherited conversion or fabricated success;
- Print cannot borrow a marker/context from a non-Print or outer Timeline invocation;
- a Print-specific failure does not create a product-wide compatibility switch.

## Diagnostics

Diagnostics stay downstream reporting only. The adapter writes request-local observations for `gravityflow.timeline-history`, and for `gravityflow.print` only when Print is actually present in the evaluated Timeline path.

Successful evaluated contract: `AVAILABLE / PGR-GFLOW-CONTRACT-SATISFIED`.

Failures reuse the existing bounded reasons (`HOST_UNQUALIFIED`, `SEAM_UNAVAILABLE`, `CONTEXT_UNAVAILABLE`, `SOURCE_INVALID`, `CONVERSION_UNAVAILABLE`). Persisted/latest diagnostics are never read to activate presentation.

## Qualification authority retained in CI

WU-04 intentionally leaves `tools/compatibility/gravityflow-package.json` unchanged. The Owner-supplied Flow `3.1.0` package remains the exact qualification authority. WU008 still checks the exact package SHA/version and records/reconciles the four whole-file Timeline/Print/GF source fingerprints. Source-hash drift therefore remains a CI qualification failure even though it is no longer a production runtime veto by itself.

Synthetic version-only drift tests demonstrate architecture behavior only. They do **not** qualify Gravity Flow `3.1.1.1`, another Flow release, another Gravity Forms release, or any unsupplied package.

## Preserved boundaries

WU-04 does not alter the Owner-approved WU-03 Entry Detail four-date exact-target-version safety exception or its future Owner-supplied package requalification protocol. Inbox/Status compatibility contracts, Entry Detail Persian-digit shaping, Status `due_date` and Entry Detail Scheduled dispositions, Gravity Forms/GravityView adapters, localization `target_version` policy, package manifest, plugin version, release/tag/deployment state and WU-05 remain outside this change.

Validation claim classes and exact-final-Head completion gates are recorded in [`VALIDATION_G010_WU04.md`](VALIDATION_G010_WU04.md).