<?php

use PHPUnit\Framework\TestCase;

final class G010GravityFlowDirectContractArchitectureTest extends TestCase {

	public function test_direct_flow_adapters_do_not_use_localization_version_or_diagnostics_as_activation_authority(): void {
		$paths = array(
			'includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php',
			'includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php',
		);

		foreach ( $paths as $path ) {
			$source = file_get_contents( dirname( __DIR__, 2 ) . '/' . $path );
			$this->assertIsString( $source, $path );
			$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $source, $path );
			$this->assertStringContainsString( "HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME'", $source, $path );
			$this->assertStringContainsString( "HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php'", $source, $path );
			$this->assertStringNotContainsString( 'includes/localization/products.php', $source, $path );
			$this->assertStringNotContainsString( 'target_version', $source, $path );
			$this->assertStringNotContainsString( 'status_snapshot', $source, $path );
			$this->assertStringNotContainsString( 'pgr_gravityflow_compatibility_latest', $source, $path );
		}
	}

	public function test_owner_supplied_gravity_flow_package_authority_remains_unchanged(): void {
		$manifest_path = dirname( __DIR__, 2 ) . '/tools/compatibility/gravityflow-package.json';
		$manifest      = json_decode( (string) file_get_contents( $manifest_path ), true );

		$this->assertIsArray( $manifest );
		$this->assertSame( 'gravityflow', $manifest['product'] ?? null );
		$this->assertSame( '3.1.0', $manifest['version'] ?? null );
		$this->assertSame( 'gravityflow/gravityflow.php', $manifest['plugin_main'] ?? null );
		$this->assertSame( 2603034, $manifest['expected_bytes'] ?? null );
		$this->assertSame(
			'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404',
			$manifest['sha256'] ?? null
		);
	}

	public function test_inbox_due_date_still_avoids_operational_getter_reentry(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php' );

		$this->assertStringContainsString( "DUE_DATE_RAW_ID = 'due_date'", $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'gravityflow_step_due_date_timestamp', $source );
	}
}
