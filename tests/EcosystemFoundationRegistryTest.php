<?php

use PHPUnit\Framework\TestCase;

final class EcosystemFoundationRegistryTest extends TestCase {
	private const G009_STATES = array(
		'NATIVE_PASS',
		'ADAPTER_REQUIRED_AND_VERIFIED',
		'NOT_PROVEN',
		'NOT_APPLICABLE',
		'FAIL_CLOSED_VERSION_DRIFT',
	);

	private function load_registry( string $relative ): array {
		$path = dirname( __DIR__ ) . '/' . $relative;
		$data = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertIsArray( $data );
		return $data;
	}

	public function test_g009_registry_is_exact_version_bound_and_uses_only_owner_locked_states(): void {
		$registry = $this->load_registry( 'tools/compatibility/g009-surfaces.json' );
		$this->assertSame( 1, $registry['schema_version'] );
		$this->assertSame( self::G009_STATES, $registry['allowed_evidence_states'] );
		$this->assertSame(
			array(
				'rtl' => array(
					array( 'width' => 1280, 'height' => 900 ),
					array( 'width' => 390, 'height' => 844 ),
				),
				'ltr' => array(
					array( 'width' => 1280, 'height' => 900 ),
					array( 'width' => 390, 'height' => 844 ),
				),
			),
			$registry['native_pass_runtime_requirements']['profiles']
		);

		$expected = array(
			'Gravity Forms'       => array( '3.1.1.1', '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' ),
			'Gravity Flow'        => array( '3.1.0', 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404' ),
			'GravityView'         => array( '3.3.4', 'af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829' ),
			'Gravity Perks'       => array( '2.3.16', 'a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c' ),
			'GP File Upload Pro'  => array( '1.5.13', 'fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce' ),
			'GP Advanced Select'  => array( '1.1.21', 'd83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2' ),
		);

		$seen   = array();
		$states = array();
		foreach ( $registry['products'] as $product ) {
			$this->assertArrayHasKey( $product['product'], $expected );
			$this->assertSame( $expected[ $product['product'] ][0], $product['version'] );
			$this->assertSame( $expected[ $product['product'] ][1], $product['package_sha256'] );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $product['package_sha256'] );
			$this->assertSame( 'FAIL_CLOSED_VERSION_DRIFT', $product['version_drift'] );
			foreach ( $product['surfaces'] as $surface ) {
				foreach ( array( 'id', 'request_identity', 'rtl_relevance', 'expected_native_behavior', 'style_handles', 'dom_state_signature', 'evidence_state', 'evidence_provenance', 'drift_behavior' ) as $key ) {
					$this->assertArrayHasKey( $key, $surface, $surface['id'] ?? $product['product'] );
				}
				$this->assertContains( $surface['evidence_state'], self::G009_STATES );
				$this->assertArrayNotHasKey( $surface['id'], $seen );
				$seen[ $surface['id'] ]   = true;
				$states[ $surface['id'] ] = $surface['evidence_state'];
			}
		}
		$this->assertSame( array_keys( $expected ), array_column( $registry['products'], 'product' ) );
		$this->assertSame( 'NATIVE_PASS', $states['gravityforms.frontend-form'] );
		$this->assertSame( 'NOT_PROVEN', $states['gravityforms.gform-admin-frontend-reachability'] );
		$this->assertSame( 'NATIVE_PASS', $states['gravityflow.frontend-inbox-ag-grid'] );
		$this->assertSame( 'NATIVE_PASS', $states['gravityview.admin-list'] );
		$this->assertSame( 'NOT_PROVEN', $states['gravityperks.family-baseline'] );
		$this->assertSame( 'NOT_PROVEN', $states['gp-file-upload-pro.frontend'] );
		$this->assertSame( 'NOT_PROVEN', $states['gp-advanced-select.tom-select'] );
	}

	public function test_g009_claim_resolution_fails_closed_on_version_package_handle_or_signature_drift(): void {
		$registry = $this->load_registry( 'tools/compatibility/g009-surfaces.json' );
		$record   = null;
		foreach ( $registry['products'] as $product ) {
			foreach ( $product['surfaces'] as $surface ) {
				if ( 'gp-advanced-select.tom-select' === $surface['id'] ) {
					$record = array( 'product' => $product, 'surface' => $surface );
				}
			}
		}
		$this->assertNotNull( $record );

		$exact = array(
			'version'        => '1.1.21',
			'package_sha256' => 'd83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2',
			'handles'        => array( 'gp-advanced-select-tom-select' ),
			'dom_signature'  => '.ts-wrapper.rtl containing .ts-control',
		);
		$this->assertSame( 'ADAPTER_REQUIRED_AND_VERIFIED', $this->resolve_g009_claim( $record, $exact, 'ADAPTER_REQUIRED_AND_VERIFIED' ) );

		foreach ( array(
			array_replace( $exact, array( 'version' => '1.1.22' ) ),
			array_replace( $exact, array( 'package_sha256' => str_repeat( '0', 64 ) ) ),
			array_replace( $exact, array( 'handles' => array( 'different-handle' ) ) ),
			array_replace( $exact, array( 'dom_signature' => '.changed-vendor-state' ) ),
		) as $drifted ) {
			$this->assertSame( 'FAIL_CLOSED_VERSION_DRIFT', $this->resolve_g009_claim( $record, $drifted, 'ADAPTER_REQUIRED_AND_VERIFIED' ) );
		}
	}

	public function test_g008_registry_keeps_discovery_distinct_from_support_and_records_required_contract_fields(): void {
		$registry = $this->load_registry( 'tools/jalali/g008-system-date-surfaces.json' );
		$this->assertSame( 'PGR_Gregorian_Jalali_Converter', $registry['conversion_authority']['converter'] );
		$this->assertSame( 'PGR_Jalali_Presentation', $registry['conversion_authority']['facade'] );
		$this->assertSame( array( 'ADMITTED_VERIFIED', 'NOT_PROVEN', 'FAIL_CLOSED_VERSION_DRIFT' ), $registry['support_states'] );
		$this->assertSame( array( 'RUNTIME_PROVEN', 'SOURCE_PROVEN', 'NOT_PROVEN' ), $registry['discovery_states'] );

		$required = array( 'id', 'ui_surface', 'raw_source', 'source_calendar', 'source_timezone', 'presentation_seam', 'semantic_dependencies', 'fallback', 'discovery_state', 'support_state', 'adapter_identity', 'evidence', 'drift_behavior' );
		$states   = array();
		foreach ( $registry['products'] as $product ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $product['package_sha256'] );
			$this->assertSame( 'FAIL_CLOSED_VERSION_DRIFT', $product['version_drift'] );
			foreach ( $product['surfaces'] as $surface ) {
				foreach ( $required as $key ) {
					$this->assertArrayHasKey( $key, $surface, $surface['id'] ?? $product['product'] );
				}
				$this->assertContains( $surface['discovery_state'], $registry['discovery_states'] );
				$this->assertContains( $surface['support_state'], $registry['support_states'] );
				$states[ $surface['id'] ] = $surface;
			}
		}

		$this->assertSame( 'RUNTIME_PROVEN', $states['gravityforms.entries-list.date-created']['discovery_state'] );
		$this->assertSame( 'ADMITTED_VERIFIED', $states['gravityforms.entries-list.date-created']['support_state'] );
		$this->assertSame( 'PGR_GF_Jalali_Presentation_Adapter', $states['gravityforms.entries-list.date-created']['adapter_identity'] );

		foreach ( array(
			'gravityflow.inbox.date-created',
			'gravityflow.inbox.last-updated',
			'gravityflow.inbox.due-date',
			'gravityflow.status.date-created',
			'gravityflow.status.workflow-timestamp',
		) as $source_proven_id ) {
			$this->assertSame( 'SOURCE_PROVEN', $states[ $source_proven_id ]['discovery_state'], $source_proven_id );
		}

		foreach ( $states as $id => $surface ) {
			if ( 'gravityforms.entries-list.date-created' !== $id ) {
				$this->assertSame( 'NOT_PROVEN', $surface['support_state'], $id );
				$this->assertNull( $surface['adapter_identity'], $id );
			}
		}
	}

	public function test_g008_claim_resolution_fails_closed_on_host_version_package_or_seam_drift(): void {
		$registry = $this->load_registry( 'tools/jalali/g008-system-date-surfaces.json' );
		$record   = array(
			'product' => $registry['products'][0],
			'surface' => $registry['products'][0]['surfaces'][0],
		);
		$this->assertSame( 'gravityforms.entries-list.date-created', $record['surface']['id'] );

		$exact = array(
			'version'           => '3.1.1.1',
			'package_sha256'    => '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b',
			'presentation_seam' => 'gform_entries_field_value when field/property id is date_created',
		);
		$this->assertSame( 'ADMITTED_VERIFIED', $this->resolve_g008_claim( $record, $exact ) );

		foreach ( array(
			array_replace( $exact, array( 'version' => '3.1.2' ) ),
			array_replace( $exact, array( 'package_sha256' => str_repeat( 'f', 64 ) ) ),
			array_replace( $exact, array( 'presentation_seam' => 'changed_host_filter' ) ),
		) as $drifted ) {
			$this->assertSame( 'FAIL_CLOSED_VERSION_DRIFT', $this->resolve_g008_claim( $record, $drifted ) );
		}
	}

	private function resolve_g009_claim( array $record, array $observed, string $qualified_state ): string {
		$product = $record['product'];
		$surface = $record['surface'];
		if ( $product['version'] !== $observed['version'] || $product['package_sha256'] !== $observed['package_sha256'] ) {
			return 'FAIL_CLOSED_VERSION_DRIFT';
		}
		foreach ( $surface['style_handles'] as $handle ) {
			if ( ! in_array( $handle, $observed['handles'], true ) ) {
				return 'FAIL_CLOSED_VERSION_DRIFT';
			}
		}
		if ( null !== $surface['dom_state_signature'] && $surface['dom_state_signature'] !== $observed['dom_signature'] ) {
			return 'FAIL_CLOSED_VERSION_DRIFT';
		}
		return $qualified_state;
	}

	private function resolve_g008_claim( array $record, array $observed ): string {
		$product = $record['product'];
		$surface = $record['surface'];
		if (
			$product['version'] !== $observed['version'] ||
			$product['package_sha256'] !== $observed['package_sha256'] ||
			$surface['presentation_seam'] !== $observed['presentation_seam']
		) {
			return 'FAIL_CLOSED_VERSION_DRIFT';
		}
		return $surface['support_state'];
	}
}