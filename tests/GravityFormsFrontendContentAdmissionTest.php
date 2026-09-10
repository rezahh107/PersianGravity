<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFormsFrontendContentAdmissionTest extends TestCase {
	private const SURFACE = 'gravityforms::frontend_runtime::shortcode:gravityform';

	public function test_frontend_shortcode_record_is_exactly_bound_to_source_and_reviewed_baseline(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface( $content['gravityforms']['admissions'] );
		$index    = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityforms', 'fa_IR' );
		$index_ids = array_column( $index['entries'], 0 );
		sort( $index_ids, SORT_STRING );

		$this->assertSame( '2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1', $record['reviewed_source_po_sha256'] );
		$this->assertSame( 4207, $record['reviewed_source_message_count'] );
		$this->assertSame( array( 'form_display.php' ), $index['source_path_rules'] );
		$this->assertSame( array( 'form_display.php' ), $index['paths'] );
		$this->assertCount( 41, $index['entries'] );
		$this->assertSame( 41, $record['admitted_message_count'] );
		$this->assertSame( '827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb', $record['admitted_keyset_sha256'] );
		$this->assertSame( '5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177', $record['admitted_surface_path_index_sha256'] );
		$this->assertSame( '1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6', $record['admitted_translation_content_sha256'] );
		$this->assertSame( $record['surface_evidence_index_sha256'], hash_file( 'sha256', $root . '/' . $record['surface_evidence_index_path'] ) );
		$this->assertSame( $record['provider_source_sha256'], hash_file( 'sha256', $root . '/' . $record['provider_source_path'] ) );
		$this->assertSame( $index_ids, $provider['ids'] );
	}

	public function test_gravityforms_aggregate_is_exactly_the_first_bounded_record_and_js_stays_empty(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$aggregate = $content['gravityforms']['aggregate'];

		$this->assertCount( 1, $content['gravityforms']['admissions'] );
		$this->assertSame( 41, $aggregate['admitted_message_count'] );
		$this->assertSame( '827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb', $aggregate['admitted_keyset_sha256'] );
		$this->assertSame( '1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6', $aggregate['admitted_translation_content_sha256'] );
		$this->assertSame( array(), $products['gravityforms']['scripts'] );
		$this->assertSame( 0, $aggregate['native_js_handles_activated'] );
		$this->assertSame( 0, $aggregate['js_translation_json_generated'] );
		$this->assertSame( array(), glob( $root . '/languages/providers/gravityforms/gravityforms-fa_IR-*.json' ) ?: array() );
	}

	public function test_locked_pr15_semantic_corrections_are_exact(): void {
		$root     = dirname( __DIR__ );
		$catalog  = ( new Gettext\Loader\StrictPoLoader() )->loadFile(
			$root . '/languages/providers/gravityforms/source/records/frontend-shortcode-fa_IR.po'
		);
		$expected = array(
			'Sorry. This form is no longer accepting new submissions.' => 'متأسفانه، این فرم دیگر ارسال‌های جدید را نمی‌پذیرد.',
			'Your form was not submitted. Please try again in a few minutes.' => 'فرم شما ارسال نشد. لطفاً چند دقیقهٔ دیگر دوباره تلاش کنید.',
			'This field requires a unique entry and the values you entered have already been used.' => 'این فیلد باید مقدار یکتا داشته باشد و مقادیری که وارد کرده‌اید قبلاً استفاده شده‌اند.',
			'Reason: %s' => 'دلیل: %s',
			"This field requires a unique entry and '%s' has already been used" => "این فیلد باید مقدار یکتا داشته باشد و '%s' قبلاً استفاده شده است.",
			'Save and Continue link used is expired or invalid.' => 'پیوند «ذخیره و ادامه» استفاده‌شده منقضی یا نامعتبر است.',
			'Spam Filter' => 'فیلتر هرزنامه',
		);
		$actual = array();

		foreach ( $catalog as $entry ) {
			if ( isset( $expected[ $entry->getOriginal() ] ) ) {
				$actual[ $entry->getOriginal() ] = $entry->getTranslation();
			}
		}

		$this->assertSame( $expected, $actual );
		$this->assertSame( 41, iterator_count( $catalog ) );
		$this->assertStringContainsString( '%s', $actual['Reason: %s'] );
		$this->assertStringContainsString( '%s', $actual["This field requires a unique entry and '%s' has already been used"] );
	}

	public function test_non_admitted_current_identity_is_absent_so_generic_upstream_fallback_remains_available(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface( $content['gravityforms']['admissions'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityforms', 'fa_IR' );

		$submit_id = hash( 'sha256', "\x1fSubmit\x1f" );
		$form_id   = hash( 'sha256', "\x1fForm\x1f" );

		$this->assertContains( $submit_id, $provider['ids'] );
		$this->assertNotContains( $form_id, $provider['ids'] );
		$this->assertSame( 41, count( $provider['ids'] ) );
	}

	private function recordForSurface( array $records ): array {
		foreach ( $records as $record ) {
			if ( self::SURFACE === $record['surface_id'] ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing Gravity Forms frontend shortcode admission' );
	}
}
