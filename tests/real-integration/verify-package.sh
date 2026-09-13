#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 9 ]]; then
  echo "usage: $0 <product> <file-id> <zip-path> <expected-filename> <expected-size> <expected-sha256> <main-entry> <expected-version> <evidence-jsonl>" >&2
  exit 64
fi

product="$1"
file_id="$2"
zip_path="$3"
expected_filename="$4"
expected_size="$5"
expected_sha="$6"
main_entry="$7"
expected_version="$8"
evidence_jsonl="$9"

fail() {
  local reason="$1"
  printf '%s verification FAIL: %s\n' "$product" "$reason" >&2
  exit 1
}

[[ -f "$zip_path" ]] || fail "downloaded file is missing"
[[ "$(basename "$zip_path")" == "$expected_filename" ]] || fail "filename mismatch"

actual_size="$(stat -c '%s' "$zip_path")"
actual_sha="$(sha256sum "$zip_path" | awk '{print $1}')"
mime_type="$(file -b --mime-type "$zip_path")"
file_description="$(file -b "$zip_path")"
signature="$(dd if="$zip_path" bs=1 count=4 status=none | od -An -tx1 | tr -d ' \n')"

case "$signature" in
  504b0304|504b0506|504b0708) ;;
  *) fail "ZIP signature check failed (signature=$signature, mime=$mime_type)" ;;
esac

case "$mime_type" in
  application/zip|application/x-zip|application/x-zip-compressed) ;;
  *) fail "file type is not ZIP (mime=$mime_type; description=$file_description)" ;;
esac

unzip -tqq "$zip_path" >/dev/null || fail "archive integrity test failed"
[[ "$actual_size" == "$expected_size" ]] || fail "byte size mismatch (expected=$expected_size actual=$actual_size)"
[[ "$actual_sha" == "$expected_sha" ]] || fail "SHA-256 mismatch (expected=$expected_sha actual=$actual_sha)"
unzip -Z1 "$zip_path" | grep -Fx "$main_entry" >/dev/null || fail "expected plugin main file is absent: $main_entry"

actual_version="$({ unzip -p "$zip_path" "$main_entry" || true; } | sed -nE 's/^[[:space:]]*[*#;\/]*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*$/\1/p' | head -n1 | tr -d '\r')"
[[ -n "$actual_version" ]] || fail "plugin version header could not be read"
[[ "$actual_version" == "$expected_version" ]] || fail "plugin version mismatch (expected=$expected_version actual=$actual_version)"

mkdir -p "$(dirname "$evidence_jsonl")"
printf '{"product":"%s","file_id":"%s","filename":"%s","expected_size":%s,"actual_size":%s,"expected_sha256":"%s","actual_sha256":"%s","expected_version":"%s","actual_version":"%s","mime_type":"%s","zip_signature":"%s","archive_integrity":"PASS","verification":"PASS"}\n' \
  "$product" "$file_id" "$expected_filename" "$expected_size" "$actual_size" "$expected_sha" "$actual_sha" "$expected_version" "$actual_version" "$mime_type" "$signature" \
  >> "$evidence_jsonl"

printf '%s verification PASS: size=%s sha256=%s version=%s\n' "$product" "$actual_size" "$actual_sha" "$actual_version"
