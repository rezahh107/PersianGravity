<?php
/**
 * WU-008 request-local observational probe.
 * Evidence-only MU plugin; never changes translations or vendor behavior.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['wu008_observer'] = array(
    'surface_id' => isset( $_GET['wu008_surface'] ) ? sanitize_text_field( wp_unslash( $_GET['wu008_surface'] ) ) : '',
    'gettext' => array(),
    'translation_files' => array(),
    'js_before' => array(),
    'js_mutations' => array(),
);

function wu008_observer_active() {
    return ! empty( $GLOBALS['wu008_observer']['surface_id'] );
}

function wu008_observer_record_gettext( $translation, $text, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $GLOBALS['wu008_observer']['gettext'][] = array(
            'kind' => 'gettext',
            'domain' => $domain,
            'msgid' => (string) $text,
            'context' => '',
            'translation' => (string) $translation,
        );
    }
    return $translation;
}
add_filter( 'gettext', 'wu008_observer_record_gettext', PHP_INT_MAX, 3 );

function wu008_observer_record_context( $translation, $text, $context, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $GLOBALS['wu008_observer']['gettext'][] = array(
            'kind' => 'gettext_with_context',
            'domain' => $domain,
            'msgid' => (string) $text,
            'context' => (string) $context,
            'translation' => (string) $translation,
        );
    }
    return $translation;
}
add_filter( 'gettext_with_context', 'wu008_observer_record_context', PHP_INT_MAX, 4 );

function wu008_observer_record_ngettext( $translation, $single, $plural, $number, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $GLOBALS['wu008_observer']['gettext'][] = array(
            'kind' => 'ngettext',
            'domain' => $domain,
            'msgid' => (string) $single,
            'plural' => (string) $plural,
            'context' => '',
            'number' => (int) $number,
            'translation' => (string) $translation,
        );
    }
    return $translation;
}
add_filter( 'ngettext', 'wu008_observer_record_ngettext', PHP_INT_MAX, 5 );

function wu008_observer_record_ngettext_context( $translation, $single, $plural, $number, $context, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $GLOBALS['wu008_observer']['gettext'][] = array(
            'kind' => 'ngettext_with_context',
            'domain' => $domain,
            'msgid' => (string) $single,
            'plural' => (string) $plural,
            'context' => (string) $context,
            'number' => (int) $number,
            'translation' => (string) $translation,
        );
    }
    return $translation;
}
add_filter( 'ngettext_with_context', 'wu008_observer_record_ngettext_context', PHP_INT_MAX, 6 );

function wu008_observer_translation_file( $file, $domain, $locale ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $GLOBALS['wu008_observer']['translation_files'][] = array(
            'domain' => $domain,
            'locale' => (string) $locale,
            'file' => (string) $file,
        );
    }
    return $file;
}
add_filter( 'load_translation_file', 'wu008_observer_translation_file', PHP_INT_MAX, 3 );

function wu008_observer_js_before( $translations, $file, $handle, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $key = 'load|' . $domain . '|' . $handle . '|' . ( is_string( $file ) ? $file : var_export( $file, true ) );
        $GLOBALS['wu008_observer']['js_before'][ $key ] = $translations;
    }
    return $translations;
}
add_filter( 'load_script_translations', 'wu008_observer_js_before', 9, 4 );

function wu008_observer_js_after( $translations, $file, $handle, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $key = 'load|' . $domain . '|' . $handle . '|' . ( is_string( $file ) ? $file : var_export( $file, true ) );
        $before = $GLOBALS['wu008_observer']['js_before'][ $key ] ?? null;
        if ( $before !== $translations ) {
            $GLOBALS['wu008_observer']['js_mutations'][] = array(
                'hook' => 'load_script_translations',
                'domain' => $domain,
                'handle' => (string) $handle,
                'file' => $file,
                'before_type' => gettype( $before ),
                'after_type' => gettype( $translations ),
                'changed' => true,
            );
        }
    }
    return $translations;
}
add_filter( 'load_script_translations', 'wu008_observer_js_after', 11, 4 );

function wu008_observer_pre_before( $translations, $file, $handle, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $key = 'pre|' . $domain . '|' . $handle . '|' . ( is_string( $file ) ? $file : var_export( $file, true ) );
        $GLOBALS['wu008_observer']['js_before'][ $key ] = $translations;
    }
    return $translations;
}
add_filter( 'pre_load_script_translations', 'wu008_observer_pre_before', 9, 4 );

function wu008_observer_pre_after( $translations, $file, $handle, $domain ) {
    if ( wu008_observer_active() && in_array( $domain, array( 'gravityforms', 'gravityflow', 'gk-gravityview' ), true ) ) {
        $key = 'pre|' . $domain . '|' . $handle . '|' . ( is_string( $file ) ? $file : var_export( $file, true ) );
        $before = $GLOBALS['wu008_observer']['js_before'][ $key ] ?? null;
        if ( $before !== $translations ) {
            $GLOBALS['wu008_observer']['js_mutations'][] = array(
                'hook' => 'pre_load_script_translations',
                'domain' => $domain,
                'handle' => (string) $handle,
                'file' => $file,
                'before_type' => gettype( $before ),
                'after_type' => gettype( $translations ),
                'changed' => true,
            );
        }
    }
    return $translations;
}
add_filter( 'pre_load_script_translations', 'wu008_observer_pre_after', 11, 4 );

add_action( 'shutdown', function () {
    if ( ! wu008_observer_active() ) {
        return;
    }
    $artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
    if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
        return;
    }
    $surface_id = $GLOBALS['wu008_observer']['surface_id'];
    $name = preg_replace( '/[^A-Za-z0-9._-]+/', '_', $surface_id );
    $dir = rtrim( $artifact_dir, '/' ) . '/request-traces';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    $payload = array(
        'surface_id' => $surface_id,
        'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
        'locale' => function_exists( 'determine_locale' ) ? determine_locale() : null,
        'rtl' => function_exists( 'is_rtl' ) ? is_rtl() : null,
        'gettext' => array_values( $GLOBALS['wu008_observer']['gettext'] ),
        'translation_files' => array_values( $GLOBALS['wu008_observer']['translation_files'] ),
        'js_mutations' => array_values( $GLOBALS['wu008_observer']['js_mutations'] ),
    );
    file_put_contents( $dir . '/' . $name . '.json', wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
}, PHP_INT_MAX );
