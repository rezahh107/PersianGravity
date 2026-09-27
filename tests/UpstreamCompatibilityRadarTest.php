<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar.php';

final class UpstreamCompatibilityRadarTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_one_shared_engine_owns_exactly_four_initial_profiles(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$keys     = array_keys( $profiles );
		sort( $keys );

		$this->assertSame( array( 'gravityflow', 'gravityforms', 'gravityperks', 'gravityview' ), $keys );
		$this->assertFileExists( $this->root . '/tools/compatibility/upstream-radar.php' );
	}

	public function test_profiles_resolve_existing_repository_authorities_without_version_literals(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$expected = array(
			'gravityforms' => array( '3.1.1.1', 'tools/i18n/admission/baseline.json' ),
			'gravityflow'  => array( '3.1.0', 'tools/compatibility/gravityflow-package.json' ),
			'gravityview'  => array( '3.3.4', 'tools/i18n/admission/baseline.json' ),
			'gravityperks' => array( '2.3.16', 'tools/i18n/admission/g007-gravity-perks.json' ),
		);

		foreach ( $expected as $key => $authority ) {
			$this->assertArrayNotHasKey( 'version', $profiles[ $key ]['baseline'], $key );
			$resolved = PGR_Upstream_Radar::resolveBaseline( $profiles[ $key ] );
			$this->assertSame( $authority[0], $resolved['version'], $key );
			$this->assertSame( $authority[1], $resolved['authority_path'], $key );
		}
	}

	public function test_version_comparison_is_deterministic_and_fail_closed(): void {
		$this->assertSame( 'NO_NEW_VERSION', PGR_Upstream_Radar::compareVersions( '3.1.2', '3.1.2' ) );
		$this->assertSame( 'NEW_VERSION_DETECTED', PGR_Upstream_Radar::compareVersions( '3.1.2', '3.1.1.1' ) );

		foreach ( array( array( '3.1.0', '3.1.1' ), array( 'latest', '3.1.1' ), array( '3.1', '3.1.1' ) ) as $case ) {
			try {
				PGR_Upstream_Radar::compareVersions( $case[0], $case[1] );
				$this->fail( 'Older/malformed version evidence unexpectedly produced an all-clear result.' );
			} catch ( RuntimeException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function test_each_profile_parser_accepts_its_governed_first_party_shape(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$fixtures = array(
			'gravityforms' => array( '<html><h3>3.1.2 | 2026-09-17</h3><p>release</p></html>', '3.1.2' ),
			'gravityflow'  => array( '<html><h4><a href="#3-1-1-1"><span>3.1.1.1</span></a> <a class="heading-anchor" href="#3-1-1-1">#</a></h4><p>release</p></html>', '3.1.1.1' ),
			'gravityview'  => array( '<html><h2>3.5.0 on September 24, 2026</h2><p>release</p></html>', '3.5.0' ),
			'gravityperks' => array( '<html><a href="/gravity-perks/">Gravity Perks</a> (v2.3.17)<p>release</p></html>', '2.3.17' ),
		);

		foreach ( $fixtures as $key => $fixture ) {
			$parsed = PGR_Upstream_Radar::parseLatestVersion( $profiles[ $key ], $fixture[0] );
			$this->assertSame( $fixture[1], $parsed['version'], $key );
		}
	}

	public function test_source_structure_drift_is_visible(): void {
		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::parseLatestVersion(
			PGR_Upstream_Radar::loadProfiles()['gravityforms'],
			'<html><p>changelog layout changed completely</p></html>'
		);
	}

	public function test_source_unavailability_is_visible(): void {
		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::scanProfile(
			PGR_Upstream_Radar::loadProfiles()['gravityforms'],
			static function ( string $url ): string {
				unset( $url );
				throw new RuntimeException( 'network unavailable' );
			},
			$this->root,
			'2026-09-27T18:16:39Z'
		);
	}

	public function test_automation_stays_below_semantic_ceiling(): void {
		$result = PGR_Upstream_Radar::scanProfile(
			PGR_Upstream_Radar::loadProfiles()['gravityforms'],
			static fn ( string $url ): string => '<html><h3>3.1.2 | 2026-09-17</h3><p>Added gform_example_hook filter.</p></html>',
			$this->root,
			'2026-09-27T18:16:39Z'
		);

		$this->assertSame( 'NEW_VERSION_DETECTED', $result['result'] );
		$candidate = $result['observation_candidate'];
		$this->assertSame( 'DETECTED_ONLY', $candidate['evidence_state'] );
		$this->assertSame( 'NOT_SUPPLIED_FOR_DETECTED_VERSION', $candidate['package_state'] );
		$this->assertSame( 'MODEL_REVIEW_REQUIRED', $candidate['review_state'] );
		$this->assertContains( 'PACKAGE_REQUIRED_FOR_PROOF', $candidate['classifications'] );
		$this->assertContains( 'gform_example_hook', $candidate['capability_observations'][0]['documented_identifiers'] );
	}

	public function test_docs_only_observations_cannot_claim_qualified_or_admitted(): void {
		$observation = $this->readObservation( 'gravityforms/3.1.2.json' );
		foreach ( array( 'QUALIFIED', 'ADMITTED' ) as $invalid ) {
			$mutated                   = $observation;
			$mutated['evidence_state'] = $invalid;
			try {
				PGR_Upstream_Radar::validateObservation( $mutated );
				$this->fail( 'Forbidden docs-only state unexpectedly passed: ' . $invalid );
			} catch ( RuntimeException $exception ) {
				$this->assertStringContainsString( 'DETECTED_ONLY', $exception->getMessage() );
			}
		}
	}

	public function test_documented_change_and_no_relevant_change_are_mutually_exclusive(): void {
		$observation                      = $this->readObservation( 'gravityforms/3.1.2.json' );
		$observation['classifications'][] = 'DOCUMENTED_CONTRACT_CHANGE';
		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::validateObservation( $observation );
	}

	public function test_better_seam_requires_explicit_supporting_official_evidence(): void {
		$observation                      = $this->readObservation( 'gravityflow/3.1.1.1.json' );
		$observation['classifications'][] = 'POTENTIAL_BETTER_SEAM_FOUND';
		$observation['capability_observations'][] = array(
			'capability'     => 'gravityflow.example',
			'classification' => 'POTENTIAL_BETTER_SEAM_FOUND',
			'summary'        => 'Candidate only.',
		);

		try {
			PGR_Upstream_Radar::validateObservation( $observation );
			$this->fail( 'Unsupported better-seam classification unexpectedly passed.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'supporting_official_evidence', $exception->getMessage() );
		}

		$index = array_key_last( $observation['capability_observations'] );
		$observation['capability_observations'][ $index ]['supporting_official_evidence'] = array( 'gravityflow_example_filter' );
		PGR_Upstream_Radar::validateObservation( $observation );
		$this->addToAssertionCount( 1 );
	}

	public function test_persistence_is_product_scoped_and_deduplicated(): void {
		$tmp = $this->makeTemporaryRoot();
		try {
			$candidate = $this->automationCandidate( 'gravityforms', '3.1.2' );
			$this->assertSame( 'CREATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );
			$this->assertSame( 'AUTOMATED_DEDUPLICATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );
			$this->assertFileExists( $tmp . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json' );
		} finally {
			$this->removeDirectory( $tmp );
		}
	}

	public function test_automation_does_not_overwrite_reviewed_observation(): void {
		$tmp = $this->makeTemporaryRoot();
		try {
			$candidate = $this->automationCandidate( 'gravityforms', '3.1.2' );
			PGR_Upstream_Radar::persistCandidate( $candidate, $tmp );
			$path = $tmp . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json';
			$reviewed = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
			$reviewed['review_state']          = 'MODEL_REVIEWED';
			$reviewed['model_review_required'] = false;
			$reviewed['automation_generated']  = false;
			foreach ( $reviewed['official_sources'] as &$source ) {
				unset( $source['sha256'] );
			}
			unset( $source );
			file_put_contents( $path, json_encode( $reviewed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
			$candidate['checked_at'] = '2026-09-28T18:16:39Z';
			$this->assertSame( 'REVIEWED_DEDUPLICATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );
			$after = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
			$this->assertSame( '2026-09-27T18:16:39Z', $after['checked_at'] );
		} finally {
			$this->removeDirectory( $tmp );
		}
	}

	public function test_persistence_does_not_mutate_package_authority(): void {
		$tmp = $this->makeTemporaryRoot();
		try {
			@mkdir( $tmp . '/tools/compatibility', 0777, true );
			$authority = $tmp . '/tools/compatibility/gravityflow-package.json';
			file_put_contents( $authority, "{\"sentinel\":true}\n" );
			$before = hash_file( 'sha256', $authority );
			PGR_Upstream_Radar::persistCandidate( $this->automationCandidate( 'gravityflow', '3.1.1.1' ), $tmp );
			$this->assertSame( $before, hash_file( 'sha256', $authority ) );
		} finally {
			$this->removeDirectory( $tmp );
		}
	}

	public function test_child_addons_are_extensible_but_not_silently_enrolled(): void {
		$document = json_decode( (string) file_get_contents( PGR_Upstream_Radar::defaultProfilesPath() ), true, 512, JSON_THROW_ON_ERROR );
		$keys     = array_column( $document['products'], 'key' );
		$this->assertNotContains( 'gp-file-upload-pro', $keys );
		$this->assertNotContains( 'gp-advanced-select', $keys );

		$future = $document['products'][0];
		$future['key']                   = 'gp-file-upload-pro';
		$future['name']                  = 'GP File Upload Pro';
		$future['baseline']['product']   = 'gp-file-upload-pro';
		$future['capabilities']          = array( 'localization.gp-file-upload-pro' );
		$future['observation_namespace'] = 'gp-file-upload-pro';
		$document['products'][]          = $future;
		$path = tempnam( sys_get_temp_dir(), 'pgr-radar-profiles-' );
		$this->assertNotFalse( $path );
		file_put_contents( $path, json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		try {
			$this->assertArrayHasKey( 'gp-file-upload-pro', PGR_Upstream_Radar::loadProfiles( $path ) );
		} finally {
			unlink( $path );
		}
	}

	public function test_arbitrary_site_plugins_are_not_discovered(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$this->assertCount( 4, $profiles );
		$this->assertArrayNotHasKey( 'woocommerce', $profiles );
		$this->assertArrayNotHasKey( 'akismet', $profiles );
	}

	public function test_workflow_has_weekly_manual_and_exact_pr_head_live_execution(): void {
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$this->assertStringContainsString( "cron: '23 6 * * 1'", $workflow );
		$this->assertStringContainsString( 'workflow_dispatch:', $workflow );
		$this->assertStringContainsString( 'pull_request:', $workflow );
		$this->assertStringContainsString( 'github.event.pull_request.head.sha', $workflow );
		$this->assertStringContainsString( 'php tools/compatibility/upstream-radar.php scan', $workflow );
	}

	public function test_reviewable_persistence_has_no_direct_main_write_or_auto_merge(): void {
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$guard    = (string) file_get_contents( $this->root . '/.github/scripts/upstream-radar-observation-boundary.sh' );
		$this->assertStringContainsString( 'automation/upstream-radar-observations', $workflow );
		$this->assertStringContainsString( 'gh pr list --head "$RADAR_BRANCH"', $workflow );
		$this->assertStringContainsString( 'gh pr create', $workflow );
		$this->assertStringContainsString( '$RUNNER_TEMP/radar-evidence', $workflow );
		$this->assertStringContainsString( '^tools/compatibility/upstream-observations/', $guard );
		$this->assertStringContainsString( 'committed origin/main HEAD', $workflow );
		$this->assertStringContainsString( 'working-tree', $workflow );
		$this->assertStringNotContainsString( 'git push origin main', $workflow );
		$this->assertStringNotContainsString( 'HEAD:main', $workflow );
		$this->assertStringNotContainsString( 'gh pr merge', $workflow );
		$this->assertStringNotContainsString( '--auto', $workflow );
	}

	public function test_radar_has_no_licensed_package_auto_acquisition_path(): void {
		$source   = strtolower( (string) file_get_contents( $this->root . '/tools/compatibility/upstream-radar.php' ) );
		$workflow = strtolower( (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' ) );
		$combined = $source . "\n" . $workflow;
		$this->assertStringNotContainsString( 'drive.google.com', $combined );
		$this->assertStringNotContainsString( 'google_drive_file_id', $combined );
		$this->assertStringNotContainsString( 'wget ', $combined );
		$this->assertStringNotContainsString( 'curl ', $combined );
	}

	public function test_production_runtime_cannot_consume_observations_as_activation_authority(): void {
		$paths = array( $this->root . '/persian-gravityforms.php', $this->root . '/uninstall.php' );
		foreach ( array( 'includes', 'admin' ) as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$paths[] = $file->getPathname();
				}
		}
		foreach ( $paths as $path ) {
			$content = (string) file_get_contents( $path );
			$this->assertStringNotContainsString( 'upstream-observations', $content, $path );
			$this->assertStringNotContainsString( 'upstream-radar.php', $content, $path );
		}
	}

	public function test_all_current_reviewed_observations_validate(): void {
		$this->assertSame( 5, PGR_Upstream_Radar::validateObservationDirectory() );
		$this->assertSame( '3.1.1', $this->readObservation( 'gravityflow/3.1.1.json' )['detected_upstream_version'] );
		$this->assertSame( '3.1.1.1', $this->readObservation( 'gravityflow/3.1.1.1.json' )['detected_upstream_version'] );
	}

	private function readObservation( string $relative ): array {
		return json_decode(
			(string) file_get_contents( $this->root . '/tools/compatibility/upstream-observations/' . $relative ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
	}

	private function automationCandidate( string $product, string $version ): array {
		return array(
			'schema_version' => 1,
			'product_key' => $product,
			'product_name' => ucfirst( $product ),
			'detected_upstream_version' => $version,
			'project_baseline' => array(
				'version' => '3.1.0',
				'authority_kind' => 'TEST_AUTHORITY',
				'authority_path' => 'tools/test-authority.json',
				'authority_product' => $product,
			),
			'evidence_state' => 'DETECTED_ONLY',
			'package_state' => 'NOT_SUPPLIED_FOR_DETECTED_VERSION',
			'checked_at' => '2026-09-27T18:16:39Z',
			'official_sources' => array(
				array(
					'url' => 'https://docs.gravityflow.io/changelog/',
					'role' => 'stable-release-and-changelog',
					'sha256' => str_repeat( 'a', 64 ),
				),
			),
			'classifications' => array( 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' ),
			'capability_observations' => array(
				array(
					'capability' => 'profile.' . $product,
					'classification' => 'MODEL_REVIEW_REQUIRED',
					'summary' => 'Needs semantic review.',
					'documented_identifiers' => array(),
				),
			),
			'model_review_required' => true,
			'package_required_for_proof' => true,
			'review_state' => 'MODEL_REVIEW_REQUIRED',
			'automation_generated' => true,
		);
	}

	private function makeTemporaryRoot(): string {
		$path = sys_get_temp_dir() . '/pgr-upstream-radar-' . bin2hex( random_bytes( 6 ) );
		mkdir( $path, 0777, true );
		return $path;
	}

	private function removeDirectory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $directory );
	}
}
