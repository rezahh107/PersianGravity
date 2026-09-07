<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
require_once dirname( __DIR__ ) . '/admin/class-pgr-help-catalog.php';

final class RepositoryConsistencyTest extends TestCase {

	public function test_active_version_declarations_are_synchronized() {
		$root    = dirname( __DIR__ );
		$plugin  = file_get_contents( $root . '/persian-gravityforms.php' );
		$readme  = file_get_contents( $root . '/readme.txt' );
		$github  = file_get_contents( $root . '/README.md' );
		$agents  = file_get_contents( $root . '/AGENTS.md' );

		$this->assertMatchesRegularExpression( '/^ \* Version: 4\.2\.0$/m', $plugin );
		$this->assertStringContainsString( "define( 'PGR_VERSION', '4.2.0' );", $plugin );
		$this->assertMatchesRegularExpression( '/^Stable tag: 4\.2\.0$/m', $readme );
		$this->assertStringContainsString( '- Plugin version: `4.2.0`', $github );
		$this->assertStringContainsString( '- Version: `4.2.0`', $agents );
	}

	public function test_bounded_module_catalog_and_help_are_complete() {
		$modules = PGR_Module_Registry::all();
		$this->assertSame(
			array( 'national_id', 'jalali_date', 'iranian_address', 'digit_normalization', 'iranian_currency', 'structured_scanner' ),
			array_keys( $modules )
		);

		foreach ( $modules as $module ) {
			foreach ( array( 'label_fa', 'label_en', 'description_fa', 'description_en', 'help_topic' ) as $key ) {
				$this->assertArrayHasKey( $key, $module );
				$this->assertNotSame( '', trim( (string) $module[ $key ] ) );
			}
			foreach ( array( 'fa', 'en' ) as $lang ) {
				$topic = PGR_Help_Catalog::get( $module['help_topic'], $lang );
				$this->assertNotNull( $topic );
				$this->assertGreaterThan( 80, strlen( strip_tags( $topic['body'] ) ) );
			}
		}
	}

	public function test_persian_translation_assets_are_shipped() {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/languages/persian-gravityforms.pot' );
		$this->assertFileExists( $root . '/languages/persian-gravityforms-fa_IR.po' );
		$this->assertFileExists( $root . '/languages/persian-gravityforms-fa_IR.mo' );
		$this->assertGreaterThan( 20, filesize( $root . '/languages/persian-gravityforms-fa_IR.mo' ) );
	}

	public function test_persian_translation_source_has_clean_bytes() {
		$root = dirname( __DIR__ );
		$po   = file_get_contents( $root . '/languages/persian-gravityforms-fa_IR.po' );

		$this->assertTrue( $this->has_clean_persian_gettext_bytes( $po ) );
		$this->assertTrue( $this->has_clean_persian_gettext_bytes( "msgid \"Hello\"\nmsgstr \"سلام\"\n" ) );

		$this->assertFalse( $this->has_clean_persian_gettext_bytes( "msgid \"Bad\"\nmsgstr \"\xC3\x28\"\n" ) );
		$this->assertFalse( $this->has_clean_persian_gettext_bytes( "msgid \"Bad\"\nmsgstr \"سلام\x01\"\n" ) );

		foreach ( array( 'Ø', 'Ù', 'Û', 'â€' ) as $signature ) {
			$this->assertFalse( $this->has_clean_persian_gettext_bytes( "msgid \"Bad\"\nmsgstr \"{$signature}\"\n" ) );
		}
	}

	public function test_module_admin_write_surface_is_post_nonce_capability_and_allowlist_based() {
		$admin = file_get_contents( dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php' );
		$this->assertStringContainsString( "add_action( 'admin_post_pgr_module_toggle'", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'pgr_module_toggle' )", $admin );
		$this->assertStringContainsString( 'current_user_can( PGR_Admin::CAPABILITY )', $admin );
		$this->assertStringContainsString( 'PGR_Module_Registry::exists( $module_id )', $admin );
		$this->assertStringContainsString( "'POST' !== strtoupper", $admin );
	}

	public function test_text_domain_remains_plugin_owned_only() {
		$root = dirname( __DIR__ );
		$source = file_get_contents( $root . '/persian-gravityforms.php' )
			. file_get_contents( $root . '/admin/class-pgr-product-admin.php' )
			. file_get_contents( $root . '/includes/class-pgr-core.php' );
		$this->assertStringContainsString( 'persian-gravityforms', $source );
		$this->assertStringNotContainsString( 'load_textdomain_mofile', $source );
	}

	/**
	 * Validate the byte-level invariants required for the shipped Persian gettext source.
	 *
	 * This deliberately does not parse PO syntax; GNU gettext remains the format authority.
	 *
	 * @param string $content Candidate gettext source bytes.
	 * @return bool
	 */
	private function has_clean_persian_gettext_bytes( $content ) {
		if ( 1 !== preg_match( '//u', $content ) ) {
			return false;
		}

		if ( 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content ) ) {
			return false;
		}

		foreach ( array( 'Ø', 'Ù', 'Û', 'â€' ) as $signature ) {
			if ( false !== strpos( $content, $signature ) ) {
				return false;
			}
		}

		return true;
	}
}
