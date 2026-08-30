<?php

use PHPUnit\Framework\TestCase;

final class RuntimeIntegrityTest extends TestCase {

	public function test_obsolete_parallel_architectures_and_forbidden_responsibilities_are_absent() {
		$root = dirname( __DIR__ );
		$this->assertDirectoryDoesNotExist( $root . '/src' );
		$this->assertDirectoryDoesNotExist( $root . '/assets/fonts' );
		$this->assertFileDoesNotExist( $root . '/persian-gravityforms-refactor.php' );

		$forbidden = array(
			'GFPersian_',
			'mellicart',
			'ir_national_id',
			'load_textdomain_mofile',
			'registration_counter',
			'Needs Review',
			'Officer',
		);

		$paths = array(
			$root . '/persian-gravityforms.php',
			$root . '/includes',
			$root . '/admin',
		);

		foreach ( $paths as $path ) {
			$files = is_dir( $path )
				? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) )
				: array( new SplFileInfo( $path ) );

			foreach ( $files as $file ) {
				if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
					continue;
				}
				$content = file_get_contents( $file->getPathname() );
				foreach ( $forbidden as $needle ) {
					$this->assertStringNotContainsString( $needle, $content, $file->getPathname() . ' contains forbidden legacy/project-specific code.' );
				}
			}
		}
	}

	public function test_only_own_text_domain_is_used_by_production_php() {
		$root      = dirname( __DIR__ );
		$iterator  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) );
		$php_files = array( $root . '/persian-gravityforms.php', $root . '/admin/class-pgr-admin.php' );

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$php_files[] = $file->getPathname();
			}
		}

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );
			$this->assertStringNotContainsString( "'gravityforms'", $content );
			$this->assertStringNotContainsString( 'load_textdomain_mofile', $content );
		}
	}
}
