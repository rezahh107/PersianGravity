<?php
/** Real pinned WordPress translation code, with only host/database services stubbed. */
$core = getenv('PGR_WP_CORE');
if (!$core || !is_file($core . '/wp-includes/l10n.php')) {
    fwrite(STDERR, "PGR_WP_CORE must point to the pinned WordPress checkout (see docs/LOCALIZATION.md).\n");
    exit(2);
}
define('ABSPATH', rtrim($core, '/') . '/');
define('WPINC', 'wp-includes');
define('HOUR_IN_SECONDS', 3600);
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/pgr-core-' . getmypid());
define('WP_LANG_DIR', WP_CONTENT_DIR . '/languages');
mkdir(WP_LANG_DIR . '/plugins', 0777, true);
mkdir(WP_LANG_DIR . '/themes', 0777, true);
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/pomo/mo.php';
foreach (['class-wp-translation-file', 'class-wp-translation-file-php', 'class-wp-translation-file-mo', 'class-wp-translation-controller', 'class-wp-translations'] as $file) {
    require ABSPATH . WPINC . '/l10n/' . $file . '.php';
}
require ABSPATH . WPINC . '/class-wp-textdomain-registry.php';
require ABSPATH . WPINC . '/l10n.php';
$GLOBALS['locale'] = 'fa_IR';
$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
add_filter('pre_determine_locale', static fn() => $GLOBALS['locale']);
function trailingslashit($value) { return rtrim($value, '/\\') . '/'; }
function get_template_directory() { return WP_CONTENT_DIR . '/themes/example'; }
function get_stylesheet_directory() { return get_template_directory(); }
function wp_cache_get($key, $group = '', $force = false, &$found = null) { $found = false; return false; }
function wp_cache_set(...$args) { return true; }
function wp_json_encode($data) { return json_encode($data); }
function _doing_it_wrong($function, $message, $version) { throw new RuntimeException($function . ': ' . $message); }
function _deprecated_function(...$args) { throw new RuntimeException('Unexpected deprecated API'); }
function plugins_url($path = '', $plugin = '') { return 'https://example.test/plugins/persian-gravity/'; }
