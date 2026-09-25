<?php
/**
 * Reproduce the exact literal-gettext/constant-concatenation source census used
 * for GravityView 3.3.4 admission. Vendor source remains read-only input.
 *
 * Usage: php tools/i18n/extract-gravityview-source.php /path/to/gravityview [output.json]
 */

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/\\' ) : '';
if ( '' === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Usage: php tools/i18n/extract-gravityview-source.php /path/to/gravityview [output.json]\n" );
	exit( 2 );
}

$domains = array( 'gk-gravityview', 'gk-query-filters', 'action-scheduler' );
$functions = array(
	'__' => array( 'msgid' => 0, 'domain' => 1 ), '_e' => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_html__' => array( 'msgid' => 0, 'domain' => 1 ), 'esc_html_e' => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_attr__' => array( 'msgid' => 0, 'domain' => 1 ), 'esc_attr_e' => array( 'msgid' => 0, 'domain' => 1 ),
	'_x' => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ), '_ex' => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ),
	'esc_html_x' => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ), 'esc_attr_x' => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ),
	'_n' => array( 'msgid' => 0, 'plural' => 1, 'domain' => 3 ), '_nx' => array( 'msgid' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ),
	'_n_noop' => array( 'msgid' => 0, 'plural' => 1, 'domain' => 2 ), '_nx_noop' => array( 'msgid' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3 ),
);

$evaluate = static function ( array $tokens ) {
	$parts = array(); $expect_string = true;
	foreach ( $tokens as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
		if ( $expect_string ) {
			if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) { return null; }
			try { $value = eval( 'return ' . $token[1] . ';' ); } catch ( Throwable $exception ) { return null; } // phpcs:ignore Squiz.PHP.Eval.Discouraged -- exact PHP string-literal semantics on inspected source input.
			if ( ! is_string( $value ) ) { return null; }
			$parts[] = $value; $expect_string = false;
		} else {
			if ( '.' !== $token ) { return null; }
			$expect_string = true;
		}
	}
	return $expect_string || empty( $parts ) ? null : implode( '', $parts );
};

$parse_arguments = static function ( array $tokens, int $open_index ) {
	$arguments = array(); $current = array(); $depth = 0; $count = count( $tokens );
	for ( $index = $open_index + 1; $index < $count; $index++ ) {
		$token = $tokens[ $index ];
		if ( in_array( $token, array( '(', '[', '{' ), true ) ) { $depth++; $current[] = $token; continue; }
		if ( ')' === $token ) {
			if ( 0 === $depth ) { $arguments[] = $current; return array( $arguments, $index ); }
			$depth--; $current[] = $token; continue;
		}
		if ( in_array( $token, array( ']', '}' ), true ) ) { $depth--; $current[] = $token; continue; }
		if ( ',' === $token && 0 === $depth ) { $arguments[] = $current; $current = array(); continue; }
		$current[] = $token;
	}
	return array( null, $open_index );
};

$entries = array(); $dynamic = array();
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) { continue; }
	$path = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$tokens = token_get_all( file_get_contents( $file->getPathname() ) ); $count = count( $tokens );
	for ( $index = 0; $index < $count; $index++ ) {
		$token = $tokens[ $index ]; if ( ! is_array( $token ) || T_STRING !== $token[0] ) { continue; }
		$name = strtolower( $token[1] ); if ( ! isset( $functions[ $name ] ) ) { continue; }
		$open = $index + 1;
		while ( $open < $count && is_array( $tokens[ $open ] ) && in_array( $tokens[ $open ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) { $open++; }
		if ( $open >= $count || '(' !== $tokens[ $open ] ) { continue; }
		list( $arguments, $end ) = $parse_arguments( $tokens, $open ); if ( null === $arguments ) { continue; }
		$values = array(); foreach ( $arguments as $argument_index => $argument_tokens ) { $values[ $argument_index ] = $evaluate( $argument_tokens ); }
		$spec = $functions[ $name ]; $domain = $values[ $spec['domain'] ] ?? null;
		if ( ! in_array( $domain, $domains, true ) ) { $index = $end; continue; }
		$msgid = $values[ $spec['msgid'] ] ?? null; $plural = isset( $spec['plural'] ) ? ( $values[ $spec['plural'] ] ?? null ) : null; $context = isset( $spec['context'] ) ? ( $values[ $spec['context'] ] ?? null ) : null;
		if ( ! is_string( $msgid ) || '' === $msgid || ( isset( $spec['plural'] ) && ! is_string( $plural ) ) || ( isset( $spec['context'] ) && ! is_string( $context ) ) ) { $dynamic[] = array( 'domain' => $domain, 'function' => $name, 'reference' => $path . ':' . $token[2] ); $index = $end; continue; }
		$identity = hash( 'sha256', (string) $context . "\x1f" . $msgid . "\x1f" . (string) $plural );
		if ( ! isset( $entries[ $domain ][ $identity ] ) ) { $entries[ $domain ][ $identity ] = array( 'msgctxt' => $context, 'msgid' => $msgid, 'msgid_plural' => $plural, 'references' => array() ); }
		$entries[ $domain ][ $identity ]['references'][] = $path . ':' . $token[2]; $index = $end;
	}
}

$result = array( 'domains' => array(), 'dynamic_calls' => $dynamic );
foreach ( $domains as $domain ) {
	$set = $entries[ $domain ] ?? array(); ksort( $set, SORT_STRING ); $identity_rows = array_keys( $set ); $reference_rows = array(); $context_count = 0; $plural_count = 0;
	foreach ( $set as $identity => &$entry ) {
		$entry['references'] = array_values( array_unique( $entry['references'] ) ); sort( $entry['references'], SORT_STRING );
		$context_count += null !== $entry['msgctxt'] && '' !== $entry['msgctxt'] ? 1 : 0; $plural_count += null !== $entry['msgid_plural'] ? 1 : 0;
		foreach ( $entry['references'] as $reference ) { $reference_rows[] = $identity . "\x1f" . $reference; }
	}
	unset( $entry ); sort( $reference_rows, SORT_STRING );
	$result['domains'][ $domain ] = array(
		'domain' => $domain, 'canonical_message_count' => count( $identity_rows ), 'context_message_count' => $context_count, 'plural_message_count' => $plural_count,
		'source_reference_count' => count( $reference_rows ),
		'canonical_keyset_sha256' => hash( 'sha256', implode( "\n", $identity_rows ) ),
		'source_reference_index_sha256' => hash( 'sha256', implode( "\n", $reference_rows ) ),
		'entries' => array_values( array_map( static function ( $identity, $entry ) { return array( 'identity' => $identity ) + $entry; }, array_keys( $set ), array_values( $set ) ) ),
	);
}
$json = json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
if ( isset( $argv[2] ) && '' !== $argv[2] ) { file_put_contents( $argv[2], $json ); } else { echo $json; }
