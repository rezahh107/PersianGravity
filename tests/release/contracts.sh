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
  local name="$1"
  PASS_COUNT=$((PASS_COUNT + 1))
  printf 'PASS %02d - %s\n' "$PASS_COUNT" "$name"
}

expect_pass() {
  local name="$1"
  shift
  if "$@" >/tmp/pgr-release-contract.out 2>/tmp/pgr-release-contract.err; then
    pass "$name"
    return
  fi

  echo "Expected PASS: $name" >&2
  cat /tmp/pgr-release-contract.out >&2 || true
  cat /tmp/pgr-release-contract.err >&2 || true
  exit 1
}

expect_fail() {
  local name="$1"
  shift
  if "$@" >/tmp/pgr-release-contract.out 2>/tmp/pgr-release-contract.err; then
    echo "Expected failure but command passed: $name" >&2
    cat /tmp/pgr-release-contract.out >&2 || true
    exit 1
  fi

  pass "$name"
}

make_fixture() {
  local fixture
  fixture="$(mktemp -d "$TMP_ROOT/fixture.XXXXXX")"
  mkdir -p "$fixture/languages" "$fixture/docs"
  cp persian-gravityforms.php readme.txt README.md AGENTS.md "$fixture/"
  cp languages/README.md languages/persian-gravityforms.pot languages/persian-gravityforms-fa_IR.po "$fixture/languages/"
  cp docs/ARCHITECTURE.md docs/LOCALIZATION.md "$fixture/docs/"
  printf '%s\n' "$fixture"
}

semver_next() {
  local current="$1" bump="$2"
  local major minor patch
  IFS=. read -r major minor patch <<< "$current"
  case "$bump" in
    patch) patch=$((patch + 1)) ;;
    minor) minor=$((minor + 1)); patch=0 ;;
    major) major=$((major + 1)); minor=0; patch=0 ;;
    *) return 1 ;;
  esac
  printf '%d.%d.%d\n' "$major" "$minor" "$patch"
}

TOOL=(php tools/release/release-tool.php)
CURRENT="$(${TOOL[@]} current)"
PATCH="$(semver_next "$CURRENT" patch)"
MINOR="$(semver_next "$CURRENT" minor)"
MAJOR="$(semver_next "$CURRENT" major)"

expect_pass 'synchronized active release metadata is accepted' "${TOOL[@]}" verify

actual="$(${TOOL[@]} next patch)"
[[ "$actual" == "$PATCH" ]] || { echo "patch bump mismatch: $actual != $PATCH" >&2; exit 1; }
pass 'patch SemVer computation'

actual="$(${TOOL[@]} next minor)"
[[ "$actual" == "$MINOR" ]] || { echo "minor bump mismatch: $actual != $MINOR" >&2; exit 1; }
pass 'minor SemVer computation resets patch'

actual="$(${TOOL[@]} next major)"
[[ "$actual" == "$MAJOR" ]] || { echo "major bump mismatch: $actual != $MAJOR" >&2; exit 1; }
pass 'major SemVer computation resets minor and patch'

expect_fail 'unsupported bump is refused' "${TOOL[@]}" next prerelease

fixture="$(make_fixture)"
while IFS= read -r file; do
  sed -i "s/${CURRENT//./\\.}/${CURRENT}-beta/g" "$file"
done < <(find "$fixture" -type f -print)
expect_fail 'non-stable source version is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "0,/Version: $CURRENT/{s/Version: $CURRENT/Version: 9.9.9/}" "$fixture/persian-gravityforms.php"
expect_fail 'plugin header and PGR_VERSION mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "s/^Stable tag: $CURRENT$/Stable tag: 9.9.9/" "$fixture/readme.txt"
expect_fail 'Stable tag mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "s/- Plugin version: \`$CURRENT\`/- Plugin version: \`9.9.9\`/" "$fixture/README.md"
expect_fail 'active README version mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "s/- Version: \`$CURRENT\`/- Version: \`9.9.9\`/" "$fixture/AGENTS.md"
expect_fail 'active AGENTS version mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "s/own-plugin text domain in $CURRENT/own-plugin text domain in 9.9.9/" "$fixture/languages/README.md"
expect_fail 'active language package version mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
sed -i "s/Architecture — $CURRENT/Architecture — 9.9.9/" "$fixture/docs/ARCHITECTURE.md"
expect_fail 'active architecture version mismatch is refused' "${TOOL[@]}" --root="$fixture" verify

fixture="$(make_fixture)"
php -r '
$p=$argv[1];
$s=file_get_contents($p);
$s=preg_replace("/^= Unreleased =\\R.*?(?=^= [0-9]+\\.[0-9]+\\.[0-9]+ =\\R)/ms", "= Unreleased =\n\n", $s, 1, $count);
if ($count !== 1) { exit(2); }
file_put_contents($p, $s);
' "$fixture/readme.txt"
expect_fail 'empty Unreleased changelog is refused' "${TOOL[@]}" --root="$fixture" prepare patch

fixture="$(make_fixture)"
historical_before="$(awk -v version="$CURRENT" '$0 == "= " version " =" { capture=1 } capture { print }' "$fixture/readme.txt" | sha256sum | awk '{print $1}')"
expect_pass 'release preparation succeeds with meaningful Unreleased notes' "${TOOL[@]}" --root="$fixture" prepare patch
historical_after="$(awk -v version="$CURRENT" '$0 == "= " version " =" { capture=1 } capture { print }' "$fixture/readme.txt" | sha256sum | awk '{print $1}')"
[[ "$historical_before" == "$historical_after" ]] || { echo 'Historical changelog changed during release preparation.' >&2; exit 1; }
grep -Fxq "= $PATCH =" "$fixture/readme.txt" || { echo 'Candidate changelog section missing.' >&2; exit 1; }
first_unreleased_line="$(awk '/^= Unreleased =$/{getline; print; exit}' "$fixture/readme.txt")"
[[ -z "$first_unreleased_line" ]] || { echo 'Fresh Unreleased section is not empty.' >&2; exit 1; }
pass 'release preparation preserves historical changelog and opens fresh Unreleased'

# Build once through the single production package builder, then mutate copies to
# exercise verifier failures without introducing a second packaging implementation.
rm -rf dist
expect_pass 'shared production package builder creates a validated artifact' bash tools/build-production-package.sh
SOURCE_HEAD="$(git rev-parse HEAD)"
SOURCE_TREE="$(git rev-parse HEAD^{tree})"
ZIP="dist/persian-gravityforms-${CURRENT}.zip"
CHECKSUM="${ZIP}.sha256"
[[ -s "$ZIP" && -s "$CHECKSUM" ]] || { echo 'Expected package outputs are missing.' >&2; exit 1; }
expect_pass 'valid package verifier accepts builder output' bash tools/release/verify-production-package.sh "$ZIP" "$CHECKSUM" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

bad="$TMP_ROOT/top-level"
mkdir -p "$bad/unpacked"
unzip -q "$ZIP" -d "$bad/unpacked"
mv "$bad/unpacked/persian-gravityforms" "$bad/unpacked/wrong-root"
(
  cd "$bad/unpacked"
  zip -X -qr "$bad/persian-gravityforms-${CURRENT}.zip" wrong-root
)
(
  cd "$bad"
  sha256sum "persian-gravityforms-${CURRENT}.zip" > "persian-gravityforms-${CURRENT}.zip.sha256"
)
expect_fail 'package with wrong top-level directory is refused' bash tools/release/verify-production-package.sh "$bad/persian-gravityforms-${CURRENT}.zip" "$bad/persian-gravityforms-${CURRENT}.zip.sha256" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

bad="$TMP_ROOT/forbidden"
mkdir -p "$bad/unpacked"
unzip -q "$ZIP" -d "$bad/unpacked"
mkdir -p "$bad/unpacked/persian-gravityforms/tests"
printf 'must-not-ship\n' > "$bad/unpacked/persian-gravityforms/tests/leak.txt"
(
  cd "$bad/unpacked"
  zip -X -qr "$bad/persian-gravityforms-${CURRENT}.zip" persian-gravityforms
)
(
  cd "$bad"
  sha256sum "persian-gravityforms-${CURRENT}.zip" > "persian-gravityforms-${CURRENT}.zip.sha256"
)
expect_fail 'forbidden repository-only package material is refused' bash tools/release/verify-production-package.sh "$bad/persian-gravityforms-${CURRENT}.zip" "$bad/persian-gravityforms-${CURRENT}.zip.sha256" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

bad="$TMP_ROOT/provider-source"
mkdir -p "$bad/unpacked"
unzip -q "$ZIP" -d "$bad/unpacked"
mkdir -p "$bad/unpacked/persian-gravityforms/languages/providers/gravityforms/source"
printf 'private source authority\n' > "$bad/unpacked/persian-gravityforms/languages/providers/gravityforms/source/leak.po"
(
  cd "$bad/unpacked"
  zip -X -qr "$bad/persian-gravityforms-${CURRENT}.zip" persian-gravityforms
)
(
  cd "$bad"
  sha256sum "persian-gravityforms-${CURRENT}.zip" > "persian-gravityforms-${CURRENT}.zip.sha256"
)
expect_fail 'provider source-authority material is refused from package' bash tools/release/verify-production-package.sh "$bad/persian-gravityforms-${CURRENT}.zip" "$bad/persian-gravityforms-${CURRENT}.zip.sha256" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

bad="$TMP_ROOT/missing-required"
mkdir -p "$bad/unpacked"
unzip -q "$ZIP" -d "$bad/unpacked"
rm "$bad/unpacked/persian-gravityforms/includes/localization/products.php"
(
  cd "$bad/unpacked"
  zip -X -qr "$bad/persian-gravityforms-${CURRENT}.zip" persian-gravityforms
)
(
  cd "$bad"
  sha256sum "persian-gravityforms-${CURRENT}.zip" > "persian-gravityforms-${CURRENT}.zip.sha256"
)
expect_fail 'package missing required runtime file is refused' bash tools/release/verify-production-package.sh "$bad/persian-gravityforms-${CURRENT}.zip" "$bad/persian-gravityforms-${CURRENT}.zip.sha256" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

bad="$TMP_ROOT/checksum"
mkdir -p "$bad"
cp "$ZIP" "$bad/persian-gravityforms-${CURRENT}.zip"
printf '%064d  %s\n' 0 "persian-gravityforms-${CURRENT}.zip" > "$bad/persian-gravityforms-${CURRENT}.zip.sha256"
expect_fail 'incorrect checksum is refused' bash tools/release/verify-production-package.sh "$bad/persian-gravityforms-${CURRENT}.zip" "$bad/persian-gravityforms-${CURRENT}.zip.sha256" "$CURRENT" "$SOURCE_HEAD" "$SOURCE_TREE"

SHA_A='1111111111111111111111111111111111111111'
SHA_B='2222222222222222222222222222222222222222'
TREE='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
TREE_B='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
PUBLISH_BASE=("${TOOL[@]}" validate-publish
  --source-version="$CURRENT"
  --candidate-version="$CURRENT"
  --source-sha="$SHA_A"
  --candidate-sha="$SHA_B"
  --integrated-sha="$SHA_A"
  --source-tree="$TREE"
  --candidate-tree="$TREE"
  --candidate-branch="release/v${CURRENT}"
  --tag="v${CURRENT}"
  --candidate-merged=1
  --tag-exists=0
  --release-exists=0)

expect_pass 'valid merged release candidate publish identity is accepted' "${PUBLISH_BASE[@]}"
expect_fail 'candidate/source version mismatch is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$PATCH" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=0 --release-exists=0
expect_fail 'existing production tag is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=1 --release-exists=0
expect_fail 'existing GitHub Release is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=0 --release-exists=1
expect_fail 'main that is not exact integrated Release PR result is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_B" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=0 --release-exists=0
expect_fail 'release source tree that differs from reviewed candidate is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE_B" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=0 --release-exists=0
expect_fail 'unmerged release candidate is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${CURRENT}" --candidate-merged=0 --tag-exists=0 --release-exists=0
expect_fail 'non-release candidate branch is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="feature/not-release" --tag="v${CURRENT}" --candidate-merged=1 --tag-exists=0 --release-exists=0
expect_fail 'tag/version mismatch is refused' "${TOOL[@]}" validate-publish --source-version="$CURRENT" --candidate-version="$CURRENT" --source-sha="$SHA_A" --candidate-sha="$SHA_B" --integrated-sha="$SHA_A" --source-tree="$TREE" --candidate-tree="$TREE" --candidate-branch="release/v${CURRENT}" --tag="v${PATCH}" --candidate-merged=1 --tag-exists=0 --release-exists=0

printf 'RELEASE_CONTRACTS=PASS tests=%d version=%s\n' "$PASS_COUNT" "$CURRENT"
