# Scanner Profile — post-merge source-bound qualification (2026-11-01)

**No production fix. No new WordPress execution in this review.** This checkpoint independently re-reads the canonical Registry, admin handler and published WU008 evidence after the merge of PR #104.

- Current merged source: `ff3f390511da3ce194815a2c856251ade145b2cc`.
- Previous merged source: `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7`.
- Authentic WU008 test Head: `f1503c2e060e349708bb645bf12d2f2e4b4b6212`.
- Evidence commit (source-bound screenshots/report): `9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2`.
- [Existing successful WU008 run](https://github.com/rezahh107/PersianGravity/actions/runs/41234928792).
- [Original 8-case and interleaving report](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/report.json).
- [Real WordPress screenshot index](https://github.com/rezahh107/PersianGravity/blob/9e25f60bf1da07f7b8494d8d7cb5dcb861f908a2/scanner-incarnation-evidence/README.md).
- [Owner decision already on main](https://github.com/rezahh107/PersianGravity/blob/ff3f390511da3ce194815a2c856251ade145b2cc/docs/SCANNER_PROFILE_INCARNATION_ATOMICITY_DECISION.md).

## Negative and positive controls observed in previous exact-Head WU008

| Control | Previously observed result |
| --- | --- |
| Unchanged A, fresh confirmation | Deleted (intended) |
| A already deleted | Old confirmation rejected |
| Different-label B with reused ID | B survived |
| Same-label B with changed parser/output | B survived |
| In-place enabled-state edit | Modified A survived |
| In-place output-label edit | Modified A survived |
| Fresh B confirmation after stale A rejected | B deleted (intended) |
| **Identical-content B after delete/recreate** | **B deleted by stale A: unsafe** |
| **Changed B interleaved at Registry delete read** | **B deleted by stale A: unsafe** |

The interleaving's injected Registry mutation took **1.404 ms**. This is *not* an HTTP latency or two-worker measurement. The source-bound evidence tree contains **24 JPEG screenshots** for eight regular cases and the interleaving case.

## Current boundary

The exact current main has changed only qualification source/tests/docs compared with the prior reviewed main; production Registry/admin behavior is unchanged. The code still has content-only `SCHEMA_VERSION = 1`, an ID-only deletion operation, and an unconditional whole-option write without a compare-and-swap guard.

WordPress' `update_option()` is not a conditional expected-old-value write, and `get_option()` may read caches. Even adding a random ID would fail if verified only in the admin handler before an unguarded ID-only delete.

**Still NOT PROVEN:** genuine independent concurrent requests, persistent cache coherence, external option writers, failed-store recovery, migration/rollback safety, screen-reader behavior and any new production fix. Previous responsive/locale/keyboard/no-JS WU008 evidence is historical, not a fresh verification.

**Minimum Owner approval:** a schema-versioned canonical incarnation/revision identity with explicit legacy migration and rollback policy, plus a host-qualified conflict-detecting atomic Registry-write protocol. The approved red Confirm, neutral Cancel, wording, keyboard focus, capability/nonce and mapping behavior must remain unchanged.

See [machine-readable source-bound report](report.json). This evidence branch is **not** a production PR and should never be merged.
