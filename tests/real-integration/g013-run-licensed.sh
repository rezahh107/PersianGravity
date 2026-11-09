#!/usr/bin/env bash
# G013 disposable WU008 qualification only. No production site or vendor writes.
set -euo pipefail
: "${WU008_WP_CLI:?}" "${WU008_WP_PATH:?}" "${WU008_PACKAGE_DIR:?}"
: "${WU008_ARTIFACT_DIR:?}" "${WU008_PGR_SHA:?}"
wp() { php "$WU008_WP_CLI" --path="$WU008_WP_PATH" "$@"; }
evidence_dir="$WU008_ARTIFACT_DIR/vazir-coexistence-evidence"
mkdir -p "$evidence_dir" "$WU008_PACKAGE_DIR"
# Fail closed, but disclose only a fixed phase enum in the Actions summary.
phase() { printf '%s\n' "$1" > "$WU008_PACKAGE_DIR/g013-stage"; }
phase PRECHECK
snapshot="$WU008_PACKAGE_DIR/g013-isolated-baseline.sql"
uploads="$WU008_PACKAGE_DIR/g013-isolated-uploads.tar"
test ! -e "$WU008_WP_PATH/wp-content/plugins/vazir-font-wp"
if wp plugin is-active vazir-font-wp >/dev/null 2>&1; then
  echo "G013 baseline has active Vazir: refusing to qualify" >&2
  exit 1
fi
# A fresh dump before every profile restores persistent DB/options/entry data,
# rather than relying only on Chromium context isolation. File uploads are
# separately restored. The DB snapshot and upload archive are never published.
mkdir -p "$WU008_WP_PATH/wp-content/uploads"
wp db export "$snapshot" --add-drop-table
tar -C "$WU008_WP_PATH/wp-content" -cf "$uploads" uploads
export WU008_G013_DB_SNAPSHOT_SHA
WU008_G013_DB_SNAPSHOT_SHA="$(sha256sum "$snapshot" | cut -d' ' -f1)"
test "${#WU008_G013_DB_SNAPSHOT_SHA}" -eq 64
cleanup() {
  local old_rc=$?
  trap - EXIT
  set +e
  wp plugin deactivate vazir-font-wp >/dev/null 2>&1
  wp db import "$snapshot" >/dev/null
  local restore_rc=$?
  rm -rf "$WU008_WP_PATH/wp-content/plugins/vazir-font-wp"
  rm -rf "$WU008_WP_PATH/wp-content/uploads"
  tar -C "$WU008_WP_PATH/wp-content" -xf "$uploads"
  local upload_rc=$?
  wp cache flush >/dev/null 2>&1
  if [ "$restore_rc" -ne 0 ] || [ "$upload_rc" -ne 0 ]; then
    echo "G013 cleanup failed; disposable DB/uploads not restored" >&2
    exit 1
  fi
  exit "$old_rc"
}
trap cleanup EXIT
restore_profile() {
  local locale="$1"
  wp db import "$snapshot" >/dev/null
  rm -rf "$WU008_WP_PATH/wp-content/uploads"
  tar -C "$WU008_WP_PATH/wp-content" -xf "$uploads"
  wp cache flush >/dev/null
  if [ "$locale" = en_US ]; then
    # Same native English control used by existing qualified WU008 runs.
    wp option delete WPLANG >/dev/null
  else
    wp option update WPLANG fa_IR >/dev/null
  fi
  G013_LOCALE="$locale" wp eval '
    $u=get_user_by("login","runtime_admin");
    $locale=getenv("G013_LOCALE");
    if (!$u || !in_array($locale,array("fa_IR","en_US"),true))
      throw new RuntimeException("G013 invalid runtime user/locale");
    update_user_meta($u->ID,"locale",$locale);
  '
  # WordPress's request-local locale/user caches must not be used as the
  # readback for a just-mutated WP_User. Reopen the real runtime.
  G013_LOCALE="$locale" wp eval '
    $u=get_user_by("login","runtime_admin");
    $want=getenv("G013_LOCALE");
    $state=array("target"=>$want,"site"=>get_locale(),
      "user"=>get_user_locale($u),"stored_user"=>(string)get_user_meta($u->ID,"locale",true),
      "WPLANG"=>(string)get_option("WPLANG",""));
    fwrite(STDERR,"G013_LOCALE_READBACK ".wp_json_encode($state)."\n");
    if ($state["site"]!==$want || $state["user"]!==$want ||
      $state["stored_user"]!==$want)
      throw new RuntimeException("G013 fresh-request locale restore/isolation mismatch");
  '
  # G-014's independently owned native Grid RTL switch must remain OFF.
  wp eval '
    $s=get_option("pgr_settings",array());
    if (!is_array($s) || !empty($s["gravity_flow_inbox_rtl"]))
      throw new RuntimeException("G013 requires G014 native RTL OFF");
  '
  if wp plugin is-active vazir-font-wp >/dev/null 2>&1; then
    echo "Restored profile is unexpectedly WITH_VAZIR" >&2
    exit 1
  fi
}
for locale in fa_IR en_US; do
  phase "BASELINE_$locale"
  restore_profile "$locale"
  test ! -d "$WU008_WP_PATH/wp-content/plugins/vazir-font-wp"
  node tests/real-integration/vazir-coexistence-browser.mjs BASELINE "$locale"
done
phase RELEASE_DOWNLOAD
release=https://github.com/rezahh107/Vazir/releases/download/v1.5.0
for asset in vazir-font-wp-1.5.0.zip vazir-font-wp-1.5.0.zip.sha256 release-manifest.json; do
  if ! curl --fail --location --retry 4 --retry-delay 2 --connect-timeout 20 --max-time 180 \
    -o "$WU008_PACKAGE_DIR/$asset" "$release/$asset"; then
    echo "G013_OFFICIAL_RELEASE_ASSET_DOWNLOAD_FAILED: cannot download official v1.5.0 asset $asset. Inspect the actual HTTP/network/release-asset failure; repository visibility is not inferred from curl failure. Use only the exact official release asset; no rebuilt ZIP, source checkout, or substitute fixture accepted." >&2
    exit 1
  fi
done
node tests/real-integration/vazir-release-verify.mjs "$WU008_PACKAGE_DIR" > "$evidence_dir/official-release.json"
test -s "$evidence_dir/official-release.json"
phase RELEASE_VERIFIED
unzip -q "$WU008_PACKAGE_DIR/vazir-font-wp-1.5.0.zip" -d "$WU008_WP_PATH/wp-content/plugins"
for locale in fa_IR en_US; do
  phase "WITH_VAZIR_$locale"
  restore_profile "$locale"
  wp plugin activate vazir-font-wp
  test "$(wp plugin get vazir-font-wp --field=version)" = 1.5.0
  settings_file="$WU008_PACKAGE_DIR/g013-current-settings.json"
  G013_SETTINGS_FILE="$settings_file" wp eval '
    if (!defined("VAZIR_FONT_VERSION") || VAZIR_FONT_VERSION!=="1.5.0" ||
      !in_array("vazir-font-wp/vazir-font-wp.php",(array)get_option("active_plugins"),true))
      throw new RuntimeException("G013 Vazir not active at real pinned version");
    $o=get_option("vazir_font_options");
    foreach (array("enable_frontend","enable_admin","enable_gravity_forms") as $key)
      if (!is_array($o)||($o[$key]??null)!==true)
        throw new RuntimeException("G013 missing real enabled setting: ".$key);
    foreach(array("300","400","500","700","900") as $w)
      if(!in_array($w,$o["font_weights"],true))
        throw new RuntimeException("G013 missing enabled WOFF2 weight: ".$w);
    file_put_contents(getenv("G013_SETTINGS_FILE"),wp_json_encode(array(
      "version"=>VAZIR_FONT_VERSION,"active"=>true,"options"=>$o
    ),JSON_PRETTY_PRINT));
  '
  if [ "$locale" = fa_IR ]; then
    cp "$settings_file" "$evidence_dir/vazir-settings.json"
  else
    cmp "$settings_file" "$evidence_dir/vazir-settings.json"
  fi
  node tests/real-integration/vazir-coexistence-browser.mjs WITH_VAZIR "$locale"
done
phase PAIR_COMPARISON
node tests/real-integration/vazir-evidence-check.mjs "$evidence_dir" compare
phase INDEPENDENT_GATE
# Record native RTL failures accurately; the paired font gate rejects an
# unobserved mode or a new WITH_VAZIR-only mode change, not an existing ag-ltr.
node tests/real-integration/vazir-evidence-check.mjs "$evidence_dir" check
phase PACKAGE_INTEGRITY
if unzip -Z1 "dist/$WU008_PGR_ZIP_NAME" | grep -Ei '(vazir-font-wp|vazirmatn.*\.woff2)'; then
  echo 'Vazir font payload contaminated the PGR production ZIP' >&2
  exit 1
fi

phase COMPLETE
