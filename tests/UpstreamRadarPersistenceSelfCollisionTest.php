<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar-consistency.php';

final class UpstreamRadarPersistenceSelfCollisionTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/pgr-persistence-collision-' . bin2hex( random_bytes( 6 ) );
        $source = dirname( __DIR__ ) . '/tools/compatibility';
        foreach ( array( 'upstream-observations', 'upstream-source-reviews' ) as $directory ) {
            $this->copyDirectory( $source . '/' . $directory, $this->root . '/tools/compatibility/' . $directory );
        }
    }

    protected function tearDown(): void {
        $this->removeDirectory( $this->root );
    }

    public function test_first_run_and_repeat_run_do_not_treat_own_automated_record_as_historical(): void {
        $candidate = $this->candidate( 'gravityforms', '3.2.0.1' );
        $summary = $this->summary( array( 'gravityforms' => $candidate ) );
        $path = $this->observationPath( 'gravityforms', '3.2.0.1' );

        $this->prepareCandidateAbsent( 'gravityforms', '3.2.0.1' );
        $this->assertFileDoesNotExist( $path );
        $statuses = PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
        $this->assertSame( 'CREATED', $statuses['gravityforms'] );
        $this->assertFileExists( $path );
        $stored = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertSame( 'MODEL_REVIEW_REQUIRED', $stored['review_state'] );
        $this->assertTrue( $stored['model_review_required'] );

        $this->assertSame(
            'AUTOMATED_DEDUPLICATED',
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root )['gravityforms']
        );
        $candidate['official_sources'][0]['sha256'] = str_repeat( 'b', 64 );
        $candidate['checked_at'] = '2026-10-25T12:00:00Z';
        $summary = $this->summary( array( 'gravityforms' => $candidate ) );
        $this->assertSame(
            'AUTOMATED_UPDATED',
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root )['gravityforms']
        );
        $updated = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertSame( 'MODEL_REVIEW_REQUIRED', $updated['review_state'] );
        $this->assertTrue( $updated['model_review_required'] );
        $this->assertSame( str_repeat( 'b', 64 ), $updated['official_sources'][0]['sha256'] );
    }


    public function test_committed_automated_record_deduplicates_then_allows_only_automation_update(): void {
        $path = $this->observationPath( 'gravityforms', '3.2.0.1' );
        $this->assertFileExists( $path );
        $original = (string) file_get_contents( $path );
        $before = $this->evidenceFiles();
        $candidate = json_decode( $original, true, 512, JSON_THROW_ON_ERROR );
        $this->assertTrue( $candidate['automation_generated'] );
        $this->assertSame( 'MODEL_REVIEW_REQUIRED', $candidate['review_state'] );
        $profiles = PGR_Upstream_Radar::loadProfiles();

        $this->assertSame(
            'AUTOMATED_DEDUPLICATED',
            PGR_Upstream_Radar::persistValidatedResults( $this->summary( array( 'gravityforms' => $candidate ) ), $profiles, $this->root )['gravityforms']
        );
        $this->assertSame( $original, file_get_contents( $path ) );

        $candidate['official_sources'][0]['sha256'] = str_repeat( 'b', 64 );
        $this->assertSame(
            'AUTOMATED_UPDATED',
            PGR_Upstream_Radar::persistValidatedResults( $this->summary( array( 'gravityforms' => $candidate ) ), $profiles, $this->root )['gravityforms']
        );
        $updated = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertSame( $candidate, $updated );
        $this->assertSame(
            'AUTOMATED_DEDUPLICATED',
            PGR_Upstream_Radar::persistValidatedResults( $this->summary( array( 'gravityforms' => $candidate ) ), $profiles, $this->root )['gravityforms']
        );

        $after = $this->evidenceFiles();
        $relative = 'tools/compatibility/upstream-observations/gravityforms/3.2.0.1.json';
        unset( $before[$relative], $after[$relative] );
        $this->assertSame( $before, $after, 'Historical, reviewed, unrelated observations and source attestations changed.' );
    }

    public function test_unresolved_historical_flow_observation_still_blocks_without_writing_anything(): void {
        $summary = $this->summary( array( 'gravityforms' => $this->candidate( 'gravityforms', '3.2.0.1' ) ) );
        $this->prepareCandidateAbsent( 'gravityforms', '3.2.0.1' );
        $summary['results'][1]['latest_upstream_version'] = '3.1.1.1';

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'unresolved historical observation 3.1.1.1' );
        try {
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
        } finally {
            $this->assertFileDoesNotExist( $this->observationPath( 'gravityforms', '3.2.0.1' ) );
            $historical = json_decode( (string) file_get_contents( $this->observationPath( 'gravityflow', '3.1.1.1' ) ), true, 512, JSON_THROW_ON_ERROR );
            $this->assertSame( 'MODEL_REVIEW_REQUIRED', $historical['review_state'] );
        }
    }

    public function test_stale_live_flow_source_blocks_before_any_persistence(): void {
        $summary = $this->summary( array( 'gravityforms' => $this->candidate( 'gravityforms', '3.2.0.1' ) ) );
        $this->prepareCandidateAbsent( 'gravityforms', '3.2.0.1' );
        $summary['results'][1]['latest_upstream_version'] = '3.1.0';
        $this->expectExceptionMessage( 'stale or ambiguous' );
        try {
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
        } finally {
            $this->assertFileDoesNotExist( $this->observationPath( 'gravityforms', '3.2.0.1' ) );
        }
    }

    public function test_changed_reviewed_perks_source_blocks_all_writes_even_after_valid_new_candidate(): void {
        $perks = json_decode(
            (string) file_get_contents( $this->observationPath( 'gravityperks', '2.3.17' ) ),
            true, 512, JSON_THROW_ON_ERROR
        );
        $this->prepareCandidateAbsent( 'gravityforms', '3.2.0.1' );
        $perks['official_sources'][0]['sha256'] = str_repeat( 'c', 64 );
        $summary = $this->summary( array(
            'gravityforms' => $this->candidate( 'gravityforms', '3.2.0.1' ),
            'gravityperks' => $perks,
        ) );
        $this->expectExceptionMessage( 'MODEL_REVIEW_REQUIRED' );
        try {
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
        } finally {
            $this->assertFileDoesNotExist( $this->observationPath( 'gravityforms', '3.2.0.1' ) );
        }
    }

    public function test_unchanged_reviewed_source_remains_attested_and_immutable(): void {
        $path = $this->observationPath( 'gravityperks', '2.3.17' );
        $before = hash_file( 'sha256', $path );
        $perks = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
        $summary = $this->summary( array( 'gravityperks' => $perks ) );
        $this->assertSame(
            'REVIEWED_DEDUPLICATED',
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root )['gravityperks']
        );
        $this->assertSame( $before, hash_file( 'sha256', $path ) );
    }

    public function test_partial_source_failure_and_invalid_later_candidate_prevent_earlier_writes(): void {
        $summary = $this->summary( array( 'gravityforms' => $this->candidate( 'gravityforms', '3.2.0.1' ) ) );
        $this->prepareCandidateAbsent( 'gravityforms', '3.2.0.1' );
        $summary['failures']['gravityview'] = 'upstream HTTP failure';
        try {
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
            $this->fail( 'Partial source failure was accepted.' );
        } catch ( RuntimeException $exception ) {
            $this->assertStringContainsString( 'source failures', $exception->getMessage() );
        }
        $this->assertFileDoesNotExist( $this->observationPath( 'gravityforms', '3.2.0.1' ) );

        $bad = $this->candidate( 'gravityview', '3.9.1' );
        $bad['review_state'] = 'MODEL_REVIEWED';
        $summary = $this->summary( array(
            'gravityforms' => $this->candidate( 'gravityforms', '3.2.0.1' ),
            'gravityview' => $bad,
        ) );
        try {
            PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
            $this->fail( 'Invalid later candidate was accepted.' );
        } catch ( RuntimeException $exception ) {
            $this->assertStringContainsString( 'reviewed observation', $exception->getMessage() );
        }
        $this->assertFileDoesNotExist( $this->observationPath( 'gravityforms', '3.2.0.1' ) );
    }

    public function test_candidate_identity_mismatch_is_rejected_before_writes(): void {
        $summary = $this->summary( array( 'gravityforms' => $this->candidate( 'gravityflow', '3.1.2' ) ) );
        $this->expectExceptionMessage( 'candidate identity' );
        PGR_Upstream_Radar::persistValidatedResults( $summary, PGR_Upstream_Radar::loadProfiles(), $this->root );
    }

    public function test_workflow_never_rechecks_consistency_against_mutated_observation_tree(): void {
        $workflow = (string) file_get_contents( dirname( __DIR__ ) . '/.github/workflows/upstream-compatibility-radar.yml' );
        $persistenceJob = substr( $workflow, (int) strpos( $workflow, "\n  persist-observations:" ) );
        $this->assertStringContainsString( '--persist-new', $persistenceJob );
        $this->assertStringNotContainsString( 'upstream-radar-consistency.php "$evidence_dir/summary.json"', $persistenceJob );
    }


    /**
     * Model first persistence without rewriting the committed evidence authority.
     * Only the selected automatic candidate is absent in this isolated fixture.
     */
    private function prepareCandidateAbsent( string $product, string $version ): void {
        $path = $this->observationPath( $product, $version );
        if ( !is_file( $path ) ) {
            return;
        }
        $observation = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
        PGR_Upstream_Radar::validateObservation( $observation );
        $this->assertSame( $product, $observation['product_key'] );
        $this->assertSame( $version, $observation['detected_upstream_version'] );
        $this->assertSame( 'MODEL_REVIEW_REQUIRED', $observation['review_state'] );
        $this->assertTrue( $observation['automation_generated'] );
        $this->assertTrue( unlink( $path ), 'Failed to prepare isolated candidate-absent fixture.' );
    }

    private function evidenceFiles(): array {
        $files = array();
        $base = $this->root . '/tools/compatibility';
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $relative = substr( $file->getPathname(), strlen( $this->root ) + 1 );
                $files[$relative] = hash_file( 'sha256', $file->getPathname() );
            }
        }
        ksort( $files );
        return $files;
    }

    private function candidate( string $product, string $version ): array {
        $record = json_decode( (string) file_get_contents( $this->observationPath( 'gravityforms', '3.1.2' ) ), true, 512, JSON_THROW_ON_ERROR );
        $record['product_key'] = $product;
        $record['product_name'] = $product;
        $record['detected_upstream_version'] = $version;
        $record['review_state'] = 'MODEL_REVIEW_REQUIRED';
        $record['model_review_required'] = true;
        $record['automation_generated'] = true;
        $record['checked_at'] = '2026-10-24T18:00:00Z';
        $record['classifications'] = array( 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' );
        $record['capability_observations'] = array( array(
            'capability' => 'profile.' . $product,
            'classification' => 'MODEL_REVIEW_REQUIRED',
            'summary' => 'Research only',
            'documented_identifiers' => array(),
        ) );
        $record['official_sources'][0]['sha256'] = str_repeat( 'a', 64 );
        if ( $product === 'gravityflow' ) {
            $record['official_sources'][0]['url'] = 'https://docs.gravityflow.io/changelog/';
        } elseif ( $product === 'gravityview' ) {
            $record['official_sources'][0]['url'] = 'https://www.gravitykit.com/products/gravityview/changelog';
        }
        return $record;
    }

    private function summary( array $candidates ): array {
        $versions = array(
            'gravityforms' => '3.2.0.1',
            'gravityflow' => '3.1.2',
            'gravityview' => '3.9.1',
            'gravityperks' => '2.3.17',
        );
        $results = array();
        foreach ( $versions as $key => $version ) {
            $results[] = array(
                'product_key' => $key,
                'latest_upstream_version' => $version,
                'observation_candidate' => $candidates[$key] ?? null,
            );
        }
        return array( 'schema_version' => 1, 'results' => $results, 'failures' => array() );
    }

    private function observationPath( string $product, string $version ): string {
        return $this->root . '/tools/compatibility/upstream-observations/' . $product . '/' . $version . '.json';
    }

    private function copyDirectory( string $source, string $destination ): void {
        mkdir( $destination, 0777, true );
        foreach ( new DirectoryIterator( $source ) as $file ) {
            if ( $file->isDot() ) {
                continue;
            }
            $from = $file->getPathname();
            $to = $destination . '/' . $file->getFilename();
            if ( $file->isDir() ) {
                $this->copyDirectory( $from, $to );
            } else {
                copy( $from, $to );
            }
        }
    }

    private function removeDirectory( string $directory ): void {
        if ( !is_dir( $directory ) ) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $iterator as $item ) {
            if ( $item->isDir() && !$item->isLink() ) {
                rmdir( $item->getPathname() );
            } else {
                unlink( $item->getPathname() );
            }
        }
        rmdir( $directory );
    }
}
