#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

PASS_COUNT=0
TMP_ROOT="$(mktemp -d)"
cleanup() {
  rm -rf "$TMP_ROOT"
}
trap cleanup EXIT

pass() {
  PASS_COUNT=$((PASS_COUNT + 1))
  printf 'PASS %02d - %s\n' "$PASS_COUNT" "$1"
}

expect_pass() {
  local name="$1"
  shift
  if "$@" >/tmp/pgr-release-recovery.out 2>/tmp/pgr-release-recovery.err; then
    pass "$name"
    return
  fi
  echo "Expected PASS: $name" >&2
  cat /tmp/pgr-release-recovery.out >&2 || true
  cat /tmp/pgr-release-recovery.err >&2 || true
  exit 1
}

expect_fail_contains() {
  local name="$1" expected="$2"
  shift 2
  if "$@" >/tmp/pgr-release-recovery.out 2>/tmp/pgr-release-recovery.err; then
    echo "Expected failure but command passed: $name" >&2
    cat /tmp/pgr-release-recovery.out >&2 || true
    exit 1
  fi
  grep -Fq "$expected" /tmp/pgr-release-recovery.err || {
    echo "Failure did not contain expected diagnostic for: $name" >&2
    printf 'Expected substring: %s\n' "$expected" >&2
    cat /tmp/pgr-release-recovery.err >&2 || true
    exit 1
  }
  pass "$name"
}

RESOLVER='tools/release/resolve-qualified-artifact.sh'
RECOVERY='tools/release/verify-prepublication-recovery.sh'
PUBLISH_WORKFLOW='.github/workflows/publish-release.yml'
TOOL=(php tools/release/release-tool.php)

bash -n "$RESOLVER" "$RECOVERY"
pass 'release recovery helper syntax'

CURRENT="$(${TOOL[@]} current)"
ZIP="dist/persian-gravityforms-${CURRENT}.zip"
CHECKSUM="${ZIP}.sha256"
[[ -s "$ZIP" && -s "$CHECKSUM" ]] || {
  echo "Recovery contracts require the production builder output before execution: $ZIP" >&2
  exit 1
}
ZIP_NAME="$(basename "$ZIP")"
CHECKSUM_NAME="$(basename "$CHECKSUM")"
ZIP_SHA="$(sha256sum "$ZIP" | awk '{print $1}')"

nested="$TMP_ROOT/nested-artifact"
mkdir -p "$nested/dist" "$nested/release-evidence"
cp "$ZIP" "$nested/dist/$ZIP_NAME"
cp "$CHECKSUM" "$nested/dist/$CHECKSUM_NAME"
printf 'evidence\n' > "$nested/release-evidence/qualification.txt"
resolver_out="$TMP_ROOT/resolver.out"
PGR_ARTIFACT_RESOLVER_OUTPUT_FILE="$resolver_out" \
  expect_pass 'nested qualification artifact layout is accepted' \
  bash "$RESOLVER" "$nested" "$ZIP_NAME" "$CHECKSUM_NAME" "$ZIP_SHA"
resolved_zip="$(awk -F= '$1 == "zip_path" { print $2 }' "$resolver_out")"
resolved_checksum="$(awk -F= '$1 == "checksum_path" { print $2 }' "$resolver_out")"
[[ "$resolved_zip" == "$nested/dist/$ZIP_NAME" ]] || {
  echo "Resolver returned wrong ZIP path: $resolved_zip" >&2
  exit 1
}
pass 'exact ZIP basename resolves to nested dist path'
[[ "$resolved_checksum" == "$nested/dist/$CHECKSUM_NAME" ]] || {
  echo "Resolver returned wrong checksum path: $resolved_checksum" >&2
  exit 1
}
pass 'exact checksum basename resolves to nested dist path'

missing_zip="$TMP_ROOT/missing-zip"
mkdir -p "$missing_zip/dist"
cp "$CHECKSUM" "$missing_zip/dist/$CHECKSUM_NAME"
expect_fail_contains 'missing ZIP is rejected with diagnostic' 'missing qualified ZIP' \
  bash "$RESOLVER" "$missing_zip" "$ZIP_NAME" "$CHECKSUM_NAME" "$ZIP_SHA"

duplicate_zip="$TMP_ROOT/duplicate-zip"
mkdir -p "$duplicate_zip/dist" "$duplicate_zip/other"
cp "$ZIP" "$duplicate_zip/dist/$ZIP_NAME"
cp "$ZIP" "$duplicate_zip/other/$ZIP_NAME"
cp "$CHECKSUM" "$duplicate_zip/dist/$CHECKSUM_NAME"
expect_fail_contains 'duplicate ZIP is rejected with diagnostic' 'duplicate qualified ZIP matches' \
  bash "$RESOLVER" "$duplicate_zip" "$ZIP_NAME" "$CHECKSUM_NAME" "$ZIP_SHA"

missing_checksum="$TMP_ROOT/missing-checksum"
mkdir -p "$missing_checksum/dist"
cp "$ZIP" "$missing_checksum/dist/$ZIP_NAME"
expect_fail_contains 'missing checksum is rejected with diagnostic' 'missing qualified checksum' \
  bash "$RESOLVER" "$missing_checksum" "$ZIP_NAME" "$CHECKSUM_NAME" "$ZIP_SHA"

BAD_SHA="$(printf '0%.0s' {1..64})"
expect_fail_contains 'qualified artifact digest mismatch is rejected' 'qualified ZIP SHA-256 mismatch' \
  bash "$RESOLVER" "$nested" "$ZIP_NAME" "$CHECKSUM_NAME" "$BAD_SHA"

repo="$TMP_ROOT/recovery-repo"
mkdir -p "$repo"
(
  cd "$repo"
  git init -q
  git config user.email release-contract@example.test
  git config user.name 'Release Contract'
  mkdir -p .github/workflows docs tests/release tools/release includes admin assets languages
  printf 'workflow-v1\n' > .github/workflows/publish-release.yml
  printf 'artifact-smoke-v1\n' > .github/workflows/artifact-install-smoke.yml
  printf 'release-docs\n' > docs/RELEASE.md
  printf 'runtime\n' > includes/runtime.php
  printf 'plugin\n' > persian-gravityforms.php
  printf 'uninstall\n' > uninstall.php
  printf 'readme\n' > readme.txt
  git add .
  git commit -qm source
)
SOURCE_SHA="$(git -C "$repo" rev-parse HEAD)"

expect_pass 'direct pre-publication source remains accepted' \
  env PGR_RECOVERY_REPOSITORY_ROOT="$repo" bash "$RECOVERY" "$SOURCE_SHA" "$SOURCE_SHA"

(
  cd "$repo"
  printf 'workflow-v2\n' >> .github/workflows/publish-release.yml
  printf 'artifact-smoke-v2\n' >> .github/workflows/artifact-install-smoke.yml
  cp "$ROOT/$RESOLVER" tools/release/resolve-qualified-artifact.sh
  cp "$ROOT/$RECOVERY" tools/release/verify-prepublication-recovery.sh
  cp "$ROOT/tests/release/recovery-contracts.sh" tests/release/recovery-contracts.sh
  printf 'release-docs-v2\n' >> docs/RELEASE.md
  git add .
  git commit -qm release-system-repair
)
SAFE_HEAD="$(git -C "$repo" rev-parse HEAD)"
recovery_out="$TMP_ROOT/recovery.out"
PGR_RECOVERY_REPOSITORY_ROOT="$repo" PGR_RECOVERY_OUTPUT_FILE="$recovery_out" \
  expect_pass 'legitimate Release-System-only pre-publication recovery is accepted' \
  bash "$RECOVERY" "$SOURCE_SHA" "$SAFE_HEAD"
[[ "$(awk -F= '$1 == "recovery_mode" { print $2 }' "$recovery_out")" == 'RELEASE_SYSTEM_ONLY' ]] || {
  echo 'Expected RELEASE_SYSTEM_ONLY recovery mode.' >&2
  exit 1
}
pass 'recovery mode is explicitly RELEASE_SYSTEM_ONLY'

(
  cd "$repo"
  printf 'unsafe-runtime-drift\n' >> includes/runtime.php
  git add includes/runtime.php
  git commit -qm unsafe-runtime-drift
)
UNSAFE_HEAD="$(git -C "$repo" rev-parse HEAD)"
expect_fail_contains 'unsafe post-candidate product/runtime drift is rejected' 'unsafe post-candidate drift outside bounded Release-System recovery scope' \
  env PGR_RECOVERY_REPOSITORY_ROOT="$repo" bash "$RECOVERY" "$SOURCE_SHA" "$UNSAFE_HEAD"

SHA_A='1111111111111111111111111111111111111111'
SHA_B='2222222222222222222222222222222222222222'
TREE='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
expect_fail_contains 'existing tag conflict remains rejected' 'tag v' \
  "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-state=CONFLICT --release-exists=0
expect_fail_contains 'existing GitHub Release remains rejected' 'already exists' \
  "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-state=ABSENT --release-exists=1

grep -Fq 'source_sha="$integrated_sha"' "$PUBLISH_WORKFLOW" || {
  echo 'Publish workflow no longer pins publication source to integrated Release PR.' >&2
  exit 1
}
grep -Fq 'verify-prepublication-recovery.sh "$source_sha" "$workflow_sha"' "$PUBLISH_WORKFLOW" || {
  echo 'Publish workflow no longer validates the bounded recovery window.' >&2
  exit 1
}
grep -Fq 'candidate_sha: ${{ needs.resolve.outputs.source_sha }}' "$PUBLISH_WORKFLOW" || {
  echo 'Qualification no longer targets the exact integrated source.' >&2
  exit 1
}
grep -Fq 'resolve-qualified-artifact.sh' "$PUBLISH_WORKFLOW" || {
  echo 'Publish workflow no longer uses the fail-closed artifact resolver.' >&2
  exit 1
}
if grep -Fq 'test -s "qualified-assets/$ZIP_NAME"' "$PUBLISH_WORKFLOW"; then
  echo 'Publish workflow regressed to a flattened qualification artifact assumption.' >&2
  exit 1
fi
grep -Fq '[[ "$remote_main" == "$WORKFLOW_SHA" ]]' "$PUBLISH_WORKFLOW" || {
  echo 'Publish workflow no longer freezes main at the validated recovery head.' >&2
  exit 1
}
pass 'Publish workflow preserves exact-source recovery and layout-independent artifact resolution'

for file in "$RESOLVER" "$RECOVERY"; do
  if grep -Eq 'gh[[:space:]]+release[[:space:]]+create|git[[:space:]]+tag|git[[:space:]]+update-ref|git[[:space:]]+push.*--force|repos/.*/git/refs' "$file"; then
    echo "Recovery helper contains a publication mutation primitive: $file" >&2
    exit 1
  fi
done
if grep -Eq '^[[:space:]]*(gh[[:space:]]+release[[:space:]]+create|gh[[:space:]]+api.*git/refs|git[[:space:]]+tag([[:space:]]|$)|git[[:space:]]+update-ref|git[[:space:]]+push.*--force)' tests/release/recovery-contracts.sh; then
  echo 'Recovery contract suite contains an executable publication mutation command.' >&2
  exit 1
fi
pass 'recovery tests and helpers cannot create tags or GitHub Releases'

printf 'RELEASE_RECOVERY_CONTRACTS=PASS tests=%d version=%s\n' "$PASS_COUNT" "$CURRENT"
