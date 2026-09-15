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

Publish Release automatically refuses to continue unless `main` is still the exact integrated result of the reviewed Release PR and has the exact reviewed candidate tree. If an unrelated commit landed after the candidate was merged, prepare a fresh release candidate instead.

Before creating any public tag or Release, Publish Release re-runs the exact-source qualification, rebuilds and validates the production ZIP, verifies SHA-256, and installs/activates that generated ZIP in disposable WordPress at the supported minimum boundary.

Only after those pre-publication gates pass does it:

- create `vX.Y.Z` on the exact qualified source;
- create the GitHub Release with generated release notes;
- attach `persian-gravityforms-X.Y.Z.zip` and `persian-gravityforms-X.Y.Z.zip.sha256`;
- download those actual published assets again;
- verify the downloaded checksum and package/source identity;
- install and activate the exact downloaded Release ZIP in disposable WordPress.

If post-publication verification fails, the workflow fails visibly and preserves evidence. It does **not** delete, rewrite, move, or silently replace the published tag or Release. Owner intervention is then required.

## What the automation protects against

The release system fails closed when it detects conditions such as:

- version declarations disagree;
- the current version is not stable `X.Y.Z` SemVer;
- `Unreleased` has no meaningful release notes;
- the release branch, version tag, or GitHub Release already exists;
- `main` no longer identifies the exact reviewed Release Candidate;
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
