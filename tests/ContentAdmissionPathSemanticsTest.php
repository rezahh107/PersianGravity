<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/admission.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class ContentAdmissionPathSemanticsTest extends TestCase {
	private array $roots = array();

	protected function tearDown(): void {
		foreach ( $this->roots as $root ) {
			$this->removeTree( $root );
		}
		$this->roots = array();
	}

	public function test_canonical_repository_path_and_directory_rule_remain_valid(): void {
		$fixture = $this->recordFixture( 'src/alpha/one/messages.php', 'src/alpha/one/' );

		$this->assertTrue( pgr_content_repository_path_is_valid( 'src/alpha/one/messages.php' ) );
		$this->assertTrue( pgr_content_repository_path_is_valid( 'src/alpha/one/' ) );
		$this->assertTrue( pgr_content_surface_path_matches( 'src/alpha/one/messages.php', array( 'src/alpha/one/' ) ) );
		$this->assertSame(
			$fixture['root'] . '/src/alpha/one/messages.php',
			pgr_content_repository_path( $fixture['root'], 'src/alpha/one/messages.php', 'positive path' )
		);

		$validated = pgr_validate_content_admission_record(
			$fixture['root'],
			$fixture['record'],
			$fixture['source'],
			$fixture['products'],
			$fixture['surfaces']
		);
		$this->assertSame( 1, $validated['computed']['admitted_message_count'] );
	}

	public function test_embedded_traversal_cannot_gain_surface_membership(): void {
		$fixture = $this->recordFixture( 'allowed/../outside.php', 'allowed/' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Out-of-surface source evidence path' );
		pgr_validate_content_admission_record(
			$fixture['root'],
			$fixture['record'],
			$fixture['source'],
			$fixture['products'],
			$fixture['surfaces']
		);
	}

	public function test_invalid_surface_rule_cannot_become_authority(): void {
		$fixture = $this->recordFixture( 'allowed/messages.php', 'allowed/./' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Out-of-surface source evidence path' );
		pgr_validate_content_admission_record(
			$fixture['root'],
			$fixture['record'],
			$fixture['source'],
			$fixture['products'],
			$fixture['surfaces']
		);
	}

	public function test_invalid_repository_path_classes_are_rejected_with_shared_semantics(): void {
		$invalid = array(
			'../outside.php',
			'allowed/../outside.php',
			'allowed/./file.php',
			'/tmp/outside.php',
			'C:\\repo\\file.php',
			'C:/repo/file.php',
			'\\\\server\\share\\file.php',
			'includes\\inbox\\file.php',
		);

		foreach ( $invalid as $path ) {
			$this->assertFalse( pgr_content_repository_path_is_valid( $path ), $path );
			$this->assertFalse( pgr_content_surface_path_matches( $path, array( 'allowed/' ) ), $path );

			$thrown = false;
			try {
				pgr_content_repository_path( '/repository', $path, 'parity test' );
			} catch ( RuntimeException $exception ) {
				$thrown = true;
				$this->assertSame( 'Invalid repository-relative path: parity test', $exception->getMessage() );
			}
			$this->assertTrue( $thrown, $path );
		}
	}

	public function test_invalid_rule_is_rejected_even_when_another_rule_would_match(): void {
		$this->assertFalse(
			pgr_content_surface_path_matches(
				'allowed/messages.php',
				array( 'allowed/', 'other/../invalid/' )
			)
		);
	}

	private function recordFixture( string $evidencePath, string $rule ): array {
		$root = sys_get_temp_dir() . '/pgr-path-semantics-' . bin2hex( random_bytes( 8 ) );
		$this->roots[] = $root;
		mkdir( $root . '/evidence', 0777, true );

		$product = 'alpha';
		$domain  = 'alpha-domain';
		$version = '1.0.0';
		$surface = 'alpha::runtime::surface';
		$msgid   = 'One';
		$msgstr  = 'یک';
		$id      = hash( 'sha256', "\x1f" . $msgid . "\x1f" );

		$index = array(
			'surface_id'        => $surface,
			'source_path_rules' => array( $rule ),
			'paths'             => array( $evidencePath ),
			'entries'           => array( array( $id, array( 0 ) ) ),
		);
		$indexRelative = 'evidence/index.json';
		$this->writeJson( $root . '/' . $indexRelative, $index );

		$providerRelative = 'evidence/provider.po';
		$this->writePo( $root . '/' . $providerRelative, $domain, $msgid, $msgstr );

		$surfaceRows = array( $id . "\x1f" . $evidencePath );
		$record      = array(
			'product'                             => $product,
			'domain'                              => $domain,
			'locale'                              => 'fa_IR',
			'target_version'                      => $version,
			'content_state'                       => 'CONTENT_ADMITTED_PARTIAL',
			'reviewed_source_po_sha256'           => hash( 'sha256', 'reviewed-baseline' ),
			'reviewed_source_message_count'       => 50,
			'source_package_sha256'               => hash( 'sha256', 'package' ),
			'vendor_pot_sha256'                   => hash( 'sha256', 'pot' ),
			'surface_id'                          => $surface,
			'admitted_message_count'              => 1,
			'admitted_keyset_sha256'              => pgr_content_hash_lines( array( $id ) ),
			'admitted_surface_path_index_sha256'  => pgr_content_hash_lines( $surfaceRows ),
			'admitted_translation_content_sha256' => pgr_content_hash_lines( array( $id . "\x1f" . $msgstr ) ),
			'surface_evidence_index_path'          => $indexRelative,
			'surface_evidence_index_sha256'        => hash_file( 'sha256', $root . '/' . $indexRelative ),
			'provider_source_path'                 => $providerRelative,
			'provider_source_sha256'               => hash_file( 'sha256', $root . '/' . $providerRelative ),
			'native_js_handles_activated'          => 0,
			'js_translation_json_generated'        => 0,
		);

		return array(
			'root'     => $root,
			'record'   => $record,
			'source'   => array(
				$product => array(
					'target_version'          => $version,
					'package_sha256'          => $record['source_package_sha256'],
					'vendor_pot_sha256'       => $record['vendor_pot_sha256'],
					'canonical_message_count' => 50,
				),
			),
			'products' => array(
				$domain => array(
					'product'        => $product,
					'target_version' => $version,
					'prefix'         => $product,
					'scripts'        => array(),
				),
			),
			'surfaces' => array(
				$surface => array(
					'surface_id'        => $surface,
					'product'           => $product,
					'source_path_rules' => array( $rule ),
				),
			),
		);
	}

	private function writePo( string $path, string $domain, string $msgid, string $msgstr ): void {
		$po  = "msgid \"\"\nmsgstr \"\"\n";
		$po .= "\"Language: fa_IR\\n\"\n";
		$po .= "\"Plural-Forms: nplurals=2; plural=(n > 1);\\n\"\n";
		$po .= "\"X-Domain: {$domain}\\n\"\n\n";
		$po .= 'msgid "' . $msgid . "\"\n";
		$po .= 'msgstr "' . $msgstr . "\"\n";
		file_put_contents( $path, $po );
	}

	private function writeJson( string $path, array $data ): void {
		file_put_contents(
			$path,
			json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n"
		);
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
