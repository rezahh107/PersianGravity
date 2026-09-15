<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class GravityFormsFrontendContentAdmissionTest extends TestCase {
	private const SHORTCODE = 'gravityforms::frontend_runtime::shortcode:gravityform';
	private const SURFACE_COUNTS = array(
		'gravityforms::frontend_runtime::shortcode:gravityform' => 41,
		'gravityforms::frontend_runtime::block:gravityforms/form' => 69,
		'gravityforms::admin_builder::admin_page:gf_edit_forms' => 905,
		'gravityforms::entry_management_runtime::admin_page:gf_entries' => 176,
		'gravityforms::settings_integrations::admin_page:gf_settings' => 514,
		'gravityforms::developer_diagnostics::admin_page:gf_system_status' => 121,
	);

	public function test_frontend_shortcode_record_is_preserved_exactly(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$record   = $this->recordForSurface( $content['gravityforms']['admissions'], self::SHORTCODE );
		$index    = pgr_admission_json( $root . '/' . $record['surface_evidence_index_path'] );
		$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityforms', 'fa_IR' );
		$index_ids = array_column( $index['entries'], 0 );
		sort( $index_ids, SORT_STRING );

		$this->assertSame( 41, $record['admitted_message_count'] );
		$this->assertSame( '827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb', $record['admitted_keyset_sha256'] );
		$this->assertSame( '5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177', $record['admitted_surface_path_index_sha256'] );
		$this->assertSame( '1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6', $record['admitted_translation_content_sha256'] );
		$this->assertSame( '0e9fb3c7a44f3bc2ae14d25f694a2301be59d7e75fc1d1f0740fb3be94283d22', $record['surface_evidence_index_sha256'] );
		$this->assertSame( 'ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a', $record['provider_source_sha256'] );
		$this->assertSame( $index_ids, $provider['ids'] );
	}

	public function test_all_six_gravityforms_records_form_exact_authorized_union(): void {
		$root     = dirname( __DIR__ );
		$products = require $root . '/includes/localization/products.php';
		$source   = pgr_validate_admission( $root );
		$content  = pgr_validate_content_admission( $root, $source, $products );
		$records  = array_values( array_filter( $content['gravityforms']['admissions'], static fn( $record ) => isset( $record['surface_id'] ) ) );
		$aggregate = $content['gravityforms']['aggregate'];
		$counts   = array();
		$occurrences = 0;
		$membership = array();

		foreach ( $records as $record ) {
			$counts[ $record['surface_id'] ] = $record['admitted_message_count'];
			$provider = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], 'gravityforms', 'fa_IR' );
			$occurrences += count( $provider['ids'] );
			foreach ( $provider['ids'] as $id ) {
				$membership[ $id ] = ( $membership[ $id ] ?? 0 ) + 1;
			}
		}
		ksort( $counts, SORT_STRING );
		$expected = self::SURFACE_COUNTS;
		ksort( $expected, SORT_STRING );
		$multi = array_filter( $membership, static fn( $count ) => 1 < $count );

		$this->assertCount( 6, $records );
		$this->assertSame( $expected, $counts );
		$this->assertSame( 1826, $occurrences );
		$this->assertCount( 1759, $membership );
		$this->assertCount( 55, $multi );
		$this->assertSame( 67, $occurrences - count( $membership ) );
		$this->assertSame( 4207, $aggregate['admitted_message_count'] );
		$remainder = array_values( array_filter( $content['gravityforms']['admissions'], static fn( $record ) => 'PRODUCT_REMAINDER' === ( $record['authority_scope'] ?? null ) ) );
		$this->assertCount( 1, $remainder );
		$this->assertSame( 'e15f8e80cc711ea7b862be66d3a8a5827f85e0312caae391fcdecbf1a34fdec7', $remainder[0]['preexisting_accepted_keyset_sha256'] );
		$this->assertSame( 'a0b631dfcb88eb497be26086799a8816b7f85f99fd42c62afe6488ee508f9c27', $remainder[0]['preexisting_accepted_translation_content_sha256'] );
		$this->assertSame( 2448, $remainder[0]['admitted_message_count'] );
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
	}

	public function test_former_unclassified_control_is_admitted_but_stale_pot_only_key_is_excluded(): void {
		$root     = dirname( __DIR__ );
		$provider = pgr_content_load_sparse_po(
			$root . '/languages/providers/gravityforms/source/fa_IR.po',
			'gravityforms',
			'fa_IR'
		);

		$this->assertContains( hash( 'sha256', "\x1fValidation Message Placement\x1f" ), $provider['ids'] );
		$this->assertContains( hash( 'sha256', "\x1fThe URL is not valid.\x1f" ), $provider['ids'] );
		$this->assertNotContains( '2b64a903d2065499fa0248c6199db150bb7d1a43025d2908e8a66e4af9d4ab92', $provider['ids'] );
		$this->assertCount( 4207, $provider['ids'] );
	}

	private function recordForSurface( array $records, string $surface ): array {
		foreach ( $records as $record ) {
			if ( $surface === $record['surface_id'] ) {
				return $record;
			}
		}
		throw new RuntimeException( 'Missing Gravity Forms admission: ' . $surface );
	}
}
