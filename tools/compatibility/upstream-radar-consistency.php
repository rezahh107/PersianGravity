#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/upstream-radar.php';

final class PGR_Upstream_Radar_Consistency {

	public static function validateSummary( array $summary, array $profiles, ?string $root = null ): void {
		$root ??= PGR_Upstream_Radar::repositoryRoot();

		if ( ( $summary['schema_version'] ?? null ) !== 1 ) {
			throw new RuntimeException( 'upstream radar summary schema_version is invalid' );
		}
		if ( ! isset( $summary['results'] ) || ! is_array( $summary['results'] ) || ! array_is_list( $summary['results'] ) ) {
			throw new RuntimeException( 'upstream radar summary results must be a list' );
		}
		if ( ! empty( $summary['failures'] ) ) {
			throw new RuntimeException( 'upstream radar summary already contains source failures' );
		}

		$results = array();
		foreach ( $summary['results'] as $result ) {
			if ( ! is_array( $result ) || ! isset( $result['product_key'], $result['latest_upstream_version'] ) ) {
				throw new RuntimeException( 'upstream radar summary contains an invalid result' );
			}
			$product_key = (string) $result['product_key'];
			if ( isset( $results[ $product_key ] ) ) {
				throw new RuntimeException( 'upstream radar summary contains a duplicate product result: ' . $product_key );
			}
			$results[ $product_key ] = $result;
		}

		foreach ( $profiles as $product_key => $profile ) {
			if ( ( $profile['release_source']['mode'] ?? 'rolling' ) !== 'rolling' ) {
				continue;
			}
			if ( ! isset( $results[ $product_key ] ) ) {
				throw new RuntimeException( $product_key . ': rolling profile is missing from live radar summary' );
			}

			$release_url = (string) $profile['release_source']['url'];
			$latest      = (string) $results[ $product_key ]['latest_upstream_version'];
			$reviewed_floor = self::highestReviewedVersionForSource( $product_key, $release_url, $root );
			if ( $reviewed_floor !== null && version_compare( $latest, $reviewed_floor, '<' ) ) {
				throw new RuntimeException(
					sprintf(
						'%s: live first-party source reported %s below reviewed observation %s from the same source; source is stale or ambiguous',
						$product_key,
						$latest,
						$reviewed_floor
					)
				);
			}

			if ( self::hasUnresolvedMatchingObservation( $product_key, $latest, $release_url, $root ) ) {
				throw new RuntimeException(
					sprintf(
						'%s: live first-party source now matches unresolved historical observation %s; MODEL_REVIEW_REQUIRED before persistence can be treated as deduplicated',
						$product_key,
						$latest
					)
				);
			}
		}
	}

	public static function highestReviewedVersionForSource( string $product_key, string $release_url, ?string $root = null ): ?string {
		$highest = null;
		foreach ( self::observationsForProduct( $product_key, $root ) as $observation ) {
			if ( ( $observation['review_state'] ?? null ) !== 'MODEL_REVIEWED' || ! empty( $observation['model_review_required'] ) ) {
				continue;
			}
			if ( ( $observation['upstream_release_mode'] ?? 'rolling' ) !== 'rolling' ) {
				continue;
			}
			if ( ! self::observationUsesCurrentReleaseSource( $observation, $release_url ) ) {
				continue;
			}

			$version = (string) $observation['detected_upstream_version'];
			if ( $highest === null || version_compare( $version, $highest, '>' ) ) {
				$highest = $version;
			}
		}

		return $highest;
	}

	public static function hasUnresolvedMatchingObservation( string $product_key, string $version, string $release_url, ?string $root = null ): bool {
		foreach ( self::observationsForProduct( $product_key, $root ) as $observation ) {
			if ( (string) ( $observation['detected_upstream_version'] ?? '' ) !== $version ) {
				continue;
			}
			if ( ( $observation['review_state'] ?? null ) !== 'MODEL_REVIEW_REQUIRED' || empty( $observation['model_review_required'] ) ) {
				continue;
			}
			if ( ( $observation['upstream_release_mode'] ?? 'rolling' ) !== 'rolling' ) {
				continue;
			}

			foreach ( $observation['official_sources'] as $source ) {
				if ( ( $source['url'] ?? null ) === $release_url ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function observationsForProduct( string $product_key, ?string $root = null ): array {
		$root ??= PGR_Upstream_Radar::repositoryRoot();
		if ( preg_match( '/^[a-z][a-z0-9-]*$/', $product_key ) !== 1 ) {
			throw new RuntimeException( 'consistency product key is invalid' );
		}

		$directory = rtrim( $root, '/' ) . '/tools/compatibility/upstream-observations/' . $product_key;
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$observations = array();
		foreach ( glob( $directory . '/*.json' ) ?: array() as $path ) {
			$content = @file_get_contents( $path );
			if ( $content === false ) {
				throw new RuntimeException( 'cannot read observation: ' . $path );
			}
			try {
				$observation = json_decode( $content, true, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $exception ) {
				throw new RuntimeException( 'observation is not valid JSON: ' . $path, 0, $exception );
			}
			if ( ! is_array( $observation ) || array_is_list( $observation ) ) {
				throw new RuntimeException( 'observation root must be an object: ' . $path );
			}
			PGR_Upstream_Radar::validateObservation( $observation );
			$observations[] = $observation;
		}

		return $observations;
	}

	private static function observationUsesCurrentReleaseSource( array $observation, string $release_url ): bool {
		foreach ( $observation['official_sources'] as $source ) {
			if ( ( $source['url'] ?? null ) === $release_url && ( $source['role'] ?? null ) === 'stable-release-and-changelog' ) {
				return true;
			}
		}
		return false;
	}
}

function pgrUpstreamRadarConsistencyMain( array $argv ): int {
	$path = $argv[1] ?? '';
	if ( ! is_string( $path ) || $path === '' ) {
		fwrite( STDERR, "upstream-radar-consistency: summary path is required\n" );
		return 1;
	}

	try {
		$content = @file_get_contents( $path );
		if ( $content === false ) {
			throw new RuntimeException( 'cannot read upstream radar summary: ' . $path );
		}
		$summary = json_decode( $content, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $summary ) || array_is_list( $summary ) ) {
			throw new RuntimeException( 'upstream radar summary root must be an object' );
		}
		PGR_Upstream_Radar_Consistency::validateSummary( $summary, PGR_Upstream_Radar::loadProfiles() );
		fwrite( STDOUT, "Upstream radar live-source consistency OK\n" );
		return 0;
	} catch ( Throwable $exception ) {
		fwrite( STDERR, 'upstream-radar-consistency: ' . $exception->getMessage() . "\n" );
		return 1;
	}
}

if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) === __FILE__ ) {
	exit( pgrUpstreamRadarConsistencyMain( $argv ) );
}
