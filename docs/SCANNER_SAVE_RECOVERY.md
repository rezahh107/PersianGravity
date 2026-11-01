# Scanner Profiles failed-save recovery (bounded admin UX)

This page describes the Scanner Create/Edit request path and the dedicated
WU008 qualification on the Draft PR. It does not constitute product acceptance,
a published release, or proof on a production WordPress site.

## Root cause and ownership

The active Scanner Profiles admin screen belongs to
`PGR_Product_Admin::add_menu()`, which delegates rendering to
`PGR_Admin::render_profiles_page()`. The pre-repair HTML form posted to
`admin-post.php`; the server validated the profile and then redirected to the
list on both successful and rejected saves. The rejected POST values were not
presented in an editable form after that redirect. This was **not evidence of
a committed Registry profile being deleted**.

The repaired form POSTs to the same native
`admin.php?page=persian-gravityforms-scanner-profiles` screen. Its
`load-{page_hook}` callback is registered by the active product-admin menu
owner and forwards to `PGR_Admin::handle_profile_save_on_page()` *before
WordPress emits the admin header*. Capability, nonce, and Registry validation
remain enforced. Successful requests use the existing redirect. Rejected
requests are re-rendered only in memory on the current authenticated request:
submitted fields are escaped for output and no new transient, URL payload,
database copy, or client-side storage is introduced.

Failed edits retain the exact original `existing_id`, `revision`, and
`incarnation_id`. Retry **cannot automatically rebase a stale edit**: the
Registry v2 compare-and-swap gate must reject edits that no longer match
the committed incarnation/revision. The user must compare against the latest
stored version and manually reapply changes.

The historical `admin_post_pgr_scanner_profile_save` endpoint is retained
for compatibility, but new rendered forms use the native page POST route.
No Registry schema/migration or GPP/Gravity Flow policy is changed.

## Runtime evidence contract

`tests/real-integration/scanner-save-recovery-browser.mjs` uses the existing
licensed WU008 WordPress 6.8.3 / Gravity Forms 3.1.1.1 environment.
The baseline mode reproduces the prior rejected Create redirect with no
recovered editable form. The repaired mode checks:

- Valid corrected Create after an invalid Create, preserving input on failure.
- Invalid Edit with multiple changed fields, corrected Edit retry.
- Stale revision refusal and preservation of the newer committed Registry value.
- Read-back Registry and GFAPI mapping hashes around rejected writes.
- `fa_IR` RTL and `en_US` LTR at 1280, 390, and 320 CSS px on error screens.

Only synthetic WU008 fixture data is used. The workflow restores the original
disposable Registry after the interactive test before downstream browser/CAS
qualifications. When the exact-Head licensed WU008 run succeeds, the
publication gate verifies bilingual reports, state invariants, and original
PNG files and places them on a run-attempt-specific evidence branch. Evidence
from an earlier Head is not valid as acceptance for a later change.

A controlled Registry write failure, live production installation, independent
screen-reader behavior, and full UX-G01/G02/G03/G04 acceptance remain separate
claims requiring their own evidence. No release, deployment, merge, or live
Registry migration is authorized by this Draft PR.
