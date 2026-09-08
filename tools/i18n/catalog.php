<?php
/** Development-only PO compilation; never included by the plugin runtime. */
use Gettext\Loader\StrictPoLoader;
use Gettext\Generator\MoGenerator;

function pgr_compile_catalog(string $po, string $domain): array {
    $catalog = (new StrictPoLoader())->loadFile($po);
    $headers = iterator_to_array($catalog->getHeaders());
    if (($headers['Language'] ?? '') !== 'fa_IR' || ($headers['X-Domain'] ?? '') !== $domain ||
        ($headers['Plural-Forms'] ?? '') !== 'nplurals=2; plural=(n > 1);') {
        throw new RuntimeException("Invalid locale/domain/plural headers: $po");
    }
    $counts = ['translated' => 0, 'untranslated' => 0, 'fuzzy' => 0];
    $messages = [];
    $jed = ['' => ['domain' => $domain, 'lang' => 'fa_IR', 'plural_forms' => $headers['Plural-Forms']]];
    foreach ($catalog as $entry) {
        if ($entry->isDisabled()) { continue; }
        $fuzzy = $entry->getFlags()->has('fuzzy');
        $values = [$entry->getTranslation()];
        if ($entry->getPlural() !== null) {
            $values = array_merge($values, $entry->getPluralTranslations());
        }
        if ($fuzzy) {
            ++$counts['fuzzy'];
            $entry->disable();
            continue;
        }
        if (count(array_filter($values, static fn($value) => is_string($value) && $value !== '')) !== count($values) || ($entry->getPlural() !== null && count($values) !== 2)) {
            ++$counts['untranslated'];
            $entry->disable();
            continue;
        }
        ++$counts['translated'];
        $key = ($entry->getContext() !== null && $entry->getContext() !== '' ? $entry->getContext() . "\x04" : '') . $entry->getOriginal();
        $messages[$key] = implode("\0", $values);
        $jed[$key] = $values;
    }
    ksort($messages, SORT_STRING);
    ksort($jed, SORT_STRING);
    $php = array_change_key_case($headers);
    ksort($php, SORT_STRING);
    $php['messages'] = $messages;
    return [
        'counts' => $counts,
        'catalog' => $catalog,
        'mo' => (new MoGenerator())->includeHeaders()->generateString($catalog),
        'php' => "<?php\n// Generated from provider PO; do not edit.\nreturn " . var_export($php, true) . ";\n",
        'json' => json_encode(['domain' => $domain, 'locale_data' => [$domain => $jed]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
    ];
}
