#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${PGR_REPOSITORY_ROOT:-$(pwd)}"
OUTPUT_FILE="${PGR_PACKAGE_OUTPUT_FILE:-${GITHUB_OUTPUT:-}}"
EXPECTED_VERSION="${PGR_PACKAGE_VERSION:-}"
EXPECTED_SOURCE_SHA="${PGR_PACKAGE_SOURCE_SHA:-}"
PLUGIN_ROOT='persian-gravityforms'
PLUGIN_FILE='persian-gravityforms.php'
DIST="$ROOT/dist"
STAGE="$DIST/$PLUGIN_ROOT"

fail() {
  echo "production-package-build: $*" >&2
  exit 1
}

for command in git php zip unzip sha256sum cp find touch chmod sort; do
  command -v "$command" >/dev/null 2>&1 || fail "required command is unavailable: $command"
done

cd "$ROOT"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "repository root is not a Git working tree: $ROOT"
SOURCE_HEAD="$(git rev-parse HEAD)"
SOURCE_TREE="$(git rev-parse HEAD^{tree})"
SOURCE_DATE_EPOCH="$(git show -s --format=%ct "$SOURCE_HEAD")"

if [[ -n "$EXPECTED_SOURCE_SHA" && "$EXPECTED_SOURCE_SHA" != "$SOURCE_HEAD" ]]; then
  fail "requested package source does not match checkout: requested=$EXPECTED_SOURCE_SHA actual=$SOURCE_HEAD"
fi

VERSION="$(php tools/release/release-tool.php current)"
if [[ -n "$EXPECTED_VERSION" && "$EXPECTED_VERSION" != "$VERSION" ]]; then
  fail "requested package version does not match source: requested=$EXPECTED_VERSION source=$VERSION"
fi
php tools/release/release-tool.php verify --expected="$VERSION" >/dev/null

ZIP_NAME="$PLUGIN_ROOT-$VERSION.zip"
CHECKSUM_NAME="$ZIP_NAME.sha256"
ARTIFACT_NAME="$PLUGIN_ROOT-$VERSION-installable-${SOURCE_HEAD:0:12}"

rm -rf "$DIST"
mkdir -p "$STAGE"

for file in "$PLUGIN_FILE" uninstall.php readme.txt; do
  [[ -f "$file" ]] || fail "required runtime file is missing from repository: $file"
  cp -p "$file" "$STAGE/"
done
for directory in admin assets includes languages; do
  [[ -d "$directory" ]] || fail "required runtime directory is missing from repository: $directory"
  cp -a "$directory" "$STAGE/"
done

# Editable provider authority/source material is intentionally not distributed.
if [[ -d "$STAGE/languages/providers" ]]; then
  while IFS= read -r -d '' source_dir; do
    rm -rf "$source_dir"
  done < <(find "$STAGE/languages/providers" -type d -name source -print0)
fi

# Build-only source identity is generated into the installable package.
php -r '
$manifest = [
    "schema_version" => 1,
    "plugin" => "persian-gravityforms",
    "version" => $argv[1],
    "source_commit" => $argv[2],
    "source_tree" => $argv[3],
];
file_put_contents($argv[4], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$VERSION" "$SOURCE_HEAD" "$SOURCE_TREE" "$STAGE/release-manifest.json"

for required in \
  "$STAGE/$PLUGIN_FILE" \
  "$STAGE/uninstall.php" \
  "$STAGE/readme.txt" \
  "$STAGE/admin/class-pgr-product-admin.php" \
  "$STAGE/assets/js/pgr-structured-scanner-core.js" \
  "$STAGE/includes/class-pgr-localization.php" \
  "$STAGE/includes/class-pgr-gravity-perks-rtl.php" \
  "$STAGE/includes/localization/products.php" \
  "$STAGE/includes/localization/g007-products.php" \
  "$STAGE/includes/localization/registry.php" \
  "$STAGE/languages/persian-gravityforms-fa_IR.po" \
  "$STAGE/languages/persian-gravityforms-fa_IR.mo" \
  "$STAGE/languages/providers/gravityforms/gravityforms-fa_IR.mo" \
  "$STAGE/languages/providers/gravityforms/gravityforms-fa_IR.l10n.php" \
  "$STAGE/languages/providers/gravityflow/gravityflow-fa_IR.mo" \
  "$STAGE/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php" \
  "$STAGE/languages/providers/gravityview/gravityview-fa_IR.mo" \
  "$STAGE/languages/providers/gravityview/gravityview-fa_IR.l10n.php" \
  "$STAGE/languages/providers/gravityperks/gravityperks-fa_IR.mo" \
  "$STAGE/languages/providers/gravityperks/gravityperks-fa_IR.l10n.php" \
  "$STAGE/languages/providers/gp-file-upload-pro/gp-file-upload-pro-fa_IR.mo" \
  "$STAGE/languages/providers/gp-file-upload-pro/gp-file-upload-pro-fa_IR.l10n.php" \
  "$STAGE/languages/providers/gp-advanced-select/gp-advanced-select-fa_IR.mo" \
  "$STAGE/languages/providers/gp-advanced-select/gp-advanced-select-fa_IR.l10n.php"; do
  [[ -f "$required" ]] || fail "required staged production file is missing: $required"
done

if find "$STAGE" -type l -print -quit | grep -q .; then
  fail 'symbolic links are not allowed in the production stage'
fi
if find "$STAGE/languages/providers" -type d -name source -print -quit | grep -q .; then
  fail 'provider source-authority directories survived production staging'
fi
if find "$STAGE" -type f -name '*.zip' -print -quit | grep -q .; then
  fail 'nested ZIP/package material survived production staging'
fi

find "$STAGE" -type d -exec chmod 0755 {} +
find "$STAGE" -type f -exec chmod 0644 {} +
find "$STAGE" -exec touch -d "@$SOURCE_DATE_EPOCH" {} +

FILE_LIST="$(mktemp)"
cleanup() {
  rm -f "$FILE_LIST"
}
trap cleanup EXIT
(
  cd "$DIST"
  find "$PLUGIN_ROOT" -type f -print | LC_ALL=C sort > "$FILE_LIST"
  zip -X -q "$ZIP_NAME" -@ < "$FILE_LIST"
  sha256sum "$ZIP_NAME" > "$CHECKSUM_NAME"
  sha256sum -c "$CHECKSUM_NAME" >/dev/null
)

ZIP_PATH="$DIST/$ZIP_NAME"
CHECKSUM_PATH="$DIST/$CHECKSUM_NAME"
[[ -s "$ZIP_PATH" ]] || fail "installable ZIP was not created: $ZIP_PATH"
[[ -s "$CHECKSUM_PATH" ]] || fail "checksum file was not created: $CHECKSUM_PATH"

bash tools/release/verify-production-package.sh \
  "$ZIP_PATH" "$CHECKSUM_PATH" "$VERSION" "$SOURCE_HEAD" "$SOURCE_TREE" >/dev/null

ZIP_SHA="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"

emit_output() {
  local key="$1" value="$2"
  if [[ -n "$OUTPUT_FILE" ]]; then
    printf '%s=%s\n' "$key" "$value" >> "$OUTPUT_FILE"
  fi
}

emit_output version "$VERSION"
emit_output zip_name "$ZIP_NAME"
emit_output checksum_name "$CHECKSUM_NAME"
emit_output artifact_name "$ARTIFACT_NAME"
emit_output zip_sha "$ZIP_SHA"
emit_output source_head "$SOURCE_HEAD"
emit_output source_tree "$SOURCE_TREE"

printf 'PRODUCTION_PACKAGE_BUILD=PASS\n'
printf 'SOURCE_HEAD=%s\n' "$SOURCE_HEAD"
printf 'SOURCE_TREE=%s\n' "$SOURCE_TREE"
printf 'PLUGIN_VERSION=%s\n' "$VERSION"
printf 'ZIP_NAME=%s\n' "$ZIP_NAME"
printf 'ZIP_SHA256=%s\n' "$ZIP_SHA"
