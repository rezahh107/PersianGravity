<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar.php';

final class UpstreamCompatibilityRadarSourceShapeTest extends TestCase {

	public function test_gravity_flow_marked_up_heading_suffix_does_not_hide_latest_four_segment_release(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityflow'];
		$html    = '<html>'
			. '<h4><a href="#3-1-1-1"><span>3.1.1.1</span></a> <a class="heading-anchor" href="#3-1-1-1">#</a></h4><p>current patch release</p>'
			. '<h3>3.1.1 | 2026-08-25</h3><p>older release</p>'
			. '</html>';

		$parsed = PGR_Upstream_Radar::parseLatestVersion( $profile, $html );

		$this->assertSame( '3.1.1.1', $parsed['version'] );
		$this->assertStringContainsString( 'current patch release', $parsed['release_block'] );
		$this->assertStringNotContainsString( 'older release', $parsed['release_block'] );
	}

	public function test_gravity_perks_parent_release_is_explicitly_terminal_with_first_party_lifecycle_evidence(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityperks'];

		$this->assertSame( 'terminal', $profile['release_source']['mode'] );
		$this->assertSame( 'DISCONTINUED_REPLACED', $profile['lifecycle']['state'] );
		$this->assertSame( 'Spellbook', $profile['lifecycle']['replacement_product'] );
		$this->assertContains( $profile['lifecycle']['official_evidence_url'], $profile['documentation_sources'] );
	}

	public function test_redirect_resolution_keeps_every_hop_inside_approved_first_party_hosts(): void {
		$this->assertSame(
			'https://docs.gravityflow.io/changelog/latest/',
			PGR_Upstream_Radar::resolveApprovedRedirect(
				'https://docs.gravityflow.io/changelog/',
				'/changelog/latest/'
			)
		);

		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::resolveApprovedRedirect(
			'https://docs.gravityflow.io/changelog/',
			'https://example.com/redirected-copy'
		);
	}

	public function test_reviewed_source_fingerprint_change_is_a_visible_failure(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'MODEL_REVIEW_REQUIRED' );
		PGR_Upstream_Radar::assertPersistenceOutcome(
			'gravityforms',
			'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED'
		);
	}

	public function test_benign_persistence_outcomes_remain_non_failures(): void {
		PGR_Upstream_Radar::assertPersistenceOutcome( 'gravityforms', 'REVIEWED_DEDUPLICATED' );
		PGR_Upstream_Radar::assertPersistenceOutcome( 'gravityflow', 'CREATED' );
		$this->addToAssertionCount( 2 );
	}
}
