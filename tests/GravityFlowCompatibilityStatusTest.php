<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';
require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
require_once dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php';

final class GravityFlowCompatibilityStatusTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->reset_diagnostics();
		$GLOBALS['pgr_test_options']         = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
		$GLOBALS['pgr_test_actions']         = array();
	}

	public function test_active_system_status_path_calls_gravity_flow_diagnostics_section(): void {
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/persian-gravityforms.php' );
		$admin     = file_get_contents( dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php' );

		$this->assertStringContainsString(
			"require_once PGR_PATH . 'includes/class-pgr-gravity-flow-compatibility-diagnostics.php';",
			$bootstrap
		);
		$this->assertStringContainsString( 'new PGR_Product_Admin( new PGR_Admin() )', $bootstrap );
		$this->assertStringContainsString( 'public function render_status_page()', $admin );
		$this->assertStringContainsString( '$this->render_gravity_flow_compatibility_status();', $admin );
	}

	public function test_status_section_truthfully_distinguishes_no_evidence_from_unavailable(): void {
		$html = $this->render_section();

		$this->assertStringContainsString( 'Gravity Flow compatibility diagnostics', $html );
		$this->assertStringContainsString( 'Informational maintenance evidence only.', $html );
		$this->assertStringContainsString( 'NOT_EVALUATED', $html );
		$this->assertStringContainsString( 'PGR-GFLOW-NOT-EVALUATED', $html );
		$this->assertStringContainsString( 'No current trustworthy compatibility observation is available.', $html );
		$this->assertStringNotContainsString( 'data-state="UNAVAILABLE"', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'Force Enable', $html );
		$this->assertStringNotContainsString( 'Delete', $html );
	}

	public function test_status_section_renders_classified_request_observation_read_only(): void {
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			'gravityflow.entry-detail.schedule',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_UNAVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_SEAM_UNAVAILABLE
		);

		$html = $this->render_section();
		$this->assertStringContainsString( 'Entry Detail — Scheduled', $html );
		$this->assertStringContainsString( 'data-state="UNAVAILABLE"', $html );
		$this->assertStringContainsString( 'PGR-GFLOW-SEAM-UNAVAILABLE', $html );
		$this->assertStringContainsString( 'A required host presentation seam or source contract was unavailable.', $html );
	}

	public function test_malformed_persisted_payload_is_not_rendered_as_incompatibility(): void {
		$id = 'gravityflow.timeline-history';
		$GLOBALS['pgr_test_options'][ PGR_Gravity_Flow_Compatibility_Diagnostics::OPTION ] = array(
			'schema_version' => 1,
			'records'        => array(
				$id => array(
					'capability_id' => $id,
					'state'         => 'UNAVAILABLE',
					'reason_id'     => 'PGR-GFLOW-SOURCE-INVALID',
					'summary'       => '<script>alert(1)</script>',
					'observed_at'   => time(),
					'host_version'  => null,
				),
			),
		);

		$html = $this->render_section();
		$this->assertStringNotContainsString( 'alert(1)', $html );
		$this->assertStringContainsString( 'gravityflow.timeline-history', $html );
		$this->assertStringContainsString( 'PGR-GFLOW-NOT-EVALUATED', $html );
	}

	private function render_section(): string {
		$admin = new PGR_Product_Admin( new PGR_Admin() );
		ob_start();
		$admin->render_gravity_flow_compatibility_status();
		return (string) ob_get_clean();
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
