<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/ci/check-php82-runtime.php';

final class CiPhp82RuntimePolicyTest extends TestCase {

    public function test_exact_php_minor_boundaries_and_prereleases(): void {
        $cases = array(
            array( 80199, '8.1.99', false ),
            array( 80200, '8.2.0', true ),
            array( 80233, '8.2.33', true ),
            array( 80234, '8.2.34', true ),
            array( 80235, '8.2.35', true ),
            array( 80299, '8.2.99', true ),
            array( 80300, '8.3.0', false ),
            array( 80424, '8.4.24', false ),
            array( 80235, '8.2.35RC1', false ),
            array( 80235, '8.2.35-dev', false ),
        );

        foreach ( $cases as $case ) {
            $this->assertSame(
                $case[2],
                pgr_ci_accepts_php82_runtime( $case[0], $case[1] ),
                sprintf( 'PHP version %s, version ID %d', $case[1], $case[0] )
            );
        }
    }

    public function test_both_licensed_workflows_invoke_the_same_fail_visible_runtime_guard(): void {
        $root = dirname( __DIR__ );
        foreach ( array( 'g008-jalali-presentation-runtime.yml', 'wu008-real-integration.yml' ) as $workflow ) {
            $yaml = file_get_contents( $root . '/.github/workflows/' . $workflow );
            $this->assertIsString( $yaml );
            $this->assertStringContainsString( 'php tools/ci/check-php82-runtime.php', $yaml );
        }
    }
}
