<?php
/** No licensed product classes and no invented vendor script handles. */
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/tools/i18n/catalog.php';
require dirname(__DIR__, 2) . '/persian-gravityforms.php';
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    ++$checks;
    echo "PASS: $label\n";
}
function reset_domain($domain) {
    WP_Translation_Controller::get_instance()->unload_textdomain($domain);
    unset($GLOBALS['l10n'][$domain], $GLOBALS['l10n_unloaded'][$domain]);
    $GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
}
function write_fixture($path, $domain, $values) {
    $headers = ['Project-Id-Version' => 'Synthetic test fixture', 'Language' => 'fa_IR', 'Content-Type' => 'text/plain; charset=UTF-8', 'Plural-Forms' => 'nplurals=2; plural=(n > 1);', 'X-Domain' => $domain];
    $po = "# SYNTHETIC TEST FIXTURE ONLY\nmsgid \"\"\nmsgstr \"\"\n";
    foreach ($headers as $key => $value) { $po .= json_encode($key . ': ' . $value . "\n", JSON_UNESCAPED_SLASHES) . "\n"; }
    foreach ($values as $key => $value) { $po .= "\nmsgid " . json_encode($key) . "\nmsgstr " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n"; }
    file_put_contents($path . '.po', $po);
    $compiled = pgr_compile_catalog($path . '.po', $domain);
    file_put_contents($path . '.mo', $compiled['mo']);
    file_put_contents($path . '.l10n.php', $compiled['php']);
    return $compiled;
}
function cleanup($path) {
    if (!is_dir($path)) { return; }
    foreach (new FilesystemIterator($path) as $file) {
        if ($file->isDir()) { cleanup($file->getPathname()); } else { unlink($file->getPathname()); }
    }
    rmdir($path);
}
try {
    check(empty($GLOBALS['l10n']), 'BOOT-I18N-02 bootstrap has loaded no translations');
    foreach (['lang_dir_for_domain', 'load_translation_file', 'load_script_translations', 'pre_load_script_translations'] as $hook) {
        check(has_filter($hook) !== false, 'BOOT-I18N-01 early ' . $hook);
    }
    check(!did_action('plugins_loaded') && !did_action('init') && !did_action('gform_loaded'), 'bootstrap precedes all late lifecycle events');
    pgr_initialize();
    check(!class_exists('PGR_Core', false), 'optional vendors including GF absent without fatal');
    do_action('after_setup_theme');
    $products = require dirname(__DIR__, 2) . '/includes/localization/products.php';
    $provider_root = WP_CONTENT_DIR . '/providers';
    $overlay = new PGR_Localization($products, $provider_root);
    $overlay->hooks();
    foreach ($products as $domain => $product) {
        $provider_dir = $provider_root . '/' . $product['product'];
        mkdir($provider_dir, 0777, true);
        $provider = $provider_dir . '/' . $product['prefix'] . '-fa_IR';
        $upstream_dir = WP_CONTENT_DIR . '/upstream-' . $product['product'];
        mkdir($upstream_dir, 0777, true);
        $upstream = $upstream_dir . '/' . $product['prefix'] . '-fa_IR';
        $compiled = write_fixture($provider, $domain, ['Collision' => 'ارائه‌دهنده', 'Provider only' => 'فقط ارائه‌دهنده']);
        reset_domain($domain);
        check(__('Provider only', $domain) === 'فقط ارائه‌دهنده', "PHP-DISC-01/PHP-PREC-03 $domain provider-only JIT");
        check(__('Missing both', $domain) === 'Missing both', "$domain source string fallback");
        write_fixture($upstream, $domain, ['Collision' => 'بالادستی', 'Upstream only' => 'فقط بالادستی']);
        foreach (['php', 'mo'] as $format) {
            reset_domain($domain);
            $format_filter = static fn() => $format;
            add_filter('translation_file_format', $format_filter);
            $GLOBALS['wp_textdomain_registry']->set_custom_path($domain, $upstream_dir);
            check($GLOBALS['wp_textdomain_registry']->get($domain, 'fa_IR') === $upstream_dir . '/', "$domain upstream registry directory preserved");
            check(__('Collision', $domain) === 'ارائه‌دهنده', "PHP-PREC-01 $domain $format collision");
            check(__('Upstream only', $domain) === 'فقط بالادستی', "PHP-PREC-02/PHP-DISC-02 $domain $format upstream fallback");
            check(__('Provider only', $domain) === 'فقط ارائه‌دهنده', "$domain $format provider-only key");
            remove_filter('translation_file_format', $format_filter);
        }
        // Missing upstream PHP must not prevent Core trying an existing upstream MO.
        unlink($upstream . '.l10n.php');
        reset_domain($domain);
        $GLOBALS['wp_textdomain_registry']->set_custom_path($domain, $upstream_dir);
        check(__('Upstream only', $domain) === 'فقط بالادستی', "$domain upstream MO survives missing PHP candidate");
        check(__('Collision', $domain) === 'ارائه‌دهنده', "$domain PHP provider over MO upstream");
        // MO-only provider, including first-provider JIT without an upstream path.
        unlink($provider . '.l10n.php');
        reset_domain($domain);
        check(__('Provider only', $domain) === 'فقط ارائه‌دهنده', "$domain MO-only first provider");
        // Core lazily parses PHP; preserve upstream even if provider contents are invalid.
        file_put_contents($provider . '.l10n.php', '<?php return false;');
        reset_domain($domain);
        $GLOBALS['wp_textdomain_registry']->set_custom_path($domain, $upstream_dir);
        check(__('Upstream only', $domain) === 'فقط بالادستی', "$domain invalid provider PHP retains upstream");
        unlink($provider . '.l10n.php');
        unlink($provider . '.mo');
        reset_domain($domain);
        check($overlay->discover(false, $domain, 'fa_IR') === false, "PHP-PREC-04 $domain no provider discovery change");
        check($overlay->php_file($upstream . '.mo', $domain, 'fa_IR') === $upstream . '.mo', "PHP-PREC-04 $domain no provider path change");
        load_textdomain($domain, $upstream . '.mo', 'fa_IR');
        check(__('Collision', $domain) === 'بالادستی', "PHP-PREC-04 $domain original load works");
        // Domains loaded before the provider remain intact. No state is erased.
        file_put_contents($provider . '.l10n.php', $compiled['php']);
        load_textdomain($domain, $upstream . '.mo', 'fa_IR');
        check(__('Collision', $domain) === 'بالادستی', "$domain already-loaded upstream preserved; early boundary honest");
        check($overlay->discover(false, $domain, 'en_US') === false, "PHP-DISC-04 $domain non-fa_IR");
        check($overlay->php_file('/missing/file.mo', $domain, 'en_US') === '/missing/file.mo', "$domain non-fa_IR file passthrough");
        reset_domain($domain);
        $GLOBALS['locale'] = 'en_US';
        check(__('Collision', $domain) === 'Collision', "$domain real non-fa_IR gettext unaffected");
        $GLOBALS['locale'] = 'fa_IR';
        // An explicit unload followed by a reload must not be blocked by stale guards.
        reset_domain($domain);
        check(__('Collision', $domain) === 'ارائه‌دهنده', "$domain reload after locale/state reset");
        unload_textdomain($domain, true);
        check(__('Collision', $domain) === 'ارائه‌دهنده', "$domain Core reloadable unload preserves provider availability");
    }
    // Compile the plural/context PO fixture and consume both formats in actual Core.
    $compiled = pgr_compile_catalog(__DIR__ . '/fixtures/synthetic.po', 'gravityforms');
    $base = $provider_root . '/gravityforms/gravityforms-fa_IR';
    foreach (['php' => '.l10n.php', 'mo' => '.mo'] as $format => $extension) {
        file_put_contents($base . $extension, $compiled[$format]);
        reset_domain('gravityforms');
        $format_filter = static fn() => $format;
        add_filter('translation_file_format', $format_filter);
        check(_x('Synthetic context', 'test-context', 'gravityforms') === 'زمینه', "Core $format consumes generated context");
        check(_n('Synthetic one', 'Synthetic many', 0, 'gravityforms') === 'یکی', "Core $format Persian plural zero");
        check(_n('Synthetic one', 'Synthetic many', 2, 'gravityforms') === 'چندتا', "Core $format Persian plural multiple");
        check(__('Synthetic unreviewed', 'gravityforms') === 'Synthetic unreviewed', "Core $format excludes fuzzy entry");
        remove_filter('translation_file_format', $format_filter);
    }
    check($overlay->discover('/original/', 'unmanaged', 'fa_IR') === '/original/', 'PHP-DISC-03 unmanaged registry passthrough');
    check($overlay->php_file('/original.mo', 'unmanaged', 'fa_IR') === '/original.mo', 'unmanaged PHP pass-through');
    check($overlay->script_content('{upstream}', '/original.json', 'unapproved-handle', 'gravityforms') === '{upstream}', 'JS unmanaged handle untouched');
    check($overlay->script_fallback(null, false, 'unapproved-handle', 'gravityforms') === null, 'JS no unapproved provider fallback');
    // Pure content contract tests do not assert any vendor handle exists.
    $json = static function ($messages, $metadata = []) {
        return json_encode(['locale_data' => ['messages' => ['' => array_merge(['lang' => 'fa_IR', 'plural_forms' => 'nplurals=2; plural=(n > 1);'], $metadata)] + $messages]]);
    };
    $local = $json(['Collision' => ['ارائه‌دهنده'], 'Empty' => [''], "context\x04Word" => ['زمینه'], 'One' => ['یکی', 'چندتا']]);
    $upstream = $json(['Collision' => ['بالادستی'], 'Upstream only' => ['باقی'], 'Empty' => ['بالادستی']], ['custom' => 'retained']);
    $merged = json_decode(PGR_Localization::merge_json($upstream, $local, 'gravityforms'), true)['locale_data']['gravityforms'];
    check($merged['Collision'] === ['ارائه‌دهنده'], 'JS content collision provider wins');
    check($merged['Upstream only'] === ['باقی'], 'JS content upstream-only key survives');
    check($merged['Empty'] === ['بالادستی'], 'JS empty provider value does not shadow upstream');
    check($merged["context\x04Word"] === ['زمینه'] && $merged['One'] === ['یکی', 'چندتا'], 'JS context and plural arrays preserved');
    check($merged['']['custom'] === 'retained', 'JS upstream metadata retained');
    check(PGR_Localization::merge_json($upstream, '{invalid', 'gravityforms') === $upstream, 'JS invalid provider preserves original');
    check(PGR_Localization::merge_json('{invalid', $local, 'gravityforms') === false, 'JS invalid upstream allows next Core candidate');
    check(is_array(json_decode(PGR_Localization::merge_json(false, $local, 'gravityforms'), true)), 'JS no-upstream content valid');
    $incompatible = $json(['Collision' => ['محلی']], ['plural_forms' => 'nplurals=1; plural=0;']);
    check(PGR_Localization::merge_json($upstream, $incompatible, 'gravityforms') === $upstream, 'JS incompatible plural metadata fails safely');
    echo "TOTAL: $checks checks passed. JS vendor handle lifecycle NOT_EXECUTED_PACKAGE_UNAVAILABLE.\n";
} finally { cleanup(WP_CONTENT_DIR); }
