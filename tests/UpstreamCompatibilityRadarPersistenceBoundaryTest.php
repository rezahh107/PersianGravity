<?php

use PHPUnit\Framework\TestCase;

final class UpstreamCompatibilityRadarPersistenceBoundaryTest extends TestCase {

	private string $root;
	private string $guard;
	private array $temporary_directories = array();

	protected function setUp(): void {
		$this->root  = dirname( __DIR__ );
		$this->guard = $this->root . '/.github/scripts/upstream-radar-observation-boundary.sh';
		$this->assertFileExists( $this->guard );
	}

	protected function tearDown(): void {
		foreach ( $this->temporary_directories as $directory ) {
			$this->removeDirectory( $directory );
		}
		$this->temporary_directories = array();
	}

	public function test_committed_radar_tool_modification_is_rejected(): void {
		list( $repository, $base ) = $this->makeRepository();
		file_put_contents( $repository . '/tools/compatibility/upstream-radar.php', "<?php\n// mutated on automation branch\n" );
		$this->commitAll( $repository, 'mutate radar executable' );

		$result = $this->runGuard( $repository, array( 'committed', $base, 'HEAD' ) );

		$this->assertNotSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'committed non-observation delta: tools/compatibility/upstream-radar.php', $result['stderr'] );
	}

	public function test_committed_non_observation_change_is_rejected_independent_of_radar_path(): void {
		list( $repository, $base ) = $this->makeRepository();
		file_put_contents( $repository . '/README.md', "changed outside observation namespace\n" );
		$this->commitAll( $repository, 'mutate unrelated repository file' );

		$result = $this->runGuard( $repository, array( 'committed', $base, 'HEAD' ) );

		$this->assertNotSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'committed non-observation delta: README.md', $result['stderr'] );
	}

	public function test_committed_valid_observation_json_blobs_are_admitted(): void {
		list( $repository, $base ) = $this->makeRepository();
		$this->writeObservation( $repository, 'gravityforms/3.1.2.json', array( 'schema_version' => 1 ) );
		$this->writeObservation( $repository, 'gravityflow/3.1.1.1.json', array( 'schema_version' => 1 ) );
		$this->commitAll( $repository, 'add observation records' );

		$result = $this->runGuard( $repository, array( 'committed', $base, 'HEAD' ) );

		$this->assertSame( 0, $result['exit_code'], $result['stderr'] );
	}

	public function test_committed_allowed_path_symlink_is_rejected(): void {
		list( $repository, $base ) = $this->makeRepository();
		$directory = $repository . '/tools/compatibility/upstream-observations/gravityforms';
		mkdir( $directory, 0777, true );
		$this->assertTrue( symlink( '../../../../README.md', $directory . '/3.1.2.json' ) );
		$this->commitAll( $repository, 'add symlink shaped like observation' );

		$result = $this->runGuard( $repository, array( 'committed', $base, 'HEAD' ) );

		$this->assertNotSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'unsupported committed observation Git mode 120000', $result['stderr'] );
	}

	public function test_committed_malformed_observation_json_is_rejected(): void {
		list( $repository, $base ) = $this->makeRepository();
		$path = $repository . '/tools/compatibility/upstream-observations/gravityforms/3.1.2.json';
		mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, "{not-json}\n" );
		$this->commitAll( $repository, 'add malformed observation' );

		$result = $this->runGuard( $repository, array( 'committed', $base, 'HEAD' ) );

		$this->assertNotSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'malformed committed observation JSON', $result['stderr'] );
	}

	public function test_post_execution_working_tree_guard_rejects_new_out_of_scope_file(): void {
		list( $repository ) = $this->makeRepository();
		file_put_contents( $repository . '/generated-outside-observations.txt', "unexpected\n" );

		$result = $this->runGuard( $repository, array( 'working-tree' ) );

		$this->assertNotSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'non-observation automated mutation: generated-outside-observations.txt', $result['stderr'] );
	}

	public function test_workflow_orders_committed_boundary_after_branch_prepare_and_before_scanner(): void {
		$workflow = (string) file_get_contents( $this->root . '/.github/workflows/upstream-compatibility-radar.yml' );
		$offset   = strpos( $workflow, "\n  persist-observations:" );
		$this->assertNotFalse( $offset );
		$persist = substr( $workflow, $offset );

		$pin     = strpos( $persist, '- name: Pin canonical observation boundary guard' );
		$prepare = strpos( $persist, '- name: Prepare bounded observation branch' );
		$guard   = strpos( $persist, '- name: Enforce committed observation-only branch boundary' );
		$scanner = strpos( $persist, 'php tools/compatibility/upstream-radar.php scan --output="$evidence_dir" --persist-new' );
		$post    = strpos( $persist, 'bash "$RADAR_BOUNDARY_GUARD" working-tree' );

		foreach ( array( $pin, $prepare, $guard, $scanner, $post ) as $position ) {
			$this->assertNotFalse( $position );
		}
		$this->assertTrue( $pin < $prepare, 'Trusted guard must be copied while canonical main is still checked out.' );
		$this->assertTrue( $prepare < $guard, 'Committed-tree boundary must run after automation-branch preparation.' );
		$this->assertTrue( $guard < $scanner, 'Committed-tree boundary must run before repository radar tooling.' );
		$this->assertTrue( $scanner < $post, 'Post-execution mutation guard must remain after scanner execution.' );
	}

	private function makeRepository(): array {
		$repository = sys_get_temp_dir() . '/pgr-radar-boundary-' . bin2hex( random_bytes( 6 ) );
		mkdir( $repository, 0777, true );
		$this->temporary_directories[] = $repository;

		mkdir( $repository . '/tools/compatibility', 0777, true );
		file_put_contents( $repository . '/README.md', "baseline\n" );
		file_put_contents( $repository . '/tools/compatibility/upstream-radar.php', "<?php\n// canonical baseline\n" );

		$this->assertCommandSuccess( $repository, array( 'git', 'init', '--quiet' ) );
		$this->assertCommandSuccess( $repository, array( 'git', 'config', 'user.name', 'Radar Boundary Test' ) );
		$this->assertCommandSuccess( $repository, array( 'git', 'config', 'user.email', 'radar-boundary@example.invalid' ) );
		$this->commitAll( $repository, 'baseline' );

		$base = trim( $this->assertCommandSuccess( $repository, array( 'git', 'rev-parse', 'HEAD' ) )['stdout'] );
		return array( $repository, $base );
	}

	private function writeObservation( string $repository, string $relative, array $document ): void {
		$path = $repository . '/tools/compatibility/upstream-observations/' . $relative;
		mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
	}

	private function commitAll( string $repository, string $message ): void {
		$this->assertCommandSuccess( $repository, array( 'git', 'add', '--all' ) );
		$this->assertCommandSuccess( $repository, array( 'git', 'commit', '--quiet', '-m', $message ) );
	}

	private function runGuard( string $repository, array $arguments ): array {
		return $this->runCommand( array_merge( array( 'bash', $this->guard ), $arguments ), $repository );
	}

	private function assertCommandSuccess( string $directory, array $command ): array {
		$result = $this->runCommand( $command, $directory );
		$this->assertSame( 0, $result['exit_code'], $result['stderr'] );
		return $result;
	}

	private function runCommand( array $command, string $directory ): array {
		$process = proc_open(
			$command,
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			$directory
		);
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );

		return array(
			'exit_code' => $exit_code,
			'stdout'    => (string) $stdout,
			'stderr'    => (string) $stderr,
		);
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
			if ( $item->isLink() || $item->isFile() ) {
				unlink( $item->getPathname() );
			} else {
				rmdir( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}
}
