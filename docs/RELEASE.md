# PersianGravity Release Guide

This is the normal release process for the Owner. You do not need to calculate a version, create a Git tag, build a ZIP, calculate a checksum, or upload GitHub Release files manually.

## One-time repository setup

GitHub must allow repository workflows to create pull requests with the built-in `GITHUB_TOKEN`: **Settings → Actions → General → Workflow permissions → Allow GitHub Actions to create and approve pull requests**.

This is a one-time repository setting, not a per-release step. PersianGravity does not require a PAT or any new long-lived release secret.

The generated Release Candidate is qualified inside the Prepare Release run itself. It does not depend on a new `push` workflow run, and it does not assume that ordinary PR workflows created by `GITHUB_TOKEN` will run without approval.

## 1. Prepare Release

1. Open **GitHub → Actions → Prepare Release**.
2. Click **Run workflow** and keep the branch selector on **main**.
3. Choose one version change:
   - **patch** — compatible bug fix or small correction.
   - **minor** — new backward-compatible capability.
   - **major** — intentional incompatible/breaking product change.
4. Run the workflow.
5. Wait for it to create a Release PR.
6. Review that PR and its checks. Merge it only when you are satisfied and it is green.

Prepare Release automatically:

- reads the current version from PersianGravity source;
- refuses inconsistent or non-stable version metadata;
- computes the next SemVer version;
- moves the real `Unreleased` changelog notes into the new version section and opens a fresh empty `Unreleased` section;
- updates only the active release-version declarations and the own-plugin gettext project-version headers;
- recompiles the own-plugin Persian MO from the unchanged translated PO content;
- creates `release/vX.Y.Z` without overwriting an existing release branch;
- qualifies the exact generated candidate commit with the release-relevant repository checks, PHP matrix, deterministic i18n checks, production package builder, checksum verification, and WordPress install/activation smoke;
- creates a reviewable Release PR only after that exact candidate qualifies.

**Merging the Release PR does not publish anything.** No ordinary feature PR merge publishes a release either.

## 2. Publish Release

After the generated Release PR has been reviewed and merged:

1. Open **GitHub → Actions → Publish Release**.
2. Click **Run workflow** and keep the branch selector on **main**.
3. Run the workflow. There is no version field to type.
4. Wait for PASS.
5. Open **GitHub → Releases** to see the published release and installable ZIP.

Normally, Publish Release requires `main` to still be the exact integrated result of the reviewed Release PR and to have the exact reviewed candidate tree.

There is one bounded pre-publication recovery exception: if a defect in the Release System itself is discovered **after** the Release PR was merged but **before** any production tag or GitHub Release exists, a repaired Publish workflow may continue to publish the original reviewed integrated source only when all post-candidate changes are deterministically proven to be confined to the small Release-System recovery allowlist. The original integrated Release PR commit remains the source for qualification, package identity, tag creation, and publication; the newer `main` commit supplies only the repaired automation. Any product/runtime/package-input drift, version drift, unrelated documentation/code drift, divergent history, conflicting tag, or existing GitHub Release fails closed and requires a fresh candidate or Owner intervention.

For qualification artifacts, Publish Release does not assume GitHub Actions flattens uploaded paths. It searches recursively for the exact expected ZIP basename and checksum basename, requires exactly one match for each, rejects missing or duplicate matches with explicit diagnostics, then verifies the exact ZIP SHA-256 and the existing production-package identity contract.

Before creating any public tag or Release, Publish Release re-runs the exact-source qualification, rebuilds and validates the production ZIP, verifies SHA-256, and installs/activates that generated ZIP in disposable WordPress at the supported minimum boundary.

Only after those pre-publication gates pass does it:

- create `vX.Y.Z` on the exact qualified source, or reuse that same lightweight tag without mutation when an earlier failed attempt already created it on the exact qualified source;
- create the GitHub Release with generated release notes;
- attach `persian-gravityforms-X.Y.Z.zip` and `persian-gravityforms-X.Y.Z.zip.sha256`;
- download those actual published assets again;
- verify the downloaded checksum and package/source identity;
- install and activate the exact downloaded Release ZIP in disposable WordPress.

If a Publish Release attempt fails after creating the expected version tag but before the GitHub Release exists, rerunning the workflow can resume automatically **only** when that lightweight tag still points directly at the same qualified source commit. The workflow verifies and reuses that exact tag without deleting, moving, overwriting, or force-updating it. A wrong-target/annotated conflicting tag, or any already-existing GitHub Release for that version, remains fail-closed and requires Owner intervention.

If verification fails after a GitHub Release already exists, the workflow fails visibly and preserves evidence. It does **not** delete, rewrite, move, replace, or silently mutate the published tag or Release; Owner intervention is required.

## What the automation protects against

The release system fails closed when it detects conditions such as:

- version declarations disagree;
- the current version is not stable `X.Y.Z` SemVer;
- `Unreleased` has no meaningful release notes;
- the release branch already exists during preparation;
- the expected version tag exists but does not identify the exact qualified source;
- a GitHub Release for the expected version already exists;
- `main` no longer identifies the exact reviewed Release Candidate and the difference is not a bounded Release-System-only recovery;
- any product/runtime/package input changed after the reviewed Release Candidate;
- the downloaded qualification artifact is missing the exact expected ZIP/checksum, contains duplicate basename matches, or has the wrong ZIP digest;
- provider localization generated artifacts drift from their admitted source/authority;
- required production files are missing;
- repository/development/private provider-source material leaks into the ZIP;
- the ZIP has the wrong top-level plugin directory;
- the package checksum or embedded source/version identity is wrong;
- the generated or published ZIP cannot install and activate cleanly without Gravity Forms present.

## Scope of the package smoke

The artifact smoke proves production-package integrity, WordPress installation, activation, the packaged PersianGravity version/source identity, bootstrap without a fatal error, and the bounded behavior when Gravity Forms is unavailable.

It does **not** replace the historical licensed Gravity Forms / Gravity Flow / GravityView 19-surface evidence and does not claim full host-product behavior from this packaging smoke.

## Routine Owner rule

For a normal release, the only routine choices are:

**choose patch/minor/major → review/merge the generated Release PR → run Publish Release**.

For a pre-publication Release-System repair, merge only the focused repair PR after it is green, then rerun **Publish Release** from `main`. Do not create the version tag or GitHub Release manually.
