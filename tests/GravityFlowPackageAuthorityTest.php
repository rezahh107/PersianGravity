<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/gravityflow-package.php';

final class GravityFlowPackageAuthorityTest extends TestCase {

	private const FILE_ID  = '1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf';
	private const FILENAME = 'gravityflow-3.1.0-owner-supplied-source-package.zip';
	private const BYTES    = 2603034;
	private const SHA256   = 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404';

	public function test_canonical_manifest_matches_current_owner_supplied_package(): void {
		$package = GravityFlowPackageAuthority::load();

		$this->assertSame( 1, $package['schema_version'] );
		$this->assertSame( 'gravityflow', $package['product'] );
		$this->assertSame( '3.1.0', $package['version'] );
		$this->assertSame( 'OWNER_SUPPLIED_GOOGLE_DRIVE', $package['authority'] );
		$this->assertSame( self::FILE_ID, $package['google_drive_file_id'] );
		$this->assertSame( self::FILENAME, $package['expected_filename'] );
		$this->assertSame( self::BYTES, $package['expected_bytes'] );
		$this->assertSame( self::SHA256, $package['sha256'] );
		$this->assertSame( 'gravityflow/gravityflow.php', $package['plugin_main_file'] );
	}

	public function test_invalid_package_authority_fails_closed(): void {
		$valid = GravityFlowPackageAuthority::load();
		$cases = array(
			'missing sha256' => static function ( array $data ): array {
				unset( $data['sha256'] );
				return $data;
			},
			'unexpected field' => static function ( array $data ): array {
				$data['alternate_url'] = 'https://example.invalid/package.zip';
				return $data;
			},
			'wrong product' => static function ( array $data ): array {
				$data['product'] = 'gravityforms';
				return $data;
			},
			'malformed version' => static function ( array $data ): array {
				$data['version'] = 'latest';
				return $data;
			},
			'wrong authority' => static function ( array $data ): array {
				$data['authority'] = 'VENDOR_DOWNLOAD';
				return $data;
			},
			'invalid Drive file ID' => static function ( array $data ): array {
				$data['google_drive_file_id'] = 'https://drive.google.com/file/d/example';
				return $data;
			},
			'unsafe filename' => static function ( array $data ): array {
				$data['expected_filename'] = '../gravityflow.zip';
				return $data;
			},
			'invalid byte count' => static function ( array $data ): array {
				$data['expected_bytes'] = '2603034';
				return $data;
			},
			'malformed SHA-256' => static function ( array $data ): array {
				$data['sha256'] = strtoupper( self::SHA256 );
				return $data;
			},
			'unsafe plugin main file' => static function ( array $data ): array {
				$data['plugin_main_file'] = '../gravityflow.php';
				return $data;
			},
			'wrong plugin root' => static function ( array $data ): array {
				$data['plugin_main_file'] = 'other-product/gravityflow.php';
				return $data;
			},
			'non-PHP plugin main file' => static function ( array $data ): array {
				$data['plugin_main_file'] = 'gravityflow/gravityflow.txt';
				return $data;
			},
		);

		foreach ( $cases as $label => $mutate ) {
			$path = tempnam( sys_get_temp_dir(), 'pgr-gf-package-' );
			$this->assertNotFalse( $path );
			file_put_contents( $path, json_encode( $mutate( $valid ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

			try {
				GravityFlowPackageAuthority::load( $path );
				$this->fail( 'Invalid package authority unexpectedly passed: ' . $label );
			} catch ( RuntimeException $exception ) {
				$this->assertNotSame( '', $exception->getMessage(), $label );
			} finally {
				unlink( $path );
			}
		}
	}

	public function test_active_workflows_resolve_gravity_flow_package_from_canonical_authority(): void {
		$root      = dirname( __DIR__ );
		$consumers = array(
			'.github/workflows/g006-gravityforms-runtime.yml' => 'G006_GF_FLOW',
			'.github/workflows/wu008-real-integration.yml'   => 'WU008_FLOW',
		);

		foreach ( $consumers as $relative_path => $prefix ) {
			$content = file_get_contents( $root . '/' . $relative_path );
			$this->assertNotFalse( $content );
			$this->assertStringContainsString(
				'php tools/compatibility/gravityflow-package.php env ' . $prefix . ' >> "$GITHUB_ENV"',
				$content,
				$relative_path
			);
		}

		$workflow_paths = array_merge(
			glob( $root . '/.github/workflows/*.yml' ) ?: array(),
			glob( $root . '/.github/workflows/*.yaml' ) ?: array()
		);
		$this->assertNotEmpty( $workflow_paths );

		foreach ( $workflow_paths as $workflow_path ) {
			$content = file_get_contents( $workflow_path );
			$this->assertNotFalse( $content );
			$relative_path = str_replace( $root . '/', '', $workflow_path );

			$this->assertStringNotContainsString( self::FILE_ID, $content, $relative_path );
			$this->assertStringNotContainsString( self::FILENAME, $content, $relative_path );
			$this->assertStringNotContainsString( self::SHA256, $content, $relative_path );
			$this->assertSame(
				0,
				preg_match( '/^\s*[A-Z0-9_]*FLOW_(?:FILE_ID|FILE|SIZE|SHA256|VERSION|MAIN_FILE):\s*/m', $content ),
				$relative_path . ' must not own an active Gravity Flow package authority.'
			);
		}

		$g006 = file_get_contents( $root . '/.github/workflows/g006-gravityforms-runtime.yml' );
		$this->assertStringContainsString( "- 'tools/compatibility/**'", $g006 );
	}
}
