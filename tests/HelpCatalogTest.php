<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
require_once dirname( __DIR__ ) . '/admin/class-pgr-help-catalog.php';

final class HelpCatalogTest extends TestCase {

	public function test_required_product_topics_exist_in_both_languages() {
		$required = array( 'quick-start', 'module-manager', 'scanner-profiles', 'settings', 'system-status', 'troubleshooting', 'data-behavior', 'scope' );
		foreach ( $required as $topic_id ) {
			foreach ( array( 'fa', 'en' ) as $lang ) {
				$topic = PGR_Help_Catalog::get( $topic_id, $lang );
				$this->assertNotNull( $topic, $topic_id . ':' . $lang );
				$this->assertGreaterThan( 80, strlen( strip_tags( $topic['body'] ) ) );
			}
		}
	}

	public function test_every_module_help_topic_exists_in_both_languages() {
		foreach ( PGR_Module_Registry::all() as $module ) {
			foreach ( array( 'fa', 'en' ) as $lang ) {
				$topic = PGR_Help_Catalog::get( $module['help_topic'], $lang );
				$this->assertNotNull( $topic, $module['id'] . ':' . $lang );
				$this->assertNotSame( '', trim( strip_tags( $topic['body'] ) ) );
			}
		}
	}
}
