<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/tools/i18n/own-catalog-guard.php';

final class OwnCatalogDriftGuardTest extends TestCase {

	public function test_identity_comparison_is_symmetric_and_generic(): void {
		$current = PGR_Own_Catalog_Guard::identity_key(null, 'Existing shipped message', null);
		$future  = PGR_Own_Catalog_Guard::identity_key(null, 'Future shipped message not known to the current catalog', null);

		$source = array(
			$current => array('context' => null, 'original' => 'Existing shipped message', 'plural' => null),
			$future  => array('context' => null, 'original' => 'Future shipped message not known to the current catalog', 'plural' => null),
		);
		$committed = array(
			$current => array('context' => null, 'original' => 'Existing shipped message', 'plural' => null),
		);

		$diff = PGR_Own_Catalog_Guard::compare_identity_sets($source, $committed);

		$this->assertSame(array($future), array_keys($diff['missing_from_committed']));
		$this->assertSame(array(), $diff['extra_in_committed']);

		$reverse = PGR_Own_Catalog_Guard::compare_identity_sets($committed, $source);
		$this->assertSame(array(), $reverse['missing_from_committed']);
		$this->assertSame(array($future), array_keys($reverse['extra_in_committed']));
	}

	public function test_catalog_identity_ignores_generator_metadata_but_preserves_context_and_plural(): void {
		$source = $this->temporary_pot(
			"msgid \"\"\nmsgstr \"\"\n\"POT-Creation-Date: 2026-09-27T00:00:00+00:00\\n\"\n\nmsgctxt \"button\"\nmsgid \"Save\"\nmsgid_plural \"Saves\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n"
		);
		$committed = $this->temporary_pot(
			"msgid \"\"\nmsgstr \"\"\n\"POT-Creation-Date: 2030-01-01T12:34:56+00:00\\n\"\n\"X-Generator: different-tool\\n\"\n\nmsgctxt \"button\"\nmsgid \"Save\"\nmsgid_plural \"Saves\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n"
		);

		try {
			PGR_Own_Catalog_Guard::assert_files_match($source, $committed);
			$this->addToAssertionCount(1);
		} finally {
			@unlink($source);
			@unlink($committed);
		}
	}

	public function test_controlled_omission_falsification_uses_real_committed_catalog_identities(): void {
		$pot = dirname(__DIR__) . '/languages/persian-gravityforms.pot';
		PGR_Own_Catalog_Guard::assert_controlled_omission_is_detected($pot, $pot);
		$this->addToAssertionCount(1);
	}

	private function temporary_pot(string $content): string {
		$path = tempnam(sys_get_temp_dir(), 'pgr-pot-test-');
		if (false === $path) {
			$this->fail('Could not allocate a temporary POT file.');
		}
		file_put_contents($path, $content);
		return $path;
	}
}
