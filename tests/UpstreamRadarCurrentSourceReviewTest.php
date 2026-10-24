<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-radar.php';

final class UpstreamRadarCurrentSourceReviewTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/pgr-source-review-' . bin2hex( random_bytes( 8 ) );
        $base = $this->root . '/tools/compatibility';
        mkdir( $base . '/upstream-observations/gravityperks', 0777, true );
        mkdir( $base . '/upstream-observations/gravityflow', 0777, true );
        mkdir( $base . '/upstream-source-reviews', 0777, true );
        foreach ( array( 'gravityperks/2.3.17.json', 'gravityflow/3.1.1.json', 'gravityflow/3.1.1.1.json' ) as $file ) {
            copy( dirname( __DIR__ ) . '/tools/compatibility/upstream-observations/' . $file, $base . '/upstream-observations/' . $file );
        }
        copy( dirname( __DIR__ ) . '/tools/compatibility/upstream-source-reviews/gravityperks-2.3.17.json', $base . '/upstream-source-reviews/gravityperks-2.3.17.json' );
    }

    protected function tearDown(): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $it as $item ) {
            $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
        }
        rmdir( $this->root );
    }

    private function currentPerksCandidate(): array {
        $file = $this->root . '/tools/compatibility/upstream-observations/gravityperks/2.3.17.json';
        $candidate = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
        $review = json_decode(
            (string) file_get_contents( $this->root . '/tools/compatibility/upstream-source-reviews/gravityperks-2.3.17.json' ),
            true, 512, JSON_THROW_ON_ERROR
        );
        foreach ( $candidate['official_sources'] as &$source ) {
            $source['sha256'] = $review['current_source_fingerprints'][ $source['url'] ];
            $source['review_scope_sha256'] = $review['current_scoped_fingerprints'][ $source['url'] ];
        }
        unset( $source );
        $candidate['checked_at'] = '2026-10-24T10:42:18Z';
        $candidate['review_state'] = 'MODEL_REVIEW_REQUIRED';
        $candidate['model_review_required'] = true;
        $candidate['automation_generated'] = true;
        $candidate['classifications'] = array( 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' );
        $candidate['capability_observations'] = array( array(
            'capability' => 'profile.gravityperks', 'classification' => 'MODEL_REVIEW_REQUIRED',
            'summary' => 'New official evidence requires separate package proof.', 'documented_identifiers' => array(),
        ) );
        PGR_Upstream_Radar::validateObservation( $candidate );
        return $candidate;
    }

    public function test_dated_current_review_accepts_only_exact_sources_without_rewriting_history(): void {
        $path = $this->root . '/tools/compatibility/upstream-observations/gravityperks/2.3.17.json';
        $original = (string) file_get_contents( $path );
        $candidate = $this->currentPerksCandidate();
        $outcome = PGR_Upstream_Radar::persistCandidate( $candidate, $this->root );
        $this->assertSame( 'REVIEWED_CURRENT_SOURCE_ATTESTED', $outcome );
        PGR_Upstream_Radar::assertPersistenceOutcome( 'gravityperks', $outcome );
        $this->assertSame( $original, file_get_contents( $path ) );
        $this->assertSame( 'MODEL_REVIEWED', json_decode( $original, true )['review_state'] );
    }

    public function test_irrelevant_page_change_is_admitted_only_with_both_exact_reviewed_content_digests(): void {
        $record = $this->root . '/tools/compatibility/upstream-observations/gravityperks/2.3.17.json';
        $original = (string) file_get_contents( $record );
        $candidate = $this->currentPerksCandidate();
        // The real next fetch changed the Weekly full-page bytes but not its scoped release text.
        $candidate['official_sources'][0]['sha256'] = 'dfb40bf4c46811d9fa8484a8bd0e08021eb68eb39cd5d3ea59a1cc08238cb097';
        $this->assertSame( 'REVIEWED_CURRENT_SOURCE_ATTESTED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
        $this->assertSame( $original, file_get_contents( $record ) );

        $candidate['official_sources'][1]['sha256'] = str_repeat( 'c', 64 );
        $candidate['official_sources'][1]['review_scope_sha256'] = str_repeat( 'd', 64 );
        $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
    }

    public function test_modified_release_text_cannot_pass_with_new_full_page_hash(): void {
        $candidate = $this->currentPerksCandidate();
        $candidate['official_sources'][0]['sha256'] = 'dfb40bf4c46811d9fa8484a8bd0e08021eb68eb39cd5d3ea59a1cc08238cb097';
        $candidate['official_sources'][0]['review_scope_sha256'] = str_repeat( 'e', 64 );
        $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
    }

    public function test_future_changes_in_either_authoritative_source_still_fail_closed(): void {
        foreach ( array( 0, 1 ) as $index ) {
            $candidate = $this->currentPerksCandidate();
            $candidate['official_sources'][ $index ]['sha256'] = str_repeat( 'a', 64 );
            $status = PGR_Upstream_Radar::persistCandidate( $candidate, $this->root );
            $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', $status );
            try {
                PGR_Upstream_Radar::assertPersistenceOutcome( 'gravityperks', $status );
                $this->fail( 'A changed official source was silently approved.' );
            } catch ( RuntimeException $exception ) {
                $this->assertStringContainsString( 'MODEL_REVIEW_REQUIRED', $exception->getMessage() );
            }
        }
    }

    public function test_missing_or_falsified_review_and_ambiguous_source_identity_fail_closed(): void {
        $reviewPath = $this->root . '/tools/compatibility/upstream-source-reviews/gravityperks-2.3.17.json';
        $baseline = (string) file_get_contents( $reviewPath );
        $candidate = $this->currentPerksCandidate();
        $candidate['official_sources'][0]['url'] = 'https://gravitywiz.com/unexpected/';
        $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );

        $candidate = $this->currentPerksCandidate();
        unlink( $reviewPath );
        $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
        file_put_contents( $reviewPath, $baseline );

        $review = json_decode( $baseline, true, 512, JSON_THROW_ON_ERROR );
        $review['historical_source_fingerprints']['https://gravitywiz.com/gravity-wiz-weekly-269/'] = str_repeat( 'b', 64 );
        file_put_contents( $reviewPath, json_encode( $review, JSON_THROW_ON_ERROR ) );
        $this->assertSame( 'REVIEWED_SOURCE_CHANGED_REVIEW_REQUIRED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
    }

    public function test_flow_new_candidate_is_created_without_updating_historical_reviews(): void {
        $dir = $this->root . '/tools/compatibility/upstream-observations/gravityflow/';
        $past = array( '3.1.1.json' => (string) file_get_contents( $dir . '3.1.1.json' ),
            '3.1.1.1.json' => (string) file_get_contents( $dir . '3.1.1.1.json' ) );
        $candidate = json_decode( $past['3.1.1.json'], true, 512, JSON_THROW_ON_ERROR );
        $candidate['detected_upstream_version'] = '3.1.2.1';
        $candidate['review_state'] = 'MODEL_REVIEW_REQUIRED';
        $candidate['model_review_required'] = true;
        $candidate['automation_generated'] = true;
        $candidate['classifications'] = array( 'MODEL_REVIEW_REQUIRED', 'PACKAGE_REQUIRED_FOR_PROOF' );
        $candidate['capability_observations'] = array( array(
            'capability' => 'profile.gravityflow', 'classification' => 'MODEL_REVIEW_REQUIRED',
            'summary' => 'Newer source requires qualification.', 'documented_identifiers' => array(),
        ) );
        $candidate['official_sources'][0]['sha256'] = 'fac0e6b6ca19525c5896a5a45eb3cb6965482085764c199086467518bc37bdd4';
        PGR_Upstream_Radar::validateObservation( $candidate );
        $this->assertSame( 'CREATED', PGR_Upstream_Radar::persistCandidate( $candidate, $this->root ) );
        foreach ( $past as $file => $bytes ) {
            $this->assertSame( $bytes, file_get_contents( $dir . $file ) );
        }
        $created = json_decode( (string) file_get_contents( $dir . '3.1.2.1.json' ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertSame( 'MODEL_REVIEW_REQUIRED', $created['review_state'] );
        $this->assertTrue( $created['package_required_for_proof'] );
    }
}
