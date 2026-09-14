<?php
/**
 * Focused request observer for the Structured Scanner Form Editor remediation.
 *
 * Evidence-only: records Gravity Forms gettext/provider loading without changing
 * translations or product behavior.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['pgr_scanner_remediation_observer'] = array(
	'gettext'           => array(),
	'translation_files' => array(),
);

function pgr_scanner_remediation_observer_active() {
	return isset( $_GET['pgr_scanner_remediation'] ) && '1' === (string) $_GET['pgr_scanner_remediation']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only evidence routing.
}

function pgr_scanner_remediation_record_gettext( $translation, $text, $domain ) {
	if ( pgr_scanner_remediation_observer_active() && 'gravityforms' === $domain ) {
		$GLOBALS['pgr_scanner_remediation_observer']['gettext'][] = array(
			'msgid'       => (string) $text,
			'translation' => (string) $translation,
		);
	}
	return $translation;
}
add_filter( 'gettext', 'pgr_scanner_remediation_record_gettext', PHP_INT_MAX, 3 );

function pgr_scanner_remediation_record_translation_file( $file, $domain, $locale ) {
	if ( pgr_scanner_remediation_observer_active() && 'gravityforms' === $domain ) {
		$GLOBALS['pgr_scanner_remediation_observer']['translation_files'][] = array(
			'domain' => (string) $domain,
			'locale' => (string) $locale,
			'file'   => (string) $file,
		);
	}
	return $file;
}
add_filter( 'load_translation_file', 'pgr_scanner_remediation_record_translation_file', PHP_INT_MAX, 3 );

add_action(
	'shutdown',
	static function () {
		if ( ! pgr_scanner_remediation_observer_active() ) {
			return;
		}

		$artifact_dir = getenv( 'PGR_SCANNER_ARTIFACT_DIR' );
		if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
			return;
		}

		wp_mkdir_p( $artifact_dir );
		$payload = array(
			'request_uri'       => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
			'locale'            => function_exists( 'determine_locale' ) ? determine_locale() : null,
			'rtl'               => function_exists( 'is_rtl' ) ? is_rtl() : null,
			'gettext'           => array_values( $GLOBALS['pgr_scanner_remediation_observer']['gettext'] ),
			'translation_files' => array_values( $GLOBALS['pgr_scanner_remediation_observer']['translation_files'] ),
		);
		file_put_contents(
			rtrim( $artifact_dir, '/' ) . '/provider-trace.json',
			wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
		);
	},
	PHP_INT_MAX
);
