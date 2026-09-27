#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/upstream-radar.php';

final class PGR_Upstream_Radar_Source_Diagnostics {

	public static function collectFromBody( array $profile, string $body ): array {
		$parser = $profile['release_source']['parser'] ?? null;
		$result = array(
			'product_key'   => $profile['key'] ?? 'unknown',
			'parser'        => $parser,
			'source_sha256' => hash( 'sha256', $body ),
			'candidates'    => array(),
		);

		if ( $parser !== 'heading_text' ) {
			return $result;
		}

		$matched = preg_match_all( '~<h[1-6]\\b[^>]*>.*?</h[1-6]>~is', $body, $matches );
		if ( $matched === false ) {
			throw new RuntimeException( ( $profile['key'] ?? 'unknown' ) . ': diagnostic heading scan failed' );
		}

		foreach ( $matches[0] ?? array() as $heading ) {
			$text = html_entity_decode( strip_tags( (string) $heading ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = preg_replace( '/\\s+/u', ' ', trim( $text ) );
			$text = is_string( $text ) ? $text : '';
			if ( preg_match_all( '/\\b[0-9]+(?:\\.[0-9]+){2,3}\\b/', $text, $versions ) < 1 ) {
				continue;
			}
			$result['candidates'][] = array(
				'text'     => substr( $text, 0, 180 ),
				'versions' => array_values( array_unique( $versions[0] ) ),
			);
			if ( count( $result['candidates'] ) >= 12 ) {
				break;
			}
		}

		return $result;
	}

	public static function collectAll(): array {
		$results = array();
		foreach ( PGR_Upstream_Radar::loadProfiles() as $key => $profile ) {
			if ( ( $profile['release_source']['parser'] ?? null ) !== 'heading_text' ) {
				continue;
			}
			$body = PGR_Upstream_Radar::httpFetcher( $profile['release_source']['url'] );
			$results[ $key ] = self::collectFromBody( $profile, $body );
		}
		return array(
			'schema_version' => 1,
			'sources'        => $results,
		);
	}
}

if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) === __FILE__ ) {
	try {
		fwrite(
			STDOUT,
			json_encode(
				PGR_Upstream_Radar_Source_Diagnostics::collectAll(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			) . "\n"
		);
		exit( 0 );
	} catch ( Throwable $exception ) {
		fwrite( STDERR, 'upstream-radar-source-diagnostics: ' . $exception->getMessage() . "\n" );
		exit( 1 );
	}
}
