<?php
/**
 * G-012 bilingual worked-example regression coverage.
 *
 * @package PersianGravityForms
 */
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-address.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-persian-date.php';
require_once dirname( __DIR__ ) . '/admin/class-pgr-help-catalog.php';

final class G012WorkedHelpTest extends TestCase {
	public function test_all_seven_modules_have_complete_bilingual_examples() {
		$examples = PGR_Help_Catalog::examples();
		$this->assertCount( 7, $examples );
		foreach ( PGR_Module_Registry::all() as $module ) {
			foreach ( array( 'fa', 'en' ) as $lang ) {
				$example = $examples[ $module['help_topic'] ][ $lang ];
				foreach ( array( 'scenario', 'prerequisites', 'expected', 'verification', 'notes' ) as $key ) {
					$this->assertNotEmpty( $example[ $key ] );
				}
				$this->assertGreaterThanOrEqual( 3, count( $example['steps'] ) );
				$this->assertGreaterThanOrEqual( 2, count( $example['samples'] ) );
			}
		}
	}


	public function test_plain_text_description_has_no_literal_markdown_formatting() {
		foreach ( PGR_Help_Catalog::examples() as $topic_id => $translations ) {
			foreach ( array( 'fa', 'en' ) as $lang ) {
				$example = $translations[ $lang ];
				$prose   = array_merge(
					array( $example['scenario'], $example['prerequisites'], $example['expected'], $example['verification'], $example['notes'] ),
					$example['steps']
				);
				foreach ( $prose as $text ) {
					$context = $topic_id . '/' . $lang;
					$this->assertStringNotContainsString( '`', $text, $context );
					$this->assertDoesNotMatchRegularExpression( '/(?:^|\\s)\\*[^*\\s][^*\\r\\n]*\\*/u', $text, $context );
				}
			}
		}
	}

	public function test_source_validated_id_and_date_fixtures() {
		$this->assertTrue( PGR_Utils::is_valid_iran_national_id( '۰۰۱-۳۵۴۶۲۴-۴' ) );
		$this->assertFalse( PGR_Utils::is_valid_iran_national_id( '۰۰۱-۳۵۴۶۲۴-۵' ) );
		$this->assertSame( '0013546244', PGR_Utils::normalize_national_id( '۰۰۱-۳۵۴۶۲۴-۴' ) );
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '۱۴۰۴/۰۲/۳۱', 'ymd_slash' ) );
		$this->assertNull( PGR_Persian_Date::canonicalize( '1404/07/31', 'ymd_slash' ) );
	}

	public function test_iranian_address_example_uses_the_real_province_value() {
		$this->assertContains( 'فارس', PGR_Address::provinces() );
		$this->assertNotContains( 'Fars', PGR_Address::provinces() );
		foreach ( array( 'fa', 'en' ) as $lang ) {
			$examples = PGR_Help_Catalog::examples()['iranian-address'][ $lang ]['samples'];
			$this->assertStringContainsString( 'Province: فارس', $examples[0]['value'] );
		}
	}

	public function test_full_explicit_token_inventory_in_both_languages() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gf-explicit-jalali-merge-tags.php' );
		$this->assertStringContainsString( '(created|updated|payment|today)(?:_(date|time))?', $source );
		$this->assertStringContainsString( 'field_([1-9][0-9]*)', $source );
		foreach ( array( 'fa', 'en' ) as $lang ) {
			$text = implode( "\n", array_column( PGR_Help_Catalog::examples()['jalali-presentation'][ $lang ]['samples'], 'value' ) );
			foreach ( array( 'created', 'updated', 'payment', 'today' ) as $family ) {
				foreach ( array( '', '_date', '_time' ) as $part ) {
					$this->assertStringContainsString( '{pgr_jdate_' . $family . $part . '}', $text );
				}
			}
			$this->assertStringContainsString( '{pgr_jdate_field_2}', $text );
		}
	}
}
