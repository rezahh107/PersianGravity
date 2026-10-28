<?php
/**
 * Disposable WU008-only server receipt; installed into mu-plugins by CI.
 * No effect on packaged PersianGravity or licensed vendor sources.
 */
defined( 'ABSPATH' ) || exit;
add_shortcode( 'g011_destination_receipt', static function () {
	$raw   = (string) ( $_SERVER['QUERY_STRING'] ?? '' );
	$pairs = array();
	foreach ( explode( '&', $raw ) as $segment ) {
		if ( '' === $segment ) {
			continue;
		}
		$pieces  = explode( '=', $segment, 2 );
		$pairs[] = array(
			'key'   => urldecode( $pieces[0] ),
			'value' => urldecode( $pieces[1] ?? '' ),
		);
	}
	$receipt = array(
		'marker'       => 'G011_SERVER_RECEIPT',
		'post_id'      => (int) get_the_ID(),
		'http_method'  => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ),
		'raw_query'    => $raw,
		'pairs'        => $pairs,
		// The last duplicate wins in PHP's native request parsing; pairs retain all.
		'php_repeat'   => $_GET['repeat'] ?? null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	);
	return '<pre id="g011-destination-receipt">' . esc_html( wp_json_encode( $receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
} );
