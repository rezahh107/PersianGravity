<?php
/**
 * Deterministic own-plugin gettext source/POT/PO guard.
 *
 * @package PersianGravityForms
 */

declare(strict_types=1);

use Gettext\Loader\StrictPoLoader;
use Gettext\Translation;
use Gettext\Translations;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

final class PGR_Own_Catalog_Guard {

	private static function load_catalog(string $path): Translations {
		$loader                   = new StrictPoLoader();
		$loader->displayErrorLine = true;
		return $loader->loadFile($path);
	}

	/** @return array<string,array{context:?string,original:string,plural:?string}> */
	public static function identities_from_file(string $path): array {
		$identities = array();

		foreach (self::load_catalog($path) as $translation) {
			if (!$translation instanceof Translation || $translation->isDisabled()) {
				continue;
			}

			$identity = array(
				'context'  => $translation->getContext(),
				'original' => $translation->getOriginal(),
				'plural'   => $translation->getPlural(),
			);
			$key        = self::identity_key($identity['context'], $identity['original'], $identity['plural']);
			$identities[$key] = $identity;
		}

		ksort($identities, SORT_STRING);
		return $identities;
	}

	public static function identity_key(?string $context, string $original, ?string $plural): string {
		return json_encode(
			array($context, $original, $plural),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
	}

	/**
	 * @param array<string,array{context:?string,original:string,plural:?string}> $source
	 * @param array<string,array{context:?string,original:string,plural:?string}> $committed
	 * @return array{missing_from_committed:array<string,array{context:?string,original:string,plural:?string}>,extra_in_committed:array<string,array{context:?string,original:string,plural:?string}>}
	 */
	public static function compare_identity_sets(array $source, array $committed): array {
		return array(
			'missing_from_committed' => array_diff_key($source, $committed),
			'extra_in_committed'     => array_diff_key($committed, $source),
		);
	}

	public static function assert_files_match(string $source_pot, string $committed_pot): void {
		$source    = self::identities_from_file($source_pot);
		$committed = self::identities_from_file($committed_pot);
		$diff      = self::compare_identity_sets($source, $committed);

		if (array() === $diff['missing_from_committed'] && array() === $diff['extra_in_committed']) {
			return;
		}

		$message = "Own-plugin gettext source/POT identity drift detected.\n";
		$message .= self::format_diff('Missing from committed POT', $diff['missing_from_committed']);
		$message .= self::format_diff('Extra in committed POT', $diff['extra_in_committed']);
		throw new RuntimeException(rtrim($message));
	}

	public static function assert_translations_complete(string $po_path): void {
		$issues = array();

		foreach (self::load_catalog($po_path) as $translation) {
			if (!$translation instanceof Translation || $translation->isDisabled()) {
				continue;
			}

			$problem = null;
			if ($translation->getFlags()->has('fuzzy')) {
				$problem = 'fuzzy';
			} elseif (null !== $translation->getPlural()) {
				$plural_translations = $translation->getPluralTranslations();
				if (array() === $plural_translations || in_array('', $plural_translations, true)) {
					$problem = 'untranslated plural';
				}
			} elseif (!$translation->isTranslated()) {
				$problem = 'untranslated';
			}

			if (null !== $problem) {
				$issues[] = sprintf('%s: %s', $problem, $translation->getOriginal());
			}
		}

		if (array() !== $issues) {
			throw new RuntimeException(
				"Own-plugin Persian PO contains incomplete active translations:\n- " . implode("\n- ", $issues)
			);
		}
	}

	public static function assert_controlled_omission_is_detected(string $source_pot, string $committed_pot): void {
		$source    = self::identities_from_file($source_pot);
		$committed = self::identities_from_file($committed_pot);
		$baseline  = self::compare_identity_sets($source, $committed);

		if (array() !== $baseline['missing_from_committed'] || array() !== $baseline['extra_in_committed']) {
			throw new RuntimeException('Controlled omission check requires matching source and committed catalogs first.');
		}
		if (array() === $committed) {
			throw new RuntimeException('Controlled omission check requires at least one source message identity.');
		}

		$omitted_key = array_key_first($committed);
		unset($committed[$omitted_key]);
		$diff = self::compare_identity_sets($source, $committed);

		if (
			1 !== count($diff['missing_from_committed']) ||
			!isset($diff['missing_from_committed'][$omitted_key]) ||
			array() !== $diff['extra_in_committed']
		) {
			throw new RuntimeException('Controlled omission was not detected by the generic source/POT guard.');
		}
	}

	public static function run_source_check(string $repository_root, string $committed_pot): void {
		$tmp = tempnam(sys_get_temp_dir(), 'pgr-own-pot-');
		if (false === $tmp) {
			throw new RuntimeException('Could not allocate a temporary POT path.');
		}
		$tmp_pot = $tmp . '.pot';
		@unlink($tmp);

		$command = sprintf(
			'wp i18n make-pot %s %s --exclude=vendor,tests',
			escapeshellarg($repository_root),
			escapeshellarg($tmp_pot)
		);

		try {
			passthru($command, $status);
			if (0 !== $status || !is_file($tmp_pot)) {
				throw new RuntimeException('Canonical wp i18n make-pot extraction failed.');
			}

			self::assert_files_match($tmp_pot, $committed_pot);
			self::assert_controlled_omission_is_detected($tmp_pot, $committed_pot);
		} finally {
			@unlink($tmp_pot);
		}
	}

	/**
	 * @param array<string,array{context:?string,original:string,plural:?string}> $entries
	 */
	private static function format_diff(string $heading, array $entries): string {
		if (array() === $entries) {
			return '';
		}

		$output = $heading . ":\n";
		foreach ($entries as $entry) {
			$context = null === $entry['context'] ? '<none>' : $entry['context'];
			$plural  = null === $entry['plural'] ? '<none>' : $entry['plural'];
			$output .= sprintf("- context=%s | msgid=%s | plural=%s\n", $context, $entry['original'], $plural);
		}
		return $output;
	}
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	try {
		$mode = $argv[1] ?? '';
		if ('source-check' === $mode) {
			$root = dirname(__DIR__, 2);
			PGR_Own_Catalog_Guard::run_source_check($root, $root . '/languages/persian-gravityforms.pot');
			echo "Own-plugin gettext source/POT coverage: PASS\n";
			echo "Controlled omission falsification: PASS (guard rejected omitted identity)\n";
			exit(0);
		}
		if ('translation-check' === $mode && isset($argv[2])) {
			PGR_Own_Catalog_Guard::assert_translations_complete($argv[2]);
			echo "Own-plugin Persian PO completeness: PASS\n";
			exit(0);
		}
		if ('compare' === $mode && isset($argv[2], $argv[3])) {
			PGR_Own_Catalog_Guard::assert_files_match($argv[2], $argv[3]);
			echo "Own-plugin gettext source/POT coverage: PASS\n";
			exit(0);
		}

		fwrite(STDERR, "Usage: php tools/i18n/own-catalog-guard.php source-check\n");
		fwrite(STDERR, "   or: php tools/i18n/own-catalog-guard.php translation-check <fa_IR.po>\n");
		fwrite(STDERR, "   or: php tools/i18n/own-catalog-guard.php compare <source.pot> <committed.pot>\n");
		exit(2);
	} catch (Throwable $error) {
		fwrite(STDERR, $error->getMessage() . "\n");
		exit(1);
	}
}
