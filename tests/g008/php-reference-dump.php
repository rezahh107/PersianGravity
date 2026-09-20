<?php
/**
 * G-008 development oracle bridge: dump production PHP converter results.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require dirname( __DIR__, 2 ) . '/includes/class-pgr-gregorian-jalali-converter.php';

if ( 3 !== $argc ) {
	fwrite( STDERR, "usage: php-reference-dump.php YYYY-MM-DD YYYY-MM-DD\n" );
	exit( 2 );
}

$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $argv[1], new DateTimeZone( 'UTC' ) );
$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $argv[2], new DateTimeZone( 'UTC' ) );
if ( false === $start || false === $end || $start > $end ) {
	fwrite( STDERR, "invalid range\n" );
	exit( 2 );
}

$rows = array();
for ( $date = $start; $date <= $end; $date = $date->modify( '+1 day' ) ) {
	$jalali = PGR_Gregorian_Jalali_Converter::to_jalali(
		(int) $date->format( 'Y' ),
		(int) $date->format( 'n' ),
		(int) $date->format( 'j' )
	);
	if ( null === $jalali ) {
		fwrite( STDERR, 'conversion failed at ' . $date->format( 'Y-m-d' ) . "\n" );
		exit( 1 );
	}
	$rows[] = array( $date->format( 'Y-m-d' ), $jalali['year'], $jalali['month'], $jalali['day'] );
}

echo json_encode( $rows, JSON_UNESCAPED_SLASHES ) . "\n";
