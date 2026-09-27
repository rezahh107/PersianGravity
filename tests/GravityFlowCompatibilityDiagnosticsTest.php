<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';

final class GravityFlowCompatibilityDiagnosticsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->reset_diagnostics();
		$GLOBALS['pgr_test_options']         = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
		$GLOBALS['pgr_test_actions']         = array();
	}

	public function test_capability_catalog_reuses_established_g008_gravity_flow_surface_ids(): void {
		$expected = array(
			'gravityflow.inbox.date-created',
			'gravityflow.inbox.last-updated',
			'gravityflow.inbox.due-date',
			'gravityflow.status.date-created',
			'gravityflow.status.workflow-timestamp',
			'gravityflow.status.due-date',
			'gravityflow.entry-detail.submitted',
			'gravityflow.entry-detail.last-updated',
			'gravityflow.entry-detail.due-date',
			'gravityflow.entry-detail.expiration',
			'gravityflow.entry-detail.schedule',
			'gravityflow.timeline-history',
			'gravityflow.print',
		);
		$catalog = PGR_Gravity_Flow_Compatibility_Diagnostics::capabilities();
		$this->assertSame( $expected, array_keys( $catalog ) );
		$this->assertCount( count( array_unique( array_keys( $catalog ) ) ), $catalog );
	}

	public function test_state_and_reason_catalogs_remain_bounded_and_deterministic(): void {
		$this->assertSame(
			array( 'AVAILABLE', 'DEGRADED', 'UNAVAILABLE', 'NOT_EVALUATED' ),
			PGR_Gravity_Flow_Compatibility_Diagnostics::states()
		);
		$this->assertSame(
			array(
				'PGR-GFLOW-CONTRACT-SATISFIED',
				'PGR-GFLOW-HOST-UNQUALIFIED',
				'PGR-GFLOW-SEAM-UNAVAILABLE',
				'PGR-GFLOW-CONTEXT-UNAVAILABLE',
				'PGR-GFLOW-SOURCE-INVALID',
				'PGR-GFLOW-NOT-APPLICABLE',
				'PGR-GFLOW-CONVERSION-UNAVAILABLE',
				'PGR-GFLOW-NOT-EVALUATED',
			),
			array_keys( PGR_Gravity_Flow_Compatibility_Diagnostics::reasons() )
		);
	}

	public function test_recorder_rejects_unknown_or_inconsistent_observations(): void {
		$cases = array(
			array( 'gravityflow.unknown', 'AVAILABLE', 'PGR-GFLOW-CONTRACT-SATISFIED' ),
			array( 'gravityflow.timeline-history', 'MAYBE', 'PGR-GFLOW-CONTRACT-SATISFIED' ),
			array( 'gravityflow.timeline-history', 'UNAVAILABLE', 'PGR-GFLOW-UNKNOWN' ),
			array( 'gravityflow.timeline-history', 'AVAILABLE', 'PGR-GFLOW-HOST-UNQUALIFIED' ),
			array( 'gravityflow.print', 'NOT_EVALUATED', 'PGR-GFLOW-SOURCE-INVALID' ),
		);
		foreach ( $cases as $case ) {
			try {
				PGR_Gravity_Flow_Compatibility_Diagnostics::record( $case[0], $case[1], $case[2] );
				$this->fail( 'Invalid diagnostic observation unexpectedly passed.' );
			} catch ( InvalidArgumentException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function test_request_local_precedence_preserves_failures_over_later_success(): void {
		$id = 'gravityflow.timeline-history';
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			$id,
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_AVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTRACT_SATISFIED
		);
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			$id,
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTEXT_UNAVAILABLE
		);
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			$id,
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_AVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTRACT_SATISFIED
		);

		$record = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot()[ $id ];
		$this->assertSame( 'DEGRADED', $record['state'] );
		$this->assertSame( 'PGR-GFLOW-CONTEXT-UNAVAILABLE', $record['reason_id'] );
	}

	public function test_latest_snapshot_is_reporting_only_bounded_and_non_autoloaded(): void {
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			'gravityflow.timeline-history',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTEXT_UNAVAILABLE
		);
		$this->assertTrue( PGR_Gravity_Flow_Compatibility_Diagnostics::persist_request_snapshot() );
		$this->assertFalse( $GLOBALS['pgr_test_option_autoload'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] );

		$payload = $GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ];
		$this->assertSame( array( 'schema_version', 'records' ), array_keys( $payload ) );
		$this->assertLessThanOrEqual( 8192, strlen( wp_json_encode( $payload ) ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_persisted_observation_from_another_host_version_is_not_runtime_truth(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		$id = 'gravityflow.timeline-history';
		$GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] = array(
			'schema_version' => 1,
			'records' => array(
				$id => array(
					'capability_id' => $id,
					'state' => 'AVAILABLE',
					'reason_id' => 'PGR-GFLOW-CONTRACT-SATISFIED',
					'summary' => PGR_Gravity_Flow_Compatibility_Diagnostics::reasons()['PGR-GFLOW-CONTRACT-SATISFIED'],
					'observed_at' => time(),
					'host_version' => '9.9.9',
				),
			),
		);
		$this->assertSame( 'NOT_EVALUATED', PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()[ $id ]['state'] );
	}

	public function test_wu04_keeps_diagnostics_reporting_only_and_preserves_unrelated_compatibility_authority(): void {
		$root          = dirname( __DIR__ );
		$adapter_files = glob( $root . '/includes/class-pgr-gravity-flow-*.php' );
		$this->assertNotFalse( $adapter_files );

		foreach ( $adapter_files as $path ) {
			if ( str_ends_with( $path, 'class-pgr-gravity-flow-compatibility-diagnostics.php' ) ) {
				continue;
			}
			$source = (string) file_get_contents( $path );
			$this->assertStringNotContainsString( 'pgr_gravityflow_compatibility_latest', $source, $path );
			$this->assertStringNotContainsString( '::status_snapshot(', $source, $path );
			$this->assertStringNotContainsString( '::persist_request_snapshot(', $source, $path );
		}

		$date_adapter = (string) file_get_contents( $root . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $date_adapter );
		$this->assertStringContainsString( "['target_version']", $date_adapter );
		$this->assertStringContainsString( 'is_exact_qualified_date_family_host()', $date_adapter );

		$timeline = (string) file_get_contents( $root . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $timeline );
		$this->assertStringContainsString( "HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME'", $timeline );
		$this->assertStringContainsString( "HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php'", $timeline );
		$this->assertStringContainsString( 'private const FIRST_CHAIN', $timeline );
		$this->assertStringContainsString( 'private const TIME_FORMAT_CHAIN', $timeline );
		$this->assertStringContainsString( 'private const SECOND_CHAIN', $timeline );
		$this->assertStringContainsString( 'is_print_inheritance_trace', $timeline );
		$this->assertStringContainsString( 'PGR_Gravity_Flow_Compatibility_Diagnostics::record', $timeline );
		$this->assertStringNotContainsString( 'SOURCE_FINGERPRINTS', $timeline );
		$this->assertStringNotContainsString( "hash_file( 'sha256'", $timeline );
		$this->assertStringNotContainsString( "['target_version']", $timeline );
		$this->assertStringNotContainsString( "private const FLOW_VERSION = '3.1.0'", $timeline );
		$this->assertStringNotContainsString( 'private const GF_VERSION', $timeline );

		$package = json_decode(
			(string) file_get_contents( $root . '/tools/compatibility/gravityflow-package.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
		$this->assertSame( '3.1.0', $package['version'] );
		$this->assertSame( 'OWNER_SUPPLIED_GOOGLE_DRIVE', $package['authority'] );
		$this->assertSame( '1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf', $package['google_drive_file_id'] );
		$this->assertSame( 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404', $package['sha256'] );
	}

	private function reset_diagnostics(): void {
		$observations = new ReflectionProperty( PGR_Gravity_Flow_Compatibility_Diagnostics::class, 'request_observations' );
		$observations->setAccessible( true );
		$observations->setValue( null, array() );

		$shutdown = new ReflectionProperty( PGR_Gravity_Flow_Compatibility_Diagnostics::class, 'shutdown_registered' );
		$shutdown->setAccessible( true );
		$shutdown->setValue( null, false );
	}
}
