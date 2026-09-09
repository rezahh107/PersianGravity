<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class ContentAdmissionGeneralizationTest extends TestCase {
	private array $roots = array();

	protected function tearDown(): void {
		foreach ( $this->roots as $root ) {
			$this->removeTree( $root );
		}
		$this->roots = array();
	}

	public function test_accepts_multiple_records_and_groups_multiple_products_without_product_specific_branches(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.2.3', 'alpha-one', 'src/alpha/one/', array( 'Alpha one' => 'آلفا یک' ) ),
				$this->spec( 'alpha', 'alpha-domain', '1.2.3', 'alpha-two', 'src/alpha/two/', array( 'Alpha two' => 'آلفا دو' ) ),
				$this->spec( 'beta', 'beta-domain', '9.8.7', 'beta-one', 'src/beta/one/', array( 'Beta one' => 'بتا یک' ) ),
			)
		);

		$result = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );

		$this->assertSame( array( 'alpha', 'beta' ), array_keys( $result ) );
		$this->assertCount( 2, $result['alpha']['admissions'] );
		$this->assertSame( 2, $result['alpha']['aggregate']['admitted_message_count'] );
		$this->assertSame( 1, $result['beta']['aggregate']['admitted_message_count'] );
	}

	public function test_shared_identity_with_identical_translation_is_deduplicated(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'Shared' => 'مشترک', 'Only A' => 'فقط آ' ) ),
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-b', 'src/b/', array( 'Shared' => 'مشترک', 'Only B' => 'فقط ب' ) ),
			)
		);

		$result = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );

		$this->assertSame( 3, $result['alpha']['aggregate']['admitted_message_count'] );
	}

	public function test_shared_identity_with_conflicting_translation_fails_closed(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'Shared' => 'ترجمه یک' ) ),
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-b', 'src/b/', array( 'Shared' => 'ترجمه دو' ) ),
			)
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Conflicting translation content for shared admitted identity' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_duplicate_record_identity_tuple_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'][] = $manifest['admissions'][0];
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Duplicate content-admission record identity tuple' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_out_of_surface_evidence_path_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$record   = &$manifest['admissions'][0];
		$index    = $this->readJson( $fixture['root'] . '/' . $record['surface_evidence_index_path'] );
		$index['paths'][0] = 'outside/surface.php';
		$this->writeJson( $fixture['root'] . '/' . $record['surface_evidence_index_path'], $index );
		$record['surface_evidence_index_sha256'] = hash_file( 'sha256', $fixture['root'] . '/' . $record['surface_evidence_index_path'] );
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Out-of-surface source evidence path' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_unknown_surface_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'][0]['surface_id'] = 'alpha::runtime::unknown';
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Unknown/unowned content-admission surface' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_product_domain_mismatch_against_manifest_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'][0]['domain'] = 'missing-domain';
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'product/domain/version manifest mismatch' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_version_mismatch_against_source_admission_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$fixture['source']['alpha']['target_version'] = '1.0.1';

		$this->expectException( RuntimeException::class );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_package_hash_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'source_package_sha256' );
	}

	public function test_pot_hash_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'vendor_pot_sha256' );
	}

	public function test_reviewed_baseline_hash_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'reviewed_source_po_sha256' );
	}

	public function test_reviewed_baseline_count_drift_fails_closed(): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		++$manifest['admissions'][0]['reviewed_source_message_count'];
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'not bound to validated source admission' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_evidence_index_file_hash_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'surface_evidence_index_sha256' );
	}

	public function test_evidence_keyset_fingerprint_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'admitted_keyset_sha256' );
	}

	public function test_evidence_path_fingerprint_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'admitted_surface_path_index_sha256' );
	}

	public function test_translation_content_fingerprint_drift_fails_closed(): void {
		$this->assertManifestFieldDriftFails( 'admitted_translation_content_sha256' );
	}

	public function test_sparse_aggregate_provider_with_extra_identity_fails_closed(): void {
		$fixture = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$this->writePo(
			$fixture['root'] . '/languages/providers/alpha/source/fa_IR.po',
			'alpha-domain',
			array( 'One' => 'یک', 'Extra' => 'اضافی' )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Aggregate sparse provider source is not the exact admitted union' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_sparse_aggregate_provider_with_missing_identity_fails_closed(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک', 'Two' => 'دو' ) ),
			)
		);
		$this->writePo( $fixture['root'] . '/languages/providers/alpha/source/fa_IR.po', 'alpha-domain', array( 'One' => 'یک' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Aggregate sparse provider source is not the exact admitted union' );
		pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
	}

	public function test_record_order_does_not_change_aggregate_fingerprints(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ),
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-b', 'src/b/', array( 'Two' => 'دو' ) ),
			)
		);
		$first    = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'] = array_reverse( $manifest['admissions'] );
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );
		$second = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );

		$this->assertSame( $first['alpha']['aggregate']['admitted_keyset_sha256'], $second['alpha']['aggregate']['admitted_keyset_sha256'] );
		$this->assertSame( $first['alpha']['aggregate']['admitted_translation_content_sha256'], $second['alpha']['aggregate']['admitted_translation_content_sha256'] );
	}

	public function test_permitted_evidence_entry_order_does_not_change_aggregate_fingerprints(): void {
		$fixture = $this->fixture(
			array(
				$this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک', 'Two' => 'دو' ) ),
			)
		);
		$first    = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$record   = &$manifest['admissions'][0];
		$index    = $this->readJson( $fixture['root'] . '/' . $record['surface_evidence_index_path'] );
		$index['entries'] = array_reverse( $index['entries'] );
		$this->writeJson( $fixture['root'] . '/' . $record['surface_evidence_index_path'], $index );
		$record['surface_evidence_index_sha256'] = hash_file( 'sha256', $fixture['root'] . '/' . $record['surface_evidence_index_path'] );
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );
		$this->syncProvenance( $fixture['root'], $manifest, 'alpha', 'alpha-domain' );
		$second = pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );

		$this->assertSame( $first['alpha']['aggregate']['admitted_keyset_sha256'], $second['alpha']['aggregate']['admitted_keyset_sha256'] );
		$this->assertSame( $first['alpha']['aggregate']['admitted_translation_content_sha256'], $second['alpha']['aggregate']['admitted_translation_content_sha256'] );
	}

	public function test_valid_source_admission_with_zero_content_records_grants_no_content_authority(): void {
		$fixture = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'] = array();
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		$this->assertSame( array(), pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] ) );
	}

	private function assertManifestFieldDriftFails( string $field ): void {
		$fixture  = $this->fixture( array( $this->spec( 'alpha', 'alpha-domain', '1.0.0', 'surface-a', 'src/a/', array( 'One' => 'یک' ) ) ) );
		$manifest = $this->readJson( $fixture['root'] . '/tools/i18n/admission/content.json' );
		$manifest['admissions'][0][ $field ] = str_repeat( 'f', 64 ) === $manifest['admissions'][0][ $field ] ? str_repeat( 'e', 64 ) : str_repeat( 'f', 64 );
		$this->writeJson( $fixture['root'] . '/tools/i18n/admission/content.json', $manifest );

		try {
			pgr_validate_content_admission( $fixture['root'], $fixture['source'], $fixture['products'] );
			$this->fail( 'Expected content-admission drift to fail closed: ' . $field );
		} catch ( RuntimeException $exception ) {
			$this->assertNotSame( '', $exception->getMessage() );
		}
	}

	private function spec( string $product, string $domain, string $version, string $surface, string $path_rule, array $messages ): array {
		return array(
			'product'   => $product,
			'domain'    => $domain,
			'version'   => $version,
			'surface'   => $surface,
			'path_rule' => $path_rule,
			'messages'  => $messages,
		);
	}

	private function fixture( array $specs ): array {
		$root = sys_get_temp_dir() . '/pgr-content-v2-' . bin2hex( random_bytes( 8 ) );
		$this->roots[] = $root;
		mkdir( $root . '/tools/i18n/admission', 0777, true );

		$products       = array();
		$source         = array();
		$surfaces       = array();
		$records        = array();
		$productMessages = array();
		$productCounts   = array();
		foreach ( $specs as $position => $spec ) {
			$product = $spec['product'];
			$domain  = $spec['domain'];
			$version = $spec['version'];
			$surface = $product . '::runtime::' . $spec['surface'];
			$path    = $spec['path_rule'] . 'messages-' . $position . '.php';

			$products[ $domain ] = array(
				'product'        => $product,
				'target_version' => $version,
				'prefix'         => $product,
				'scripts'        => array(),
			);
			$productCounts[ $product ] = max( $productCounts[ $product ] ?? 0, 50 );
			$source[ $product ] = array(
				'target_version'          => $version,
				'package_sha256'          => hash( 'sha256', $product . ':package' ),
				'vendor_pot_sha256'       => hash( 'sha256', $product . ':pot' ),
				'canonical_message_count' => $productCounts[ $product ],
			);
			$surfaces[] = array(
				'surface_id'        => $surface,
				'product'           => $product,
				'source_path_rules' => array( $spec['path_rule'] ),
			);

			$ids              = array();
			$translationRows  = array();
			$entries          = array();
			foreach ( $spec['messages'] as $msgid => $translation ) {
				$id = hash( 'sha256', "\x1f" . $msgid . "\x1f" );
				$ids[] = $id;
				$translationRows[ $id ] = $id . "\x1f" . $translation;
				$entries[] = array( $id, array( 0 ) );
				if ( isset( $productMessages[ $product ][ $msgid ] ) && $productMessages[ $product ][ $msgid ] !== $translation ) {
					continue;
				}
				$productMessages[ $product ][ $msgid ] = $translation;
			}
			$index = array(
				'surface_id'        => $surface,
				'source_path_rules' => array( $spec['path_rule'] ),
				'paths'             => array( $path ),
				'entries'           => $entries,
			);
			$indexRelative = 'tools/i18n/admission/index-' . $position . '.json';
			$this->writeJson( $root . '/' . $indexRelative, $index );
			$providerRelative = 'tools/i18n/admission/provider-' . $position . '.po';
			$this->writePo( $root . '/' . $providerRelative, $domain, $spec['messages'] );

			$surfaceRows = array();
			foreach ( $ids as $id ) {
				$surfaceRows[] = $id . "\x1f" . $path;
			}
			$records[] = array(
				'product'                             => $product,
				'domain'                              => $domain,
				'locale'                              => 'fa_IR',
				'target_version'                      => $version,
				'content_state'                       => 'CONTENT_ADMITTED_PARTIAL',
				'reviewed_source_po_sha256'           => hash( 'sha256', $product . ':reviewed-baseline' ),
				'reviewed_source_message_count'       => $productCounts[ $product ],
				'source_package_sha256'               => $source[ $product ]['package_sha256'],
				'vendor_pot_sha256'                   => $source[ $product ]['vendor_pot_sha256'],
				'surface_id'                          => $surface,
				'admitted_message_count'              => count( $ids ),
				'admitted_keyset_sha256'              => pgr_content_hash_lines( $ids ),
				'admitted_surface_path_index_sha256'  => pgr_content_hash_lines( $surfaceRows ),
				'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $translationRows ) ),
				'surface_evidence_index_path'          => $indexRelative,
				'surface_evidence_index_sha256'        => hash_file( 'sha256', $root . '/' . $indexRelative ),
				'provider_source_path'                 => $providerRelative,
				'provider_source_sha256'               => hash_file( 'sha256', $root . '/' . $providerRelative ),
				'native_js_handles_activated'          => 0,
				'js_translation_json_generated'        => 0,
			);
		}

		$manifest = array(
			'content_admission_revision' => 2,
			'identity_hash_method'       => 'SHA256_UTF8_MSGCTXT_US_MSGID_US_MSGID_PLURAL',
			'keyset_hash_method'         => 'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256',
			'surface_path_hash_method'   => 'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_MATCHED_SOURCE_PATH',
			'translation_hash_method'    => 'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_US_NUL_JOINED_MSGSTR',
			'admissions'                 => $records,
		);
		$this->writeJson( $root . '/tools/i18n/admission/content.json', $manifest );
		$this->writeJson( $root . '/tools/i18n/admission/surfaces.json', array( 'surfaces' => $surfaces ) );

		foreach ( $productMessages as $product => $messages ) {
			$domain = null;
			foreach ( $products as $candidateDomain => $candidate ) {
				if ( $candidate['product'] === $product ) {
					$domain = $candidateDomain;
					break;
				}
			}
			$aggregateDir = $root . '/languages/providers/' . $product . '/source';
			mkdir( $aggregateDir, 0777, true );
			$this->writePo( $aggregateDir . '/fa_IR.po', $domain, $messages );
			$this->syncProvenance( $root, $manifest, $product, $domain );
		}

		return array(
			'root'     => $root,
			'products' => $products,
			'source'   => $source,
		);
	}

	private function syncProvenance( string $root, array $manifest, string $product, string $domain ): void {
		$records = array_values(
			array_filter(
				$manifest['admissions'],
				static fn( $record ) => $record['product'] === $product
			)
		);
		usort( $records, static fn( $left, $right ) => pgr_content_record_identity( $left ) <=> pgr_content_record_identity( $right ) );
		$rows = array();
		foreach ( $records as $record ) {
			$po = pgr_content_load_sparse_po( $root . '/' . $record['provider_source_path'], $domain, 'fa_IR' );
			foreach ( $po['translation_rows'] as $id => $row ) {
				if ( ! isset( $rows[ $id ] ) ) {
					$rows[ $id ] = $row;
				}
			}
		}
		ksort( $rows, SORT_STRING );
		$aggregatePath = 'languages/providers/' . $product . '/source/fa_IR.po';
		$provenance = array(
			'content_admission_revision' => 2,
			'content_admission_state'    => 'CONTENT_ADMITTED_PARTIAL',
			'content_admissions'         => array_map( 'pgr_content_provenance_record', $records ),
			'content_aggregate'          => array(
				'admitted_message_count'              => count( $rows ),
				'admitted_keyset_sha256'              => pgr_content_hash_lines( array_keys( $rows ) ),
				'admitted_translation_content_sha256' => pgr_content_hash_lines( array_values( $rows ) ),
				'provider_source_path'                 => $aggregatePath,
				'provider_source_sha256'               => hash_file( 'sha256', $root . '/' . $aggregatePath ),
			),
			'native_js_handles_activated'   => 0,
			'js_translation_json_generated' => 0,
		);
		$this->writeJson( $root . '/languages/providers/' . $product . '/source/provenance.json', $provenance );
	}

	private function writePo( string $path, string $domain, array $messages ): void {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}
		$po = "msgid \"\"\nmsgstr \"\"\n";
		$po .= "\"Language: fa_IR\\n\"\n";
		$po .= "\"Plural-Forms: nplurals=2; plural=(n > 1);\\n\"\n";
		$po .= "\"X-Domain: " . $domain . "\\n\"\n\n";
		foreach ( $messages as $msgid => $translation ) {
			$po .= 'msgid "' . addcslashes( $msgid, "\\\"\n\r\t" ) . "\"\n";
			$po .= 'msgstr "' . addcslashes( $translation, "\\\"\n\r\t" ) . "\"\n\n";
		}
		file_put_contents( $path, $po );
	}

	private function writeJson( string $path, array $data ): void {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}
		file_put_contents( $path, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
	}

	private function readJson( string $path ): array {
		return json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function removeTree( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $path );
	}
}
