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

		foreach ( $this->production_php_files( $root ) as $file ) {
			$content = file_get_contents( $file );
			foreach ( $forbidden as $needle ) {
				$this->assertStringNotContainsString( $needle, $content, $file . ' contains forbidden legacy/project-specific code.' );
			}
		}
	}

	public function test_legacy_jalali_boundary_cannot_reappear() {
		$root = dirname( __DIR__ );
		$this->assertFileDoesNotExist( $root . '/assets/js/jalali-datepicker.js' );
		$this->assertFileDoesNotExist( $root . '/assets/js/jalali-datepicker.min.js' );
		$this->assertFileExists( $root . '/includes/fields/class-gf-field-jalali-date.php' );

		foreach ( $this->production_php_files( $root ) as $file ) {
			$content = file_get_contents( $file );
			$this->assertStringNotContainsString( 'pgrJalali', $content, $file . ' reintroduces native Date Jalali mode.' );
			$this->assertStringNotContainsString( 'jquery-ui-datepicker', $content, $file . ' reintroduces shared datepicker authority.' );
			$this->assertStringNotContainsString( 'wp_deregister_script', $content, $file . ' deregisters a shared WordPress script handle.' );
		}
	}

	public function test_foreign_domains_are_declared_only_in_the_approved_manifest() {
		$root = dirname( __DIR__ );
		foreach ( $this->production_php_files( $root ) as $file ) {
			$content = file_get_contents( $file );
			if ( $file !== $root . '/includes/localization/products.php' ) {
				foreach ( array( 'gravityforms', 'gravityflow', 'gk-gravityview' ) as $domain ) {
					$this->assertStringNotContainsString( "'" . $domain . "'", $content );
				}
			}
			$this->assertStringNotContainsString( 'load_textdomain_mofile', $content );
		}
	}

	private function production_php_files( $root ) {
		$php_files = array(
			$root . '/persian-gravityforms.php',
			$root . '/uninstall.php',
		);

		foreach ( array( 'includes', 'admin' ) as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === $file->getExtension() ) {
					$php_files[] = $file->getPathname();
				}
			}
		}

		return $php_files;
	}
}
