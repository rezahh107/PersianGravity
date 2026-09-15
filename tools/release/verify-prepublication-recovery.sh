#!/usr/bin/env bash
set -Eeuo pipefail

SOURCE_SHA="${1:-}"
WORKFLOW_SHA="${2:-}"
ROOT="${PGR_RECOVERY_REPOSITORY_ROOT:-$(pwd)}"
OUTPUT_FILE="${PGR_RECOVERY_OUTPUT_FILE:-${GITHUB_OUTPUT:-}}"

fail() {
  echo "prepublication-recovery: $*" >&2
  exit 1
}

command -v git >/dev/null 2>&1 || fail 'git is unavailable'
[[ "$SOURCE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'source SHA must be a 40-character lowercase Git object id'
[[ "$WORKFLOW_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'workflow SHA must be a 40-character lowercase Git object id'

cd "$ROOT"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "repository root is not a Git work tree: $ROOT"
git cat-file -e "${SOURCE_SHA}^{commit}" 2>/dev/null || fail "source commit is unavailable: $SOURCE_SHA"
git cat-file -e "${WORKFLOW_SHA}^{commit}" 2>/dev/null || fail "workflow commit is unavailable: $WORKFLOW_SHA"

emit_output() {
  local key="$1" value="$2"
  if [[ -n "$OUTPUT_FILE" ]]; then
    printf '%s=%s\n' "$key" "$value" >> "$OUTPUT_FILE"
  fi
}

if [[ "$SOURCE_SHA" == "$WORKFLOW_SHA" ]]; then
  emit_output recovery_mode DIRECT
  printf 'PREPUBLICATION_RECOVERY=PASS mode=DIRECT source=%s workflow=%s\n' "$SOURCE_SHA" "$WORKFLOW_SHA"
  exit 0
fi

git merge-base --is-ancestor "$SOURCE_SHA" "$WORKFLOW_SHA" || {
  fail "workflow commit is not a descendant of the reviewed integrated release source: source=${SOURCE_SHA} workflow=${WORKFLOW_SHA}"
}

allowed_path() {
  case "$1" in
    .github/workflows/publish-release.yml|.github/workflows/artifact-install-smoke.yml|docs/RELEASE.md|tests/release/recovery-contracts.sh|tools/release/resolve-qualified-artifact.sh|tools/release/verify-prepublication-recovery.sh)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

mapfile -d '' changed_paths < <(git diff --name-only -z "$SOURCE_SHA" "$WORKFLOW_SHA")
[[ "${#changed_paths[@]}" -gt 0 ]] || fail 'workflow/source commits differ but no changed paths were resolved'

for path in "${changed_paths[@]}"; do
  allowed_path "$path" || fail "unsafe post-candidate drift outside bounded Release-System recovery scope: ${path}"
done

# Keep this explicit even though the path allowlist is stricter: these are the
# exact source inputs copied by tools/build-production-package.sh.
git diff --quiet "$SOURCE_SHA" "$WORKFLOW_SHA" -- \
  persian-gravityforms.php uninstall.php readme.txt admin assets includes languages || {
  fail 'production package inputs changed after the reviewed Release Candidate'
}

emit_output recovery_mode RELEASE_SYSTEM_ONLY
printf 'PREPUBLICATION_RECOVERY=PASS mode=RELEASE_SYSTEM_ONLY source=%s workflow=%s changed_paths=%d\n' \
  "$SOURCE_SHA" "$WORKFLOW_SHA" "${#changed_paths[@]}"
