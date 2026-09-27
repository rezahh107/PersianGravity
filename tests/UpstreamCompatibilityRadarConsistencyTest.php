<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar-consistency.php';

final class UpstreamCompatibilityRadarConsistencyTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_reviewed_flow_floor_uses_highest_current_reviewed_version_from_same_first_party_source(): void {
		$version = PGR_Upstream_Radar_Consistency::highestReviewedVersionForSource(
			'gravityflow',
			'https://docs.gravityflow.io/changelog/',
			$this->root
		);

		$this->assertSame( '3.1.1', $version );
	}

	public function test_historical_unconfirmed_flow_signal_does_not_become_live_source_floor(): void {
		$historical = json_decode(
			(string) file_get_contents( $this->root . '/tools/compatibility/upstream-observations/gravityflow/3.1.1.1.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		$this->assertSame( 'MODEL_REVIEW_REQUIRED', $historical['review_state'] );
		$this->assertTrue( $historical['model_review_required'] );
		$this->assertSame( 'historical-detection-reference', $historical['official_sources'][0]['role'] );
		$this->assertSame(
			'3.1.1',
			PGR_Upstream_Radar_Consistency::highestReviewedVersionForSource(
				'gravityflow',
				'https://docs.gravityflow.io/changelog/',
				$this->root
			)
		);
	}

	public function test_live_rolling_source_cannot_regress_below_reviewed_first_party_observation(): void {
		$summary = $this->summaryWithVersions(
			array(
				'gravityforms' => '3.1.2',
				'gravityflow'  => '3.1.0',
				'gravityview'  => '3.5.0',
				'gravityperks' => '2.3.17',
			)
		);

		try {
			PGR_Upstream_Radar_Consistency::validateSummary( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
			$this->fail( 'A stale/ambiguous rolling source unexpectedly passed consistency validation.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'gravityflow', $exception->getMessage() );
			$this->assertStringContainsString( '3.1.0', $exception->getMessage() );
			$this->assertStringContainsString( '3.1.1', $exception->getMessage() );
			$this->assertStringContainsString( 'stale or ambiguous', $exception->getMessage() );
		}
	}

	public function test_equal_or_newer_live_rolling_source_passes_consistency_validation(): void {
		$summary = $this->summaryWithVersions(
			array(
				'gravityforms' => '3.1.2',
				'gravityflow'  => '3.1.1',
				'gravityview'  => '3.5.0',
				'gravityperks' => '2.3.17',
			)
		);

		PGR_Upstream_Radar_Consistency::validateSummary( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
		$this->addToAssertionCount( 1 );
	}

	public function test_terminal_source_is_not_treated_as_a_rolling_monotonic_feed(): void {
		$summary = $this->summaryWithVersions(
			array(
				'gravityforms' => '3.1.2',
				'gravityflow'  => '3.1.1',
				'gravityview'  => '3.5.0',
				'gravityperks' => '2.3.16',
			)
		);

		PGR_Upstream_Radar_Consistency::validateSummary( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
		$this->addToAssertionCount( 1 );
	}

	public function test_missing_rolling_profile_is_a_visible_failure(): void {
		$summary = $this->summaryWithVersions(
			array(
				'gravityforms' => '3.1.2',
				'gravityview'  => '3.5.0',
				'gravityperks' => '2.3.17',
			)
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'gravityflow: rolling profile is missing' );
		PGR_Upstream_Radar_Consistency::validateSummary( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
	}

	private function summaryWithVersions( array $versions ): array {
		$results = array();
		foreach ( $versions as $product_key => $version ) {
			$results[] = array(
				'product_key'             => $product_key,
				'latest_upstream_version' => $version,
			);
		}

		return array(
			'schema_version' => 1,
			'results'        => $results,
			'failures'       => array(),
		);
	}
}
