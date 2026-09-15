#!/usr/bin/env bash
set -Eeuo pipefail

ZIP_PATH="${PGR_PACKAGE_ZIP:-}"
EXPECTED_VERSION="${PGR_PACKAGE_VERSION:-}"
EXPECTED_SOURCE_SHA="${PGR_PACKAGE_SOURCE_SHA:-}"
EXPECTED_SOURCE_TREE="${PGR_PACKAGE_SOURCE_TREE:-}"
WP_VERSION="${PGR_SMOKE_WP_VERSION:-6.7.2}"
DB_PASSWORD="${PGR_SMOKE_DB_PASSWORD:-}"
EVIDENCE_PATH="${PGR_SMOKE_EVIDENCE:-}"
PLUGIN_ROOT='persian-gravityforms'
PLUGIN_BASENAME='persian-gravityforms/persian-gravityforms.php'

fail() {
  echo "production-package-smoke: $*" >&2
  exit 1
}

for command in php wp unzip sha256sum grep sed awk; do
  command -v "$command" >/dev/null 2>&1 || fail "required command is unavailable: $command"
done

[[ -n "$ZIP_PATH" && -f "$ZIP_PATH" ]] || fail 'PGR_PACKAGE_ZIP must identify the generated/published ZIP'
[[ "$EXPECTED_VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] || fail 'PGR_PACKAGE_VERSION must be stable SemVer'
[[ "$EXPECTED_SOURCE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'PGR_PACKAGE_SOURCE_SHA must be an exact Git commit id'
[[ "$EXPECTED_SOURCE_TREE" =~ ^[0-9a-f]{40}$ ]] || fail 'PGR_PACKAGE_SOURCE_TREE must be an exact Git tree id'
[[ -n "$DB_PASSWORD" ]] || fail 'PGR_SMOKE_DB_PASSWORD is required'
[[ -n "$EVIDENCE_PATH" ]] || fail 'PGR_SMOKE_EVIDENCE is required'

ZIP_PATH="$(cd "$(dirname "$ZIP_PATH")" && pwd)/$(basename "$ZIP_PATH")"
WORK="$(mktemp -d)"
WP_ROOT="$WORK/wordpress"
cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

mkdir -p "$(dirname "$EVIDENCE_PATH")"

wp core download \
  --path="$WP_ROOT" \
  --version="$WP_VERSION" \
  --locale=en_US \
  --skip-content \
  --force

wp config create \
  --path="$WP_ROOT" \
  --dbname=wordpress \
  --dbuser=root \
  --dbpass="$DB_PASSWORD" \
  --dbhost=127.0.0.1:3306 \
  --skip-check

wp core install \
  --path="$WP_ROOT" \
  --url=http://persiangravity-release.test \
  --title='PersianGravity Release Smoke' \
  --admin_user=release-admin \
  --admin_password='release-smoke-password-123!' \
  --admin_email=release@example.test \
  --skip-email

observed_wp="$(wp core version --path="$WP_ROOT")"
[[ "$observed_wp" == "$WP_VERSION" ]] || fail "WordPress version mismatch: expected=$WP_VERSION observed=$observed_wp"

# The packaging smoke intentionally proves the bounded no-Gravity-Forms path.
wp eval --path="$WP_ROOT" 'if (class_exists("GFForms") || class_exists("GF_Field") || class_exists("GF_Fields")) { fwrite(STDERR, "Gravity Forms unexpectedly present.\n"); exit(1); }'

wp plugin install "$ZIP_PATH" --path="$WP_ROOT" --activate
wp plugin is-active "$PLUGIN_ROOT" --path="$WP_ROOT" || fail 'installed PersianGravity plugin is not active'

PGR_EXPECTED_VERSION="$EXPECTED_VERSION" wp eval --path="$WP_ROOT" '
$expected = getenv("PGR_EXPECTED_VERSION");
if (!defined("PGR_VERSION") || PGR_VERSION !== $expected) {
    fwrite(STDERR, "PGR_VERSION mismatch after artifact activation.\n");
    exit(1);
}
if (!function_exists("pgr_initialize")) {
    fwrite(STDERR, "PersianGravity bootstrap function is unavailable.\n");
    exit(1);
}
if (class_exists("GFForms") || class_exists("GF_Field") || class_exists("GF_Fields")) {
    fwrite(STDERR, "Gravity Forms unexpectedly present after activation.\n");
    exit(1);
}
do_action("gform_loaded");
echo PGR_VERSION;
' > "$WORK/runtime-version.txt"

[[ "$(cat "$WORK/runtime-version.txt")" == "$EXPECTED_VERSION" ]] || fail 'runtime version output does not match expected candidate version'

INSTALLED_ROOT="$WP_ROOT/wp-content/plugins/$PLUGIN_ROOT"
[[ -f "$INSTALLED_ROOT/persian-gravityforms.php" ]] || fail 'installed plugin entrypoint is missing'
[[ -f "$INSTALLED_ROOT/release-manifest.json" ]] || fail 'installed release identity manifest is missing'

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
        fwrite(STDERR, "installed release manifest mismatch for {$key}\n");
        exit(1);
    }
}
' "$INSTALLED_ROOT/release-manifest.json" "$EXPECTED_VERSION" "$EXPECTED_SOURCE_SHA" "$EXPECTED_SOURCE_TREE" || fail 'installed artifact source identity is invalid'

active_basename="$(wp eval --path="$WP_ROOT" 'echo in_array("persian-gravityforms/persian-gravityforms.php", (array) get_option("active_plugins", []), true) ? "yes" : "no";')"
[[ "$active_basename" == 'yes' ]] || fail "expected active plugin basename not found: $PLUGIN_BASENAME"

zip_sha="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
php_version="$(php -r 'echo PHP_VERSION;')"

{
  printf 'ARTIFACT_INSTALL_SMOKE=PASS\n'
  printf 'ZIP=%s\n' "$(basename "$ZIP_PATH")"
  printf 'ZIP_SHA256=%s\n' "$zip_sha"
  printf 'PLUGIN_VERSION=%s\n' "$EXPECTED_VERSION"
  printf 'SOURCE_HEAD=%s\n' "$EXPECTED_SOURCE_SHA"
  printf 'SOURCE_TREE=%s\n' "$EXPECTED_SOURCE_TREE"
  printf 'WORDPRESS_VERSION=%s\n' "$observed_wp"
  printf 'PHP_VERSION=%s\n' "$php_version"
  printf 'PLUGIN_ACTIVE=yes\n'
  printf 'PLUGIN_BOOTSTRAP=PASS\n'
  printf 'GRAVITY_FORMS_AVAILABLE=no\n'
  printf 'NO_GRAVITY_FORMS_PATH=PASS\n'
} | tee "$EVIDENCE_PATH"
