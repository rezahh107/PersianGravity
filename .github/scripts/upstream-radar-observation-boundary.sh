#!/usr/bin/env bash
set -euo pipefail

readonly OBSERVATION_PATH_PATTERN='^tools/compatibility/upstream-observations/(gravityforms|gravityflow|gravityview|gravityperks)/[0-9]+(\.[0-9]+){2,3}\.json$'

fail() {
  printf 'Upstream radar observation boundary rejected state: %s\n' "$1" >&2
  exit 1
}

is_allowed_observation_path() {
  [[ "$1" =~ $OBSERVATION_PATH_PATTERN ]]
}

validate_committed_tree() {
  local base_ref="$1"
  local head_ref="$2"
  local metadata path old_mode new_mode old_oid new_oid status

  command -v jq >/dev/null 2>&1 || fail 'jq is required for committed observation validation'
  git rev-parse --verify "${base_ref}^{commit}" >/dev/null 2>&1 || fail "missing canonical base ref: ${base_ref}"
  git rev-parse --verify "${head_ref}^{commit}" >/dev/null 2>&1 || fail "missing candidate head ref: ${head_ref}"

  while IFS= read -r -d '' metadata; do
    IFS= read -r -d '' path || fail 'malformed git diff record'
    metadata="${metadata#:}"
    read -r old_mode new_mode old_oid new_oid status <<< "$metadata"

    is_allowed_observation_path "$path" || fail "committed non-observation delta: ${path}"
    [[ "$status" == 'A' || "$status" == 'M' ]] || fail "unsupported committed observation status ${status}: ${path}"
    [[ "$new_mode" == '100644' ]] || fail "unsupported committed observation Git mode ${new_mode}: ${path}"
    git cat-file -e "${new_oid}^{blob}" >/dev/null 2>&1 || fail "committed observation is not a normal blob: ${path}"
    git cat-file blob "$new_oid" | jq -e . >/dev/null 2>&1 || fail "malformed committed observation JSON: ${path}"
  done < <(git diff --raw -z --no-renames "$base_ref" "$head_ref")
}

validate_working_tree() {
  local entry status path
  local -a changed=()

  while IFS= read -r -d '' entry; do
    status="${entry:0:2}"
    path="${entry:3}"

    [[ "$status" != *R* && "$status" != *C* ]] || fail "unsupported working-tree rename/copy: ${path}"
    is_allowed_observation_path "$path" || fail "non-observation automated mutation: ${path}"
    changed+=( "$path" )
  done < <(git status --porcelain=v1 -z --untracked-files=all)

  if (( ${#changed[@]} == 0 )); then
    printf 'false\n'
    return 0
  fi

  printf 'true\n'
}

case "${1:-}" in
  committed)
    (( $# == 3 )) || fail 'usage: committed <canonical-base-ref> <candidate-head-ref>'
    validate_committed_tree "$2" "$3"
    ;;
  working-tree)
    (( $# == 1 )) || fail 'usage: working-tree'
    validate_working_tree
    ;;
  *)
    fail 'usage: <committed|working-tree> [refs]'
    ;;
esac
