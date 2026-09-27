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
		$this->assertSame( array(), glob( $this->root . '/tools/compatibility/upstream-radar-*.php' ) ?: array() );
	}

	public function test_profiles_resolve_baselines_from_existing_repository_authorities(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$expected = array(
			'gravityforms' => array( '3.1.1.1', 'tools/i18n/admission/baseline.json' ),
			'gravityflow'  => array( '3.1.0', 'tools/compatibility/gravityflow-package.json' ),
			'gravityview'  => array( '3.3.4', 'tools/i18n/admission/baseline.json' ),
			'gravityperks' => array( '2.3.16', 'tools/i18n/admission/g007-gravity-perks.json' ),
		);

		foreach ( $expected as $key => $authority ) {
			$this->assertArrayNotHasKey( 'version', $profiles[ $key ]['baseline'], $key . ' must not duplicate the operational baseline version.' );
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
				$this->fail( 'Ambiguous/older version evidence unexpectedly produced a result.' );
			} catch ( RuntimeException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function test_each_profile_parser_accepts_its_governed_release_shape(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$fixtures = array(
			'gravityforms' => array( '<html><h3>3.1.2 | 2026-09-17</h3><p>release</p></html>', '3.1.2' ),
			'gravityflow'  => array( '<html><h3>3.1.1.1</h3><p>release</p></html>', '3.1.1.1' ),
			'gravityview'  => array( '<html><h2>3.5.0 on September 24, 2026</h2><p>release</p></html>', '3.5.0' ),
			'gravityperks' => array( '<html><h3>Gravity Perks</h3><p>Gravity Perks (v2.3.17)</p></html>', '2.3.17' ),
		);

		foreach ( $fixtures as $key => $fixture ) {
			$parsed = PGR_Upstream_Radar::parseLatestVersion( $profiles[ $key ], $fixture[0] );
			$this->assertSame( $fixture[1], $parsed['version'], $key );
		}
	}

	public function test_structural_drift_and_transport_failure_remain_visible(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityforms'];

		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::parseLatestVersion( $profile, '<html><p>changelog layout changed completely</p></html>' );
	}

	public function test_scan_profile_propagates_source_unavailability(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityforms'];
		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::scanProfile(
			$profile,
			static function (): string {
				throw new RuntimeException( 'network unavailable' );
			},
			$this->root,
			'2026-09-27T17:55:00Z'
		);
	}

	public function test_new_version_automation_candidate_stays_below_semantic_ceiling(): void {
		$profile = PGR_Upstream_Radar::loadProfiles()['gravityforms'];
		$result  = PGR_Upstream_Radar::scanProfile(
			$profile,
			static fn (): string => '<html><h3>3.1.2 | 2026-09-17</h3><p>Added gform_example_hook filter.</p></html>',
			$this->root,
			'2026-09-27T17:55:00Z'
		);

		$this->assertSame( 'NEW_VERSION_DETECTED', $result['result'] );
		$candidate = $result['observation_candidate'];
		$this->assertIsArray( $candidate );
		$this->assertSame( 'DETECTED_ONLY', $candidate['evidence_state'] );
		$this->assertSame( 'NOT_SUPPLIED_FOR_DETECTED_VERSION', $candidate['package_state'] );
		$this->assertSame( 'MODEL_REVIEW_REQUIRED', $candidate['review_state'] );
		$this->assertTrue( $candidate['model_review_required'] );
		$this->assertContains( 'PACKAGE_REQUIRED_FOR_PROOF', $candidate['classifications'] );
		$this->assertContains( 'gform_example_hook', $candidate['capability_observations'][0]['documented_identifiers'] );
	}

	public function test_observation_schema_cannot_promote_docs_only_evidence_to_qualified_or_admitted(): void {
		$path        = $this->root . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json';
		$observation = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		PGR_Upstream_Radar::validateObservation( $observation );

		foreach ( array( 'QUALIFIED', 'ADMITTED' ) as $invalid ) {
			$mutated                   = $observation;
			$mutated['evidence_state'] = $invalid;
			try {
				PGR_Upstream_Radar::validateObservation( $mutated );
				$this->fail( 'Docs-only observation accepted forbidden state: ' . $invalid );
			} catch ( RuntimeException $exception ) {
				$this->assertStringContainsString( 'DETECTED_ONLY', $exception->getMessage() );
			}
		}
	}

	public function test_documented_change_is_distinct_from_no_relevant_documented_change(): void {
		$path        = $this->root . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json';
		$observation = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		$observation['classifications'][] = 'DOCUMENTED_CONTRACT_CHANGE';

		$this->expectException( RuntimeException::class );
		PGR_Upstream_Radar::validateObservation( $observation );
	}

	public function test_potential_better_seam_requires_explicit_supporting_official_evidence(): void {
		$path        = $this->root . '/tools/compatibility/upstream-observations/gravityflow/3.1.1.1.json';
		$observation = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		$observation['classifications'][] = 'POTENTIAL_BETTER_SEAM_FOUND';
		$observation['capability_observations'][] = array(
			'capability'     => 'gravityflow.example',
			'classification' => 'POTENTIAL_BETTER_SEAM_FOUND',
			'summary'        => 'Candidate only.',
		);

		try {
			PGR_Upstream_Radar::validateObservation( $observation );
			$this->fail( 'Bare better-seam classification unexpectedly passed.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'supporting_official_evidence', $exception->getMessage() );
		}

		$index = array_key_last( $observation['capability_observations'] );
		$observation['capability_observations'][ $index ]['supporting_official_evidence'] = array( 'gravityflow_example_filter' );
		PGR_Upstream_Radar::validateObservation( $observation );
		$this->addToAssertionCount( 1 );
	}

	public function test_reviewed_observations_are_product_scoped_and_automation_does_not_overwrite_them(): void {
		$tmp = $this->makeTemporaryRoot();
		try {
			$candidate = $this->automationCandidate( 'gravityforms', '3.1.2' );
			$this->assertSame( 'CREATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );
			$this->assertFileExists( $tmp . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json' );
			$this->assertSame( 'AUTOMATED_DEDUPLICATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );

			$path     = $tmp . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json';
			$reviewed = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
			$reviewed['review_state']          = 'MODEL_REVIEWED';
			$reviewed['model_review_required'] = false;
			$reviewed['automation_generated']  = false;
			foreach ( $reviewed['official_sources'] as &$source ) {
				unset( $source['sha256'] );
			}
			unset( $source );
			file_put_contents( $path, json_encode( $reviewed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

			$candidate['checked_at'] = '2026-09-28T17:55:00Z';
			$this->assertSame( 'REVIEWED_DEDUPLICATED', PGR_Upstream_Radar::persistCandidate( $candidate, $tmp ) );
			$after = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
			$this->assertSame( '2026-09-27T17:55:00Z', $after['checked_at'] );
		} finally {
			$this->removeDirectory( $tmp );
		}
	}

	public function test_persistence_never_mutates_package_or_version_authority(): void {
		$tmp = $this->makeTemporaryRoot();
		try {
			$authorityDir = $tmp . '/tools/compatibility';
			@mkdir( $authorityDir, 0777, true );
			$packagePath = $authorityDir . '/gravityflow-package.json';
			file_put_contents( $packagePath, "{\"sentinel\":true}\n" );
			$before = hash_file( 'sha256', $packagePath );
			PGR_Upstream_Radar::persistCandidate( $this->automationCandidate( 'gravityflow', '3.1.1.1' ), $tmp );
			$this->assertSame( $before, hash_file( 'sha256', $packagePath ) );
		} finally {
			$this->removeDirectory( $tmp );
		}
	}

	public function test_current_profile_set_excludes_child_addons_but_core_accepts_an_explicit_future_profile(): void {
		$document = json_decode( (string) file_get_contents( PGR_Upstream_Radar::defaultProfilesPath() ), true, 512, JSON_THROW_ON_ERROR );
		$keys     = array_column( $document['products'], 'key' );
		$this->assertNotContains( 'gp-file-upload-pro', $keys );
		$this->assertNotContains( 'gp-advanced-select', $keys );

		$future = $document['products'][3];
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
			$loaded = PGR_Upstream_Radar::loadProfiles( $path );
			$this->assertArrayHasKey( 'gp-file-upload-pro', $loaded );
		} finally {
			unlink( $path );
		}
	}

	public function test_arbitrary_site_plugins_are_not_automatically_enrolled(): void {
		$profiles = PGR_Upstream_Radar::loadProfiles();
		$this->assertCount( 4, $profiles );
		$this->assertArrayNotHasKey( 'akismet', $profiles );
		$this->assertArrayNotHasKey( 'woocommerce', $profiles );
	}

	public function test_workflow_has_weekly_manual_and_live_pr_execution_without_direct_main_write(): void {
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$this->assertStringContainsString( 'schedule:', $workflow );
		$this->assertStringContainsString( "cron: '23 6 * * 1'", $workflow );
		$this->assertStringContainsString( 'workflow_dispatch:', $workflow );
		$this->assertStringContainsString( 'pull_request:', $workflow );
		$this->assertStringContainsString( 'php tools/compatibility/upstream-radar.php scan', $workflow );
		$this->assertStringNotContainsString( 'HEAD:main', $workflow );
		$this->assertStringNotContainsString( 'git push origin main', $workflow );
		$this->assertStringNotContainsString( 'gh pr merge', $workflow );
		$this->assertStringNotContainsString( '--auto', $workflow );
	}

	public function test_automated_persistence_is_bounded_to_observation_paths_and_deduplicated_branch(): void {
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$this->assertStringContainsString( 'automation/upstream-radar-observations', $workflow );
		$this->assertStringContainsString( '^tools/compatibility/upstream-observations/', $workflow );
		$this->assertStringContainsString( 'gh pr list --head "$RADAR_BRANCH"', $workflow );
		$this->assertStringContainsString( 'gh pr create', $workflow );
	}

	public function test_radar_has_no_licensed_package_auto_acquisition_path(): void {
		$source   = (string) file_get_contents( $this->root . '/tools/compatibility/upstream-radar.php' );
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$combined = strtolower( $source . "\n" . $workflow );

		$this->assertStringNotContainsString( 'drive.google.com', $combined );
		$this->assertStringNotContainsString( 'google_drive_file_id', $combined );
		$this->assertStringNotContainsString( 'wget ', $combined );
		$this->assertStringNotContainsString( 'curl ', $combined );
	}

	public function test_production_runtime_cannot_consume_observation_state_as_activation_authority(): void {
		$runtimePaths = array( $this->root . '/persian-gravityforms.php', $this->root . '/uninstall.php' );
		foreach ( array( 'includes', 'admin' ) as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$runtimePaths[] = $file->getPathname();
				}
			}
		}

		foreach ( $runtimePaths as $path ) {
			$content = (string) file_get_contents( $path );
			$this->assertStringNotContainsString( 'upstream-observations', $content, str_replace( $this->root . '/', '', $path ) );
			$this->assertStringNotContainsString( 'upstream-radar.php', $content, str_replace( $this->root . '/', '', $path ) );
		}
	}

	public function test_all_committed_observations_validate_and_preserve_package_proof_boundary(): void {
		$this->assertSame( 4, PGR_Upstream_Radar::validateObservationDirectory() );
	}

	private function automationCandidate( string $product, string $version ): array {
		return array(
			'schema_version'              => 1,
			'product_key'                 => $product,
			'product_name'                => ucfirst( $product ),
			'detected_upstream_version'   => $version,
			'project_baseline'            => array(
				'version'           => '3.1.0',
				'authority_kind'    => 'TEST_AUTHORITY',
				'authority_path'    => 'tools/test-authority.json',
				'authority_product' => $product,
			),
			'evidence_state'              => 'DETECTED_ONLY',
			'package_state'               => 'NOT_SUPPLIED_FOR_DETECTED_VERSION',
			'checked_at'                  => '2026-09-27T17:55:00Z',
			'official_sources'            => array(
				array(
					'url'    => 'https://docs.gravityflow.io/changelog/',
					'role'   => 'stable-release-and-changelog',
					'sha256' => str_repeat( 'a', 64 ),
				),
			),
			'classifications'             => array( 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' ),
			'capability_observations'     => array(
				array(
					'capability'             => 'profile.' . $product,
					'classification'         => 'MODEL_REVIEW_REQUIRED',
					'summary'                => 'Needs semantic review.',
					'documented_identifiers' => array(),
				),
			),
			'model_review_required'       => true,
			'package_required_for_proof'  => true,
			'review_state'                => 'MODEL_REVIEW_REQUIRED',
			'automation_generated'        => true,
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
