<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar.php';

final class UpstreamCompatibilityRadarSourceShapeTest extends TestCase {

	public function test_gravity_flow_marked_up_heading_does_not_hide_latest_four_segment_release(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityflow'];
		$html    = '<html>'
			. '<h3><a href="#3-1-1-1"><span>3.1.1.1</span></a></h3><p>current patch release</p>'
			. '<h3>3.1.1 | 2026-08-25</h3><p>older release</p>'
			. '</html>';

		$parsed = PGR_Upstream_Radar::parseLatestVersion( $profile, $html );

		$this->assertSame( '3.1.1.1', $parsed['version'] );
		$this->assertStringContainsString( 'current patch release', $parsed['release_block'] );
		$this->assertStringNotContainsString( 'older release', $parsed['release_block'] );
	}
}
