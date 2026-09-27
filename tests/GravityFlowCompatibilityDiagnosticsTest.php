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
		$catalog  = PGR_Gravity_Flow_Compatibility_Diagnostics::capabilities();

		$this->assertSame( $expected, array_keys( $catalog ) );
		$this->assertCount( count( array_unique( array_keys( $catalog ) ) ), $catalog );

		$registry = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/tools/jalali/g008-system-date-surfaces.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
		$known = array();
		foreach ( $registry['products'] as $product ) {
			if ( 'Gravity Flow' !== $product['product'] ) {
				continue;
			}
			$known = array_column( $product['surfaces'], 'id' );
			break;
		}

		foreach ( $expected as $capability_id ) {
			$this->assertContains( $capability_id, $known, $capability_id );
		}
	}

	public function test_state_and_reason_catalogs_are_small_unique_and_deterministic(): void {
		$states = PGR_Gravity_Flow_Compatibility_Diagnostics::states();
		$this->assertSame(
			array( 'AVAILABLE', 'DEGRADED', 'UNAVAILABLE', 'NOT_EVALUATED' ),
			$states
		);
		$this->assertCount( count( array_unique( $states ) ), $states );

		$reasons = PGR_Gravity_Flow_Compatibility_Diagnostics::reasons();
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
			array_keys( $reasons )
		);
		$this->assertCount( count( array_unique( array_keys( $reasons ) ) ), $reasons );
		foreach ( $reasons as $reason_id => $summary ) {
			$this->assertLessThanOrEqual( 160, strlen( $summary ), $reason_id );
			$this->assertSame( $summary, strip_tags( $summary ), $reason_id );
		}
	}

	public function test_recorder_rejects_unknown_and_unclassified_values(): void {
		$valid_capability = 'gravityflow.inbox.date-created';

		$cases = array(
			array( 'gravityflow.unknown', 'AVAILABLE', 'PGR-GFLOW-CONTRACT-SATISFIED' ),
			array( $valid_capability, 'MAYBE', 'PGR-GFLOW-CONTRACT-SATISFIED' ),
			array( $valid_capability, 'UNAVAILABLE', 'PGR-GFLOW-UNKNOWN' ),
			array( $valid_capability, 'AVAILABLE', 'PGR-GFLOW-HOST-UNQUALIFIED' ),
			array( $valid_capability, 'NOT_EVALUATED', 'PGR-GFLOW-SOURCE-INVALID' ),
			array( $valid_capability, 'UNAVAILABLE', 'PGR-GFLOW-CONTRACT-SATISFIED' ),
			array( $valid_capability, 'DEGRADED', 'PGR-GFLOW-NOT-EVALUATED' ),
		);

		foreach ( $cases as $case ) {
			try {
				PGR_Gravity_Flow_Compatibility_Diagnostics::record( $case[0], $case[1], $case[2] );
				$this->fail( 'Invalid diagnostic observation unexpectedly passed: ' . implode( ' / ', $case ) );
			} catch ( InvalidArgumentException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function test_request_local_precedence_is_deterministic_and_failure_preserving(): void {
		$id = 'gravityflow.inbox.date-created';

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

		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			$id,
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_UNAVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_HOST_UNQUALIFIED
		);
		$record = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot()[ $id ];
		$this->assertSame( 'UNAVAILABLE', $record['state'] );
		$this->assertSame( 'PGR-GFLOW-HOST-UNQUALIFIED', $record['reason_id'] );
	}

	public function test_recorder_has_no_arbitrary_context_or_free_form_message_channel(): void {
		$method = new ReflectionMethod( PGR_Gravity_Flow_Compatibility_Diagnostics::class, 'record' );
		$this->assertSame( 3, $method->getNumberOfParameters() );
		$this->assertSame( 3, $method->getNumberOfRequiredParameters() );

		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			'gravityflow.status.date-created',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_UNAVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_SOURCE_INVALID
		);
		$record = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot()['gravityflow.status.date-created'];

		$this->assertSame(
			array( 'capability_id', 'state', 'reason_id', 'summary', 'observed_at', 'host_version' ),
			array_keys( $record )
		);
		$this->assertSame(
			PGR_Gravity_Flow_Compatibility_Diagnostics::reasons()[ $record['reason_id'] ],
			$record['summary']
		);
	}

	public function test_latest_snapshot_is_schema_bounded_and_non_autoloaded(): void {
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			'gravityflow.timeline-history',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTEXT_UNAVAILABLE
		);

		$this->assertTrue( PGR_Gravity_Flow_Compatibility_Diagnostics::persist_request_snapshot() );
		$this->assertFalse(
			$GLOBALS['pgr_test_option_autoload'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ]
		);

		$payload = $GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ];
		$this->assertSame( array( 'schema_version', 'records' ), array_keys( $payload ) );
		$this->assertSame( 1, $payload['schema_version'] );
		$this->assertLessThanOrEqual( 8192, strlen( wp_json_encode( $payload ) ) );
		$this->assertSame(
			array( 'capability_id', 'state', 'reason_id', 'summary', 'observed_at', 'host_version' ),
			array_keys( $payload['records']['gravityflow.timeline-history'] )
		);
	}

	public function test_missing_malformed_and_stale_persisted_data_reports_not_evaluated(): void {
		$id = 'gravityflow.inbox.due-date';
		$this->assertSame(
			'NOT_EVALUATED',
			PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()[ $id ]['state']
		);

		$GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] = array(
			'schema_version' => 1,
			'records'        => array(
				$id => array(
					'capability_id' => $id,
					'state'         => 'UNAVAILABLE',
					'reason_id'     => 'PGR-GFLOW-SOURCE-INVALID',
					'summary'       => 'Injected free-form payload',
					'observed_at'   => time(),
					'host_version'  => null,
					'context'       => array( 'entry_id' => 123 ),
				),
			),
		);
		$this->assertSame(
			'NOT_EVALUATED',
			PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()[ $id ]['state']
		);

		$GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] = array(
			'schema_version' => 1,
			'records'        => array(
				$id => array(
					'capability_id' => $id,
					'state'         => 'UNAVAILABLE',
					'reason_id'     => 'PGR-GFLOW-SOURCE-INVALID',
					'summary'       => PGR_Gravity_Flow_Compatibility_Diagnostics::reasons()['PGR-GFLOW-SOURCE-INVALID'],
					'observed_at'   => time() - 604801,
					'host_version'  => null,
				),
			),
		);
		$this->assertSame(
			'NOT_EVALUATED',
			PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()[ $id ]['state']
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_persisted_observation_for_another_host_version_is_not_trusted(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );

		$id = 'gravityflow.inbox.date-created';
		$GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] = array(
			'schema_version' => 1,
			'records'        => array(
				$id => array(
					'capability_id' => $id,
					'state'         => 'AVAILABLE',
					'reason_id'     => 'PGR-GFLOW-CONTRACT-SATISFIED',
					'summary'       => PGR_Gravity_Flow_Compatibility_Diagnostics::reasons()['PGR-GFLOW-CONTRACT-SATISFIED'],
					'observed_at'   => time(),
					'host_version'  => '3.1.1',
				),
			),
		);

		$this->assertSame(
			'NOT_EVALUATED',
			PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot()[ $id ]['state']
		);
	}

	public function test_wu02_does_not_create_runtime_authority_or_weaken_existing_guards(): void {
		$root = dirname( __DIR__ );
		$adapter_files = glob( $root . '/includes/class-pgr-gravity-flow-*.php' );
		$this->assertNotFalse( $adapter_files );

		foreach ( $adapter_files as $path ) {
			if ( str_ends_with( $path, 'class-pgr-gravity-flow-compatibility-diagnostics.php' ) ) {
				continue;
			}
			$source = file_get_contents( $path );
			$this->assertStringNotContainsString( 'pgr_gravityflow_compatibility_latest', $source, $path );
			$this->assertStringNotContainsString( '::status_snapshot(', $source, $path );
			$this->assertStringNotContainsString( '::persist_request_snapshot(', $source, $path );
		}

		$inbox = file_get_contents( $root . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $inbox );
		$this->assertStringContainsString( "['target_version']", $inbox );

		$status = file_get_contents( $root . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $status );
		$this->assertStringContainsString( "['target_version']", $status );

		$entry_detail = file_get_contents( $root . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $entry_detail );
		$this->assertStringContainsString( "['target_version']", $entry_detail );

		$timeline = file_get_contents( $root . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "private const FLOW_VERSION = '3.1.0';", $timeline );
		$this->assertStringContainsString( "private const GF_VERSION   = '3.1.1.1';", $timeline );
		$this->assertStringContainsString( 'private const SOURCE_FINGERPRINTS', $timeline );
		$this->assertStringContainsString( 'private const FIRST_CHAIN', $timeline );
		$this->assertStringContainsString( 'private const SECOND_CHAIN', $timeline );

		$package = json_decode(
			file_get_contents( $root . '/tools/compatibility/gravityflow-package.json' ),
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
