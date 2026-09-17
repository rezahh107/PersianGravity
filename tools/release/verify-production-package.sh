#!/usr/bin/env bash
set -Eeuo pipefail

ZIP_PATH="${1:-}"
CHECKSUM_PATH="${2:-}"
EXPECTED_VERSION="${3:-}"
EXPECTED_SOURCE_HEAD="${4:-}"
EXPECTED_SOURCE_TREE="${5:-}"
PLUGIN_ROOT='persian-gravityforms'
PLUGIN_FILE='persian-gravityforms.php'

fail() {
  echo "production-package-verify: $*" >&2
  exit 1
}

for command in unzip sha256sum grep sed awk php find; do
  command -v "$command" >/dev/null 2>&1 || fail "required command is unavailable: $command"
done

[[ -n "$ZIP_PATH" && -f "$ZIP_PATH" ]] || fail 'ZIP path is missing or not a file'
[[ -n "$CHECKSUM_PATH" && -f "$CHECKSUM_PATH" ]] || fail 'checksum path is missing or not a file'
[[ "$EXPECTED_VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] || fail "expected version is not stable SemVer: $EXPECTED_VERSION"
[[ "$EXPECTED_SOURCE_HEAD" =~ ^[0-9a-f]{40}$ ]] || fail 'expected source commit must be a 40-character lowercase Git object id'
[[ "$EXPECTED_SOURCE_TREE" =~ ^[0-9a-f]{40}$ ]] || fail 'expected source tree must be a 40-character lowercase Git object id'

ZIP_DIR="$(cd "$(dirname "$ZIP_PATH")" && pwd)"
ZIP_NAME="$(basename "$ZIP_PATH")"
CHECKSUM_DIR="$(cd "$(dirname "$CHECKSUM_PATH")" && pwd)"
CHECKSUM_NAME="$(basename "$CHECKSUM_PATH")"
EXPECTED_ZIP_NAME="${PLUGIN_ROOT}-${EXPECTED_VERSION}.zip"
[[ "$ZIP_NAME" == "$EXPECTED_ZIP_NAME" ]] || fail "ZIP filename mismatch: expected=$EXPECTED_ZIP_NAME actual=$ZIP_NAME"
[[ "$CHECKSUM_NAME" == "${ZIP_NAME}.sha256" ]] || fail "checksum filename mismatch: expected=${ZIP_NAME}.sha256 actual=$CHECKSUM_NAME"
[[ "$ZIP_DIR" == "$CHECKSUM_DIR" ]] || fail 'ZIP and checksum must be in the same directory'

(
  cd "$ZIP_DIR"
  sha256sum -c "$CHECKSUM_NAME"
) >/dev/null

unzip -tq "$ZIP_PATH" >/dev/null
LISTING="$(mktemp)"
EXTRACTED="$(mktemp -d)"
cleanup() {
  rm -f "$LISTING"
  rm -rf "$EXTRACTED"
}
trap cleanup EXIT

unzip -Z1 "$ZIP_PATH" > "$LISTING"
mapfile -t TOP_LEVELS < <(cut -d/ -f1 "$LISTING" | sed '/^$/d' | LC_ALL=C sort -u)
if (( ${#TOP_LEVELS[@]} != 1 )) || [[ "${TOP_LEVELS[0]}" != "$PLUGIN_ROOT" ]]; then
  fail "ZIP must contain exactly one top-level directory named $PLUGIN_ROOT"
fi

REQUIRED_PATHS=(
  "$PLUGIN_ROOT/$PLUGIN_FILE"
  "$PLUGIN_ROOT/uninstall.php"
  "$PLUGIN_ROOT/readme.txt"
  "$PLUGIN_ROOT/release-manifest.json"
  "$PLUGIN_ROOT/admin/class-pgr-product-admin.php"
  "$PLUGIN_ROOT/assets/js/pgr-structured-scanner-core.js"
  "$PLUGIN_ROOT/includes/class-pgr-localization.php"
  "$PLUGIN_ROOT/includes/class-pgr-gravity-perks-rtl.php"
  "$PLUGIN_ROOT/includes/localization/products.php"
  "$PLUGIN_ROOT/includes/localization/g007-products.php"
  "$PLUGIN_ROOT/includes/localization/registry.php"
  "$PLUGIN_ROOT/languages/persian-gravityforms-fa_IR.po"
  "$PLUGIN_ROOT/languages/persian-gravityforms-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gravityforms/gravityforms-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gravityforms/gravityforms-fa_IR.l10n.php"
  "$PLUGIN_ROOT/languages/providers/gravityflow/gravityflow-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php"
  "$PLUGIN_ROOT/languages/providers/gravityview/gravityview-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gravityview/gravityview-fa_IR.l10n.php"
  "$PLUGIN_ROOT/languages/providers/gravityperks/gravityperks-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gravityperks/gravityperks-fa_IR.l10n.php"
  "$PLUGIN_ROOT/languages/providers/gp-file-upload-pro/gp-file-upload-pro-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gp-file-upload-pro/gp-file-upload-pro-fa_IR.l10n.php"
  "$PLUGIN_ROOT/languages/providers/gp-advanced-select/gp-advanced-select-fa_IR.mo"
  "$PLUGIN_ROOT/languages/providers/gp-advanced-select/gp-advanced-select-fa_IR.l10n.php"
)
for required in "${REQUIRED_PATHS[@]}"; do
  grep -Fxq "$required" "$LISTING" || fail "required ZIP entry missing: $required"
done

FORBIDDEN_PREFIXES=(
  "$PLUGIN_ROOT/.git/"
  "$PLUGIN_ROOT/.github/"
  "$PLUGIN_ROOT/tests/"
  "$PLUGIN_ROOT/tools/"
  "$PLUGIN_ROOT/docs/"
  "$PLUGIN_ROOT/vendor/"
  "$PLUGIN_ROOT/.wordpress-core/"
  "$PLUGIN_ROOT/.phpunit.cache/"
  "$PLUGIN_ROOT/languages/providers/gravityforms/source/"
  "$PLUGIN_ROOT/languages/providers/gravityflow/source/"
  "$PLUGIN_ROOT/languages/providers/gravityview/source/"
  "$PLUGIN_ROOT/languages/providers/gravityperks/source/"
  "$PLUGIN_ROOT/languages/providers/gp-file-upload-pro/source/"
  "$PLUGIN_ROOT/languages/providers/gp-advanced-select/source/"
)
for forbidden in "${FORBIDDEN_PREFIXES[@]}"; do
  if grep -Fq "$forbidden" "$LISTING"; then
    fail "forbidden repository/development path present in ZIP: $forbidden"
  fi
done

FORBIDDEN_FILES=(
  "$PLUGIN_ROOT/composer.json"
  "$PLUGIN_ROOT/composer.lock"
  "$PLUGIN_ROOT/phpcs.xml.dist"
  "$PLUGIN_ROOT/phpunit.xml.dist"
  "$PLUGIN_ROOT/AGENTS.md"
  "$PLUGIN_ROOT/COMPLIANCE_REPORT.md"
  "$PLUGIN_ROOT/README.md"
  "$PLUGIN_ROOT/.distignore"
  "$PLUGIN_ROOT/.gitignore"
)
for forbidden in "${FORBIDDEN_FILES[@]}"; do
  if grep -Fxq "$forbidden" "$LISTING"; then
    fail "forbidden repository-only file present in ZIP: $forbidden"
  fi
done

if grep -E -q "^${PLUGIN_ROOT}/.*\.zip$" "$LISTING"; then
  fail 'nested ZIP/package material is forbidden in the production package'
fi
if grep -E -q "^${PLUGIN_ROOT}/languages/providers/[^/]+/source/" "$LISTING"; then
  fail 'provider editable source-authority material leaked into the production package'
fi

unzip -q "$ZIP_PATH" -d "$EXTRACTED"
ROOT="$EXTRACTED/$PLUGIN_ROOT"
[[ -d "$ROOT" ]] || fail 'extracted plugin root is missing'
if find "$ROOT" -type l -print -quit | grep -q .; then
  fail 'symbolic links are not allowed in the production package'
fi

HEADER_VERSION="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$ROOT/$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]+$//')"
CONSTANT_VERSION="$(grep -m1 "define( 'PGR_VERSION'" "$ROOT/$PLUGIN_FILE" | sed -E "s/.*'PGR_VERSION',[[:space:]]*'([^']+)'.*/\1/")"
STABLE_TAG="$(grep -m1 -E '^Stable tag:' "$ROOT/readme.txt" | sed -E 's/^Stable tag:[[:space:]]*//; s/[[:space:]]+$//')"
[[ "$HEADER_VERSION" == "$EXPECTED_VERSION" ]] || fail "packaged plugin header version mismatch: $HEADER_VERSION"
[[ "$CONSTANT_VERSION" == "$EXPECTED_VERSION" ]] || fail "packaged PGR_VERSION mismatch: $CONSTANT_VERSION"
[[ "$STABLE_TAG" == "$EXPECTED_VERSION" ]] || fail "packaged Stable tag mismatch: $STABLE_TAG"

php -r '
$manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$expected = [
    "plugin" => "persian-gravityforms",
    "version" => $argv[2],
    "source_commit" => $argv[3],
    "source_tree" => $argv[4],
];
foreach ($expected as $key => $value) {
    if (($manifest[$key] ?? null) !== $value) {
        fwrite(STDERR, "release manifest mismatch for {$key}\n");
        exit(1);
    }
}
' "$ROOT/release-manifest.json" "$EXPECTED_VERSION" "$EXPECTED_SOURCE_HEAD" "$EXPECTED_SOURCE_TREE" || fail 'release manifest identity validation failed'

[[ ! -e "$ROOT/vendor" ]] || fail 'development-only vendor directory is present in extracted package'
[[ ! -e "$ROOT/tests" ]] || fail 'tests directory is present in extracted package'
[[ ! -e "$ROOT/tools" ]] || fail 'tools directory is present in extracted package'
[[ ! -e "$ROOT/docs" ]] || fail 'docs directory is present in extracted package'

printf 'PRODUCTION_PACKAGE_VERIFY=PASS version=%s source=%s tree=%s zip=%s\n' \
  "$EXPECTED_VERSION" "$EXPECTED_SOURCE_HEAD" "$EXPECTED_SOURCE_TREE" "$ZIP_NAME"
