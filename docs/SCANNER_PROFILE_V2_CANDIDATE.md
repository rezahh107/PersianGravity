# Scanner Profile Registry v2 — candidate safety and migration procedure

**Status:** Source merged to main via PR #106 (`810ed1eabf956f7073ea7f568fc7d1c24a69b661`, 2026-11-01). No verified production migration, public release, deployment or real-site acceptance. Owner authorization is required separately for rollout. Scope: Scanner Profiles only; no changes to G-013, G-014, GPP or Vazir.

## Defect and correction

v1's content-only fingerprint identified neither a unique creation of a custom profile nor a single atomic deletion: a stale confirmation could delete a new profile with the same public ID and identical content, or a different replacement written between the handler's validation and the Registry's later ID-only deletion.

v2 retains each public profile ID and the canonical `pgr_scanner_profiles` non-autoloaded WordPress option. It adds a fresh random immutable `incarnation_id` on each create and an edit `revision`. A confirmation fingerprint covers the entire normalized definition plus those two attributes. The nonce, visible label and existing confirmation page remain unchanged. The Registry rechecks the presented snapshot **inside the Registry** and executes a conditional database write against the exact prior serialized option. Each create, edit, delete and migration uses the same conditional write protocol; conflict or write failure returns failure. Built-ins are unchanged. Gravity Forms mappings continue to refer only to the stable public profile IDs.

`update_option()` is unsuitable as a CAS operation because its database update is by `option_name` without the old serialized value precondition. `add_option()` in WordPress 6.8.3 uses `ON DUPLICATE KEY UPDATE`, so the candidate's absent-option create instead uses `$wpdb->insert()` (unique name) and conditional updates use `$wpdb->prepare()`. The prepared SQL compares exact stored bytes with `BINARY`. The registered WordPress option value is serialized via `maybe_serialize()`. The Registry uses fresh DB snapshots rather than cached `get_option()` for safety decisions. Normal WordPress option caches (`options`, `notoptions`, `alloptions`) are invalidated after successful writes; registered write filters must not transform canonical data. Standard option write actions are emitted, but this is not a general replacement for the native Options API.

**Host qualification is mandatory:** database affected-row semantics, table uniqueness, serialization, write hooks, cache invalidation, concurrent requests and persistent object cache must be tested against the **exact tested WordPress 6.8.3 installation** and an admitted cache backend. Independent processes and real cache cannot be inferred from a single-request hook or fake SQL unit tests. Outside Registry APIs, direct third-party mutation of `pgr_scanner_profiles` is unsupported. If an external writer or filter modifies it, compatibility must be separately assessed; do not bypass safety to allow it.

## Migration — never automatic

The plugin never migrates on activation or on read. A valid legacy v1 option remains readable for runtime compatibility, but mutation/deletion is unavailable until an authorized explicit migration. Old v1 deletion confirmations cannot pass the v2 fingerprint check.

For a disposable/staging installation only:

1. Record the exact deployed plugin version and capture a **verified, durable copy** of the pre-migration `pgr_scanner_profiles` option's serialized bytes *outside webroot* (along with a broader site/database backup and GFAPI mappings). `PGR_Scanner_Profile_Registry::pre_migration_backup()` returns `raw` and its `sha256` only for strictly valid, losslessly representable v1 data. Store both securely, verify the SHA-256 of stored bytes, and verify backup restore feasibility on a disposable environment.
2. With an authorized operator session (for example, a separately controlled WP-CLI eval context), invoke `PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $verified_sha256 )`. This must only be invoked **after** verifying that the stored backup is the exact raw pre-migration option. If the live raw bytes changed, the method fails closed; it does not discard unrecognized or malformed legacy records.
3. Read back `schema_version = 2`, every original public ID, display name, parser, ordered output, enabled state and GFAPI field mappings. Test fresh confirmation, stale confirmation, failure recovery and supported front-end scanner output.
4. Calling the method again on already-valid v2 returns `already_v2` without changing any incarnation. Concurrent migration detects conflicting writes. A failed migration must leave recoverable v1, not a partial v2.

### Rollback and recovery

There is **no automatic or assumed lossless v2→v1 downgrade**. Prefer fixing forward on v2. An older v1 plugin is **not safe to run against a v2 Registry** because its normalizer may drop v2 metadata. To restore an earlier plugin version, first stop all Scanner writers, retain/export the current v2 for reconciliation, restore the **matching pre-migration plugin version and exact Registry backup**, and verify stored data/mappings before reopening writes. This necessarily restores *earlier* data; profile changes made since backup are **not preserved** and must be reviewed/reapplied manually. Do not silently rewrite or discard unknown legacy records or v2 records.

The candidate exposes no destructive downgrade or automatic restore endpoint. Do not run these steps on a real installation without a separate Owner-approved rollout and a tested backup/restore procedure.

## Evidence boundaries

- Automated tests must prove identical-content recreation, stale revision, fresh and unchanged deletion, malformed v1 rejection, failed writes, interleaved replacement and no GFAPI mapping mutation.
- WU008's eight authentic Scanner scenarios plus authenticated deletion pages in fa_IR/en_US at 1280/390/320 (including keyboard and no JavaScript) must run on the **candidate exact Head**.
- A deterministic WordPress hook between the Registry's read and SQL write is **not** a two-worker concurrency proof.
- Real independent PHP workers, write failure on MariaDB, and an actual persistent-cache backend are separate acceptance gates.
- If any required host guarantee is not proven, this PR remains a **draft candidate**, not a safe release or finished product.

## Next bounded UI/UX checkpoint

Only **after** safety qualification, perform a read-only assessment of the Scanner Profiles **list and edit form** in both locales, desktop/mobile, accessible keyboard/no-JS behavior. Produce findings/screenshots without changing their UI here or claiming that those screens or the full Scanner product are complete.
