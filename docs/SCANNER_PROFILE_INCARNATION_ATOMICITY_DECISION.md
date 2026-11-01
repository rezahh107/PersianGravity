# Scanner Profile deletion — incarnation and atomicity decision (qualification only)

Status: **OWNER DECISION REQUIRED — NO PRODUCTION PATCH**  
Verified merged baseline: `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7` (2026-10-31).
Current merged main independently rechecked (2026-11-01): `ff3f390511da3ce194815a2c856251ade145b2cc` (merged qualification PR #104; **production Registry/admin behavior unchanged**).  
Authoritative repository: `rezahh107/PersianGravity`.  
This document does not authorize a storage migration or UI/business-rule change.

## Observed source facts

- `PGR_Scanner_Profile_Registry::SCHEMA_VERSION = 1`; a single non-autoloaded `pgr_scanner_profiles` option contains `schema_version` and `profiles`. `validate_profile()` constructs a fixed normalized shape and discards unknown metadata. `normalize_option()` validates and reconstructs every stored profile. No retained per-incarnation identity exists.
- `snapshot_fingerprint()` hashes the validated canonical content, not instance lifetime or edit history. The confirmed delete UI binds its real WordPress nonce to ID + fingerprint and verifies its label and fingerprint on POST.
- The admin POST calls `Registry::get()` and *then* `Registry::delete_custom_profile(id)`. The latter re-reads the option and filters by **ID only**. `persist_option()` uses `add_option()` / `update_option()` and ignores their result, while save/delete report success unconditionally after attempted persistence.
- All Registry writes are read/modify/write operations on the whole option. No atomic expected-value precondition is supplied to the DB mutation. Normal WordPress `update_option()` writes by `option_name`, not by `option_name + expected old option_value`; `get_option()` can return cached data.
- Built-in profiles are source-defined, `read_only`, and explicitly denied by the delete method. Existing Gravity Forms mappings refer to stable public profile IDs and are not rewritten on deletion.

Source: `includes/class-pgr-scanner-profile-registry.php`, `admin/class-pgr-admin.php`, `tests/ScannerProfileDeleteDecisionTest.php`, the WU008 real-integration fixtures and the exact source SHA above. WordPress option behavior should be re-verified against the pinned WP 6.8.3 runtime before production code is chosen.

## Two independent failure mechanisms

1. **Same-ID/content replacement (ABA):** A is confirmed, deleted, then B is created with the same ID and normalized definition. The fingerprint and nonce action are indistinguishable. Authentic [WU008 incarnation evidence](https://github.com/rezahh107/PersianGravity/blob/eab6ba4e8fa0a282fa399034d4f4700304d5aefd/scanner-incarnation-evidence/report.json) shows the stale A POST deleting B. Different-content replacements and unchanged A are existing negative/positive controls.
2. **Check/delete interleaving (TOCTOU):** After the handler checks a fresh snapshot, another writer may replace the ID before `delete_custom_profile()` reads it. Even a new UUID checked only in the handler would not protect the later ID-only deletion. A deterministic, genuine-Registry interleaving test is added to WU008 by this qualification branch. Such a hook test establishes the execution-order vulnerability, **not** an actual two-worker concurrent transaction.

A content hash, shorter nonce lifetime, extra UI confirmation, or a transient used only at POST validation does not close both mechanisms. Merely adding an ID token to the UI does not create atomicity.

## Bounded alternatives

| Candidate | Incarnation distinction | Check/write atomicity | Compatibility and risk |
| --- | --- | --- | --- |
| A. Content fingerprint / shorter or one-shot UI nonce | **No** for identical recreations | **No** | Preserves v1, insufficient |
| B. Separate generation sidecar option or transient | Possible if every canonical create/delete updates it | **No** without additional coordination | Cross-option partial writes, cache divergence, legacy initial state, restore/rollback semantics; introduces new persistence contract |
| C. Versioned identity in canonical Registry plus guarded conditional write | **Yes** (random immutable incarnation ID for every create; independent edit/revision or snapshot check) | **Potentially yes**, provided **all Registry writes** use one atomic expected-state protocol | Requires explicit schema/migration decision and careful WP cache, option filters, failure, restore and rollback work |
| D. Advisory lock around Registry methods | Could coordinate Registry participants but does not itself invent identity | Only for cooperating writers, and only with fresh reads/correct lock lifetime | Host/MySQL portability and external `update_option()` writers remain unresolved; lock alone insufficient |

**Recommended conditional design if authorized:** C, with a single canonical versioned source of truth rather than an uncoordinated sidecar. Compare the presented profile's incarnation ID *and* complete confirmed snapshot; make the delete a conditional operation on the same expected canonical option state (or an equally proven host-compatible serialized write protocol). Both save and delete must reject conflicting writes and must not return success on failed storage. Random IDs must be generated at **creation**, not from content. A fresh confirmation for B must still delete B. Retain the exact existing confirmation visual/wording and all capability/nonce/built-in restrictions.

This is **not** authorization to use raw SQL or a migration by default. If DB-level compare-and-swap is selected, qualify its use of `$wpdb->prepare()`, exact serialized comparison, affected-row/no-op behavior, WordPress option filters/actions, and the `options` / `notoptions` / `alloptions` caches (including persistent object cache). An unconditional cache delete after a conditional DB write does not by itself prove linearizability of concurrent WordPress writes. Check external option writers and arbitrary option filters, and fail closed if the required guarantees cannot be established. An alternative lock design must demonstrate the same invariants in the admitted hosting environment.

## Legacy, migration and rollback boundaries to decide

1. Existing schema-v1 profiles lack incarnation metadata. Choose and authorize an **explicit, idempotent v1→v2 migration** with versioned normalized reader/writer and conditional re-read/commit; any pre-migration A confirmations must fail closed after migration. Do not fabricate stable identities by hashing old profile content.
2. Existing runtime field mappings, parser/output contract, immutable profile ID, display metadata, `sayad_v01`, non-autoloading, user locale and `GFAPI` data must remain unchanged. Decide whether older plugin rollback is *blocked pending export/restore* or formally supported with a documented lossless downgrade path; current v1 normalizer will strip unknown per-profile fields and cannot safely preserve an upgraded identity.
3. Define behavior for failure after lock/CAS/migration initiation, update failures, repeated POSTs, and plugin restores/backups. Decide whether external direct writes to the option are supported or explicitly outside the API contract.
4. The existing `save_custom_profile()` / `delete_custom_profile()` return shapes and write success semantics need narrow correction, not blanket refactoring. Test mixed writers and failure feedback before acceptance.

## Owner / Project Manager authorization requested (minimum)

**Approve or reject:** (1) a canonical versioned Scanner Profile incarnation/revision persistence contract with a narrowly defined migration of existing v1 profiles and explicit rollback policy; and (2) an atomic conflict-detecting Registry write/delete protocol that may require bounded direct DB conditional updates and WordPress cache coordination, only after authentic disposable host proof. Continue to preserve current admin UI, WordPress permission/nonce behavior, built-ins and Gravity Forms mappings. If one of these is denied, the two demonstrated safety gaps cannot honestly be declared closed by a fingerprint-only patch.

## Validation and acceptance gate

- Existing eight genuine WU008 scenarios and native deletion page tests still execute: unchanged, missing, changed label, same label with different executable definition, enabled-only edit, output-label-only edit, fresh B, identical-content B.
- Qualification test must reproduce the check/delete interleaving using an isolated **test-only** WordPress hook, genuine Registry APIs and preserved GFAPI mapping. This test demonstrates **vulnerability**, not a repair. Distinct-worker concurrency remains NOT_PROVEN.
- A later **production** candidate must reverse both negative outcomes: identical-content B survives stale A POST; changed B survives even when mutation occurs at the check/delete boundary; fresh B deletes; unchanged A deletes; a changed profile fails closed; failed writes are never reported as success. A genuinely concurrent two-writer persistent-cache experiment is required before declaring concurrency resolved.
- For the candidate, re-run permission, nonce, cancellation, replay, built-in, GFAPI, fa_IR/en_US, 1280/390/320, focus/keyboard/no-JS and inspect genuine before/after screenshots. Existing qualified screenshots are evidence of **current** appearance only, not acceptance of any changed production Head.
- Publish measured runtime evidence on a run-specific evidence branch with **exact tested Head** and source-bound artifacts; do not infer PASS from this document, source inspection, or a pending workflow.

**Release/merge/deploy:** none authorized by this qualification.

## Post-merge qualification checkpoint (2026-11-01)

**Status remains OWNER DECISION REQUIRED. This checkpoint is not a production fix.** Merged main is `ff3f390511da3ce194815a2c856251ade145b2cc`; compared with the earlier production-reviewed `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7`, only the five qualification/doc/test paths changed. Registry and admin handler source blobs are unchanged: `448482be6c32d35f4183560dbdefc79a4a5fee2c` and `f7943ef27a7ee5159b72664ddec8ce4128227bcd`, respectively.

The existing **exact-Head WU008 run** [41234928792](https://github.com/rezahh107/PersianGravity/actions/runs/41234928792) succeeded on source Head `f1503c2e060e349708bb645bf12d2f2e4b4b6212`. Its [published immutable-commit evidence](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/report.json) independently read back on this checkpoint contains the eight expected cases, 24 JPEGs, unchanged mapped GFAPI data and the deterministic test-only interleaving marker. **Negative controls remain unsafe:** stale A deletes identical-content B; stale A also deletes changed B after injection at the Registry's second option read. Injected mutation only: **1.404 ms**; this is not two-worker concurrency.

The previous results include positive controls for unchanged A deletion, missing/changed B refusal, enabled/output-label edits, and fresh B deletion. Existing WU008 deletion security/visual evidence covers the original fa_IR/en_US, 1280/390/320, keyboard/focus/no-JS, permission/nonce/replay/cancellation/built-in scenarios, but **they were not rerun in this session**. The exact current merged main was not subjected to a new licensed WordPress execution. No changed production screenshots are needed because no production source/UI was changed.

**Unproved acceptance work:** independent concurrent workers; persistent object cache and external option-writer behavior; host-option filter/action semantics under conditional writes; failed-write and recovery behavior; migrated legacy profiles, rollback/downgrade and restored backup safety. None can be labeled PASS from a single-request hook.

**Decision requested, unchanged:** authorize explicit canonical incarnation identity/versioned migration with defined v1 handling and rollback, plus a genuinely guarded conflict-detecting Registry write/delete operation, only after authentic disposable WordPress validation. Preserve the approved deletion screen and WordPress security controls without widening the repair.

[Post-merge evidence branch](https://github.com/rezahh107/PersianGravity/tree/evidence/scanner-incarnation-postmerge-20261101/scanner-incarnation-postmerge-evidence) contains the source-SHA-bound readback report and measured negative outcomes. It is not a runtime patch.
