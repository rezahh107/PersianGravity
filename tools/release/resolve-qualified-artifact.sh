#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-}"
ZIP_NAME="${2:-}"
CHECKSUM_NAME="${3:-}"
EXPECTED_ZIP_SHA="${4:-}"
OUTPUT_FILE="${PGR_ARTIFACT_RESOLVER_OUTPUT_FILE:-${GITHUB_OUTPUT:-}}"

fail() {
  echo "qualified-artifact-resolver: $*" >&2
  exit 1
}

for command in find sha256sum; do
  command -v "$command" >/dev/null 2>&1 || fail "required command is unavailable: $command"
done

[[ -n "$ROOT" && -d "$ROOT" ]] || fail 'artifact root directory is missing'
[[ -n "$ZIP_NAME" && "$ZIP_NAME" != */* ]] || fail 'expected ZIP name must be a basename'
[[ -n "$CHECKSUM_NAME" && "$CHECKSUM_NAME" != */* ]] || fail 'expected checksum name must be a basename'
[[ "$EXPECTED_ZIP_SHA" =~ ^[0-9a-f]{64}$ ]] || fail 'expected ZIP SHA-256 must be 64 lowercase hex characters'

ROOT="$(cd "$ROOT" && pwd)"

resolve_one() {
  local basename="$1" label="$2"
  local -a matches=()
  mapfile -d '' matches < <(find "$ROOT" -type f -name "$basename" -print0)

  case "${#matches[@]}" in
    1)
      printf '%s\n' "${matches[0]}"
      ;;
    0)
      fail "missing ${label}: expected exactly one file named ${basename} under ${ROOT}"
      ;;
    *)
      printf 'qualified-artifact-resolver: duplicate %s matches for %s under %s:\n' "$label" "$basename" "$ROOT" >&2
      printf '  %s\n' "${matches[@]}" >&2
      exit 1
      ;;
  esac
}

ZIP_PATH="$(resolve_one "$ZIP_NAME" 'qualified ZIP')"
CHECKSUM_PATH="$(resolve_one "$CHECKSUM_NAME" 'qualified checksum')"
ACTUAL_ZIP_SHA="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
[[ "$ACTUAL_ZIP_SHA" == "$EXPECTED_ZIP_SHA" ]] || {
  fail "qualified ZIP SHA-256 mismatch: expected=${EXPECTED_ZIP_SHA} actual=${ACTUAL_ZIP_SHA} path=${ZIP_PATH}"
}

emit_output() {
  local key="$1" value="$2"
  if [[ -n "$OUTPUT_FILE" ]]; then
    printf '%s=%s\n' "$key" "$value" >> "$OUTPUT_FILE"
  fi
}

emit_output zip_path "$ZIP_PATH"
emit_output checksum_path "$CHECKSUM_PATH"
emit_output zip_sha "$ACTUAL_ZIP_SHA"

printf 'QUALIFIED_ARTIFACT_RESOLUTION=PASS\n'
printf 'ZIP_PATH=%s\n' "$ZIP_PATH"
printf 'CHECKSUM_PATH=%s\n' "$CHECKSUM_PATH"
printf 'ZIP_SHA256=%s\n' "$ACTUAL_ZIP_SHA"
